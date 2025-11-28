<?php

namespace App\Services\ApiUvs\CourseApiServices;

use App\Models\CourseDay;
use App\Models\Person;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CourseDayAttendanceSyncService
{
    protected ApiUvsService $api;

    public function __construct(ApiUvsService $api)
    {
        $this->api = $api;
    }

    /**
     * Lokale Attendance eines CourseDay in UVS-Struktur mappen,
     * an den UVS-API-Endpunkt senden (Push) und dabei auch
     * Änderungen aus UVS "pullen".
     *
     * Nutzt die Response, um src_api_id + state in attendance_data zu pflegen.
     */
    public function syncToRemote(CourseDay $day): bool
    {
        // Ohne Kurs / Termin / Datum macht Sync keinen Sinn
        if (!$day->course || !$day->course->termin_id || !$day->date) {
            return false;
        }

        // 1) Attendance in UVS-"changes" Struktur mappen (nur "auffällige" Fälle)
        $changes = $this->mapAttendanceToUvsChanges($day);

        // 2) Teilnehmersammlung für den Pull:
        //    Alle Teilnehmer des Tages, nicht nur die mit Änderungen.
        $teilnehmerIds = $this->collectTeilnehmerIds($day);

        if (empty($teilnehmerIds) && empty($changes)) {
            // nichts zu tun
            return true;
        }

        $payload = [
            'termin_id'      => $day->course->termin_id,
            'date'           => $day->date->toDateString(),
            'teilnehmer_ids' => $teilnehmerIds,
            'changes'        => $changes,
        ];

        // 3) API-Call (Body = $payload, Query leer)
        $response = $this->api->request(
            'POST',
            '/api/course/courseday/syncattendancedata',
            $payload,
            []
        );

        // 4) Response auswerten & zurück ins Model mappen
        if (!empty($response['ok'])) {
            $this->applySyncResponse($day, $response);
            return true;
        }

        return false;
    }

    /**
     * Alle UVS-Teilnehmer-IDs für einen CourseDay sammeln.
     * (Unabhängig davon, ob es lokale Änderungen gibt.)
     */
    protected function collectTeilnehmerIds(CourseDay $day): array
    {
        $participants = $day->course->participants ?? collect();

        if ($participants->isEmpty()) {
            return [];
        }

        return $participants
            ->map(fn ($person) => (string) $person->teilnehmer_id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * aus CourseDay->attendance_data wird ein "changes"-Array für UVS (tn_fehl).
     * Enthält bei Korrekturen auf "vollständig anwesend" einen Eintrag mit
     * 'action' => 'delete'.
     */
    protected function mapAttendanceToUvsChanges(CourseDay $day): array
    {
        $attendance   = $day->attendance_data ?? [];
        $participants = $attendance['participants'] ?? [];

        if (!is_array($participants) || empty($participants)) {
            return [];
        }

        $terminId  = $day->course->termin_id;
        $date      = $day->date->toDateString(); // YYYY-MM-DD
        $tutorName = $day->course->tutor
            ? trim(($day->course->tutor->vorname ?? '') . ' ' . ($day->course->tutor->nachname ?? '') . ' (Schulnetz)')
            : 'Baustein-Dozent (Schulnetz)';

        [$courseStart, $courseEnd, $totalMinutes] = $this->computeCourseTimes($day, 'push');

        $localIds = array_keys($participants);
        $persons  = Person::whereIn('id', $localIds)->get()->keyBy('id');

        $changes = [];

        foreach ($participants as $localPersonId => $row) {
            /** @var Person|null $person */
            $person = $persons->get($localPersonId);
            if (!$person) {
                continue;
            }

            $teilnehmerId = $person->teilnehmer_id ?? null;
            if (!$teilnehmerId) {
                continue;
            }

            $institutId = $person->institut_id ?? ($day->course->institut_id ?? 0);

            $present   = (bool)($row['present'] ?? false);
            $excused   = (bool)($row['excused'] ?? false);
            $lateMin   = (int)($row['late_minutes'] ?? 0);
            $leftEarly = (int)($row['left_early_minutes'] ?? 0);

            $state     = $row['state']    ?? null;
            $hasRemote = !empty($row['src_api_id']);

            $isDirty = in_array($state, ['draft', 'dirty'], true) || !$hasRemote;

            $isFullyPresent = $present && !$excused && $lateMin === 0 && $leftEarly === 0;

            // FALL 1: Vollständig anwesend -> ggf. Delete
            if ($isFullyPresent) {
                if ($isDirty && $hasRemote) {
                    $tnFehltageId = $teilnehmerId . '-' . $terminId;

                    $changes[] = [
                        'tn_fehltage_id' => (string) $tnFehltageId,
                        'teilnehmer_id'  => (string) $teilnehmerId,
                        'institut_id'    => (int) $institutId,
                        'termin_id'      => (string) $terminId,
                        'date'           => $date,
                        'fehl_grund'     => '',
                        'fehl_bem'       => '',
                        'gekommen'       => '00:00',
                        'gegangen'       => '00:00',
                        'fehl_std'       => 0.0,
                        'status'         => 1,
                        'upd_user'       => (string) $tutorName,
                        'action'         => 'delete',
                    ];
                }

                continue;
            }

            // FALL 2: Fehlzeit, aber nicht dirty & schon remote -> nichts senden
            if (!$isDirty && $hasRemote) {
                continue;
            }

            // FALL 3: Neue oder geänderte Fehlzeit -> normalen Change erzeugen
            $fehlStd = 0.0;
            if (!$present) {
                $fehlStd = (float)($day->std ?? 0);
            }

            if (!$present) {
                $gekommen = '00:00';
                $gegangen = '00:00';
            } else {
                $gekommen = $this->resolveTimeForPush($row, 'arrived_at', 'in',  $courseStart);
                $gegangen = $this->resolveTimeForPush($row, 'left_at',    'out', $courseEnd);
            }

            if ($present && ($leftEarly > 0 || $lateMin > 0) && $totalMinutes > 0) {
                $fehlStd = round(($lateMin + $leftEarly) / 60, 2);
            }

            $fehlGrund = $this->mapReasonCode($present, $excused, $lateMin, $leftEarly);
            $note      = $this->normalizeNote($row['note'] ?? null, $day->id, $localPersonId);

            $tnFehltageId = $teilnehmerId . '-' . $terminId;

            $changes[] = [
                'tn_fehltage_id' => (string) $tnFehltageId,
                'teilnehmer_id'  => (string) $teilnehmerId,
                'institut_id'    => (int) $institutId,
                'termin_id'      => (string) $terminId,
                'date'           => $date,
                'fehl_grund'     => (string) $fehlGrund,
                'fehl_bem'       => (string) $note,
                'gekommen'       => (string) $gekommen,
                'gegangen'       => (string) $gegangen,
                'fehl_std'       => (float) $fehlStd,
                'status'         => (int) 1,
                'upd_user'       => (string) $tutorName,
            ];
        }

        return $changes;
    }

    protected function mapReasonCode(bool $present, bool $excused, int $lateMinutes, int $leftEarlyMinutes): string
    {
        if (!$present) {
            return $excused ? 'E' : 'UE';
        }

        if ($lateMinutes > 0 || $leftEarlyMinutes > 0) {
            return 'TA';
        }

        if ($excused) {
            return 'E';
        }

        return 'E';
    }

    protected function reverseMapReasonCode(string $fehlGrund): array
    {
        $code = strtoupper(trim($fehlGrund));

        switch ($code) {
            case 'E':
            case 'K':
                return ['present' => null, 'excused' => true];

            case 'UE':
            case 'F':
                return ['present' => false, 'excused' => false];

            case 'TA':
            case 'T':
                return ['present' => true, 'excused' => false];

            default:
                return ['present' => null, 'excused' => null];
        }
    }

    protected function normalizeNote(mixed $rawNote, int $dayId, int|string $localPersonId): string
    {
        if (is_array($rawNote)) {
            $note = implode(' | ', array_map(
                static fn ($v) => is_scalar($v) ? (string) $v : '',
                $rawNote
            ));

            return trim($note);
        }

        if (is_scalar($rawNote)) {
            return trim((string) $rawNote);
        }

        return '';
    }

    /**
     * Response der UVS-API zurück in attendance_data mappen.
     * Achtet auf action 'deleted' aus pushed.results.
     */
    protected function applySyncResponse(CourseDay $day, array $response): void
    {
        $outerData = $response['data'] ?? [];
        $innerData = $outerData['data'] ?? $outerData;

        $pushed  = $innerData['pushed'] ?? null;
        $pulled  = $innerData['pulled'] ?? null;
        $results = $pushed['results'] ?? [];

        $pulledItems = [];
        if (is_array($pulled) && !empty($pulled['items']) && is_array($pulled['items'])) {
            $pulledItems = $pulled['items'];
        }

        if (empty($results) && empty($pulledItems)) {
            return;
        }

        $attendance   = $day->attendance_data ?? [];
        $participants = $attendance['participants'] ?? [];

        /**
         * NEU:
         * Wenn keine participants-Struktur existiert,
         * versuchen wir sie aus pulledItems + Person(teilnehmer_id) aufzubauen.
         */
        if (!is_array($participants) || empty($participants)) {
            $remoteTeilnehmerIds = collect($pulledItems ?? [])
                ->pluck('teilnehmer_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($remoteTeilnehmerIds)) {
                $personsByTeilnehmerId = Person::whereIn('teilnehmer_id', $remoteTeilnehmerIds)
                    ->get()
                    ->groupBy('teilnehmer_id');

                foreach ($personsByTeilnehmerId as $tnId => $personsGroup) {
                    foreach ($personsGroup as $person) {
                        $participants[$person->id] = [
                            'present'            => false,
                            'excused'            => false,
                            'late_minutes'       => 0,
                            'left_early_minutes' => 0,
                            'note'               => null,
                            'timestamps'         => [
                                'in'  => null,
                                'out' => null,
                            ],
                            'arrived_at'         => null,
                            'left_at'            => null,
                            'src_api_id'         => null,
                            'state'              => null,
                        ];
                    }
                }

                $attendance['participants'] = $participants;
            }

            if (!is_array($participants) || empty($participants)) {
                // Kein Mapping möglich
                return;
            }
        }

        $localIds = array_keys($participants);
        $persons  = Person::whereIn('id', $localIds)->get()->keyBy('id');

        // Mapping teilnehmer_id (UVS) → localPersonId(s)
        $teilnehmerIdToLocal = [];

        foreach ($participants as $localPersonId => $row) {
            /** @var Person|null $person */
            $person = $persons->get($localPersonId);
            if (!$person || empty($person->teilnehmer_id)) {
                continue;
            }

            $uvsId = $person->teilnehmer_id;

            if (!isset($teilnehmerIdToLocal[$uvsId])) {
                $teilnehmerIdToLocal[$uvsId] = [];
            }

            $teilnehmerIdToLocal[$uvsId][] = $localPersonId;
        }

        $now     = Carbon::now();
        $nowStr  = $now->toDateTimeString();
        $dayDate = $day->date?->toDateString();

        [$courseStart, $courseEnd, $totalMinutes] = $this->computeCourseTimes($day, 'pull');

        $updatedFromPushed = 0;
        $updatedFromPulled = 0;

        // 1) pushed.results
        if (!empty($results)) {
            foreach ($results as $result) {
                $uid          = $result['uid']           ?? null;
                $teilnehmerId = $result['teilnehmer_id'] ?? null;
                $action       = $result['action']        ?? null;

                if (!$uid || !$teilnehmerId) {
                    continue;
                }

                if (empty($teilnehmerIdToLocal[$teilnehmerId])) {
                    continue;
                }

                foreach ($teilnehmerIdToLocal[$teilnehmerId] as $localPersonId) {
                    $row = $participants[$localPersonId] ?? [];

                    if ($action === 'deleted') {
                        $row['src_api_id']         = null;
                        $row['state']              = null;
                        $row['late_minutes']       = 0;
                        $row['left_early_minutes'] = 0;
                        $row['excused']            = false;
                    } else {
                        $row['src_api_id'] = $uid;
                        $row['state']      = 'synced';
                    }

                    $row['updated_at'] = $nowStr;

                    $participants[$localPersonId] = $row;
                    $updatedFromPushed++;
                }
            }
        }

        // 2) pulled.items
        if (!empty($pulledItems)) {
            foreach ($pulledItems as $item) {
                $uid          = $item['uid']            ?? null;
                $teilnehmerId = $item['teilnehmer_id']  ?? null;
                $fehlDatumIso = $item['fehl_datum_iso'] ?? null;

                if (!$uid || !$teilnehmerId) {
                    continue;
                }

                if ($dayDate && $fehlDatumIso && $fehlDatumIso !== $dayDate) {
                    continue;
                }

                if (empty($teilnehmerIdToLocal[$teilnehmerId])) {
                    continue;
                }

                $fehlStdRemote   = (float)($item['fehl_std'] ?? 0.0);
                $fehlGrundRemote = (string)($item['fehl_grund'] ?? '');
                $fehlBemRemote   = trim((string)($item['fehl_bem'] ?? ''));
                $gekommenRemote  = trim((string)($item['gekommen'] ?? ''));
                $gegangenRemote  = trim((string)($item['gegangen'] ?? ''));

                $statusRemote  = isset($item['status']) ? (int)$item['status'] : null;
                $updUserRemote = (string)($item['upd_user'] ?? '');

                $isSchulnetzItem = ($statusRemote === 1) && str_contains($updUserRemote, '(Schulnetz)');

                $gekommenCarbon = $this->parseTimeOnDay($day->date, $gekommenRemote);
                $gegangenCarbon = $this->parseTimeOnDay($day->date, $gegangenRemote);

                foreach ($teilnehmerIdToLocal[$teilnehmerId] as $localPersonId) {
                    $row = $participants[$localPersonId] ?? [];

                    $expected = $this->buildExpectedRemoteSnapshotForRow(
                        $day,
                        $row,
                        $courseStart,
                        $courseEnd,
                        $totalMinutes,
                        $localPersonId
                    );

                    $diffFehlStd  = abs($fehlStdRemote - $expected['fehl_std']);
                    $sameGrund    = ($fehlGrundRemote === $expected['fehl_grund']);
                    $sameBem      = ($fehlBemRemote === $expected['fehl_bem']);
                    $sameGekommen = ($gekommenRemote === $expected['gekommen']);
                    $sameGegangen = ($gegangenRemote === $expected['gegangen']);

                    $noChange = $diffFehlStd < 0.01
                        && $sameGrund
                        && $sameBem
                        && $sameGekommen
                        && $sameGegangen;

                    // Schulnetz-Item ohne Änderungen -> neutralisieren
                    if ($isSchulnetzItem && $noChange) {
                        $row['updated_at'] = null;
                        $row['src_api_id'] = null;
                        $row['state']      = null;

                        $participants[$localPersonId] = $row;
                        continue;
                    }

                    // Normale Übernahme
                    $row['src_api_id'] = $uid;
                    $row['state']      = 'synced';
                    $row['updated_at'] = $nowStr;

                    $row['note'] = $fehlBemRemote !== '' ? $fehlBemRemote : ($row['note'] ?? null);

                    if ($gekommenRemote !== '') {
                        $row['arrived_at'] = $gekommenRemote;
                    }

                    if ($gegangenRemote !== '') {
                        $row['left_at'] = $gegangenRemote;
                    }

                    $lateMinutes      = (int)($row['late_minutes'] ?? 0);
                    $leftEarlyMinutes = (int)($row['left_early_minutes'] ?? 0);

                    if ($courseStart && $gekommenCarbon) {
                        $diff        = $courseStart->diffInMinutes($gekommenCarbon, false);
                        $lateMinutes = $diff > 0 ? $diff : 0;
                    }

                    if ($courseEnd && $gegangenCarbon) {
                        $diff             = $gegangenCarbon->diffInMinutes($courseEnd, false);
                        $leftEarlyMinutes = $diff < 0 ? abs($diff) : 0;
                    }

                    $row['late_minutes']       = $lateMinutes;
                    $row['left_early_minutes'] = $leftEarlyMinutes;

                    if ($totalMinutes > 0) {
                        $totalHours     = $totalMinutes / 60.0;
                        $row['present'] = $fehlStdRemote < ($totalHours - 0.01);
                    } elseif ($gekommenCarbon || $gegangenCarbon) {
                        $row['present'] = true;
                    } else {
                        $row['present'] = (bool)($row['present'] ?? false);
                    }

                    $reverse = $this->reverseMapReasonCode($fehlGrundRemote);

                    if ($reverse['excused'] !== null) {
                        $row['excused'] = $reverse['excused'];
                    }

                    if ($reverse['present'] !== null) {
                        $row['present'] = $reverse['present'];
                    }

                    $participants[$localPersonId] = $row;
                    $updatedFromPulled++;
                }
            }
        }

        if ($updatedFromPushed === 0 && $updatedFromPulled === 0) {
            return;
        }

        $attendance['participants'] = $participants;

        if (!isset($attendance['status']) || !is_array($attendance['status'])) {
            $attendance['status'] = [
                'start'      => 1,
                'end'        => 0,
                'state'      => null,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        $attendance['status']['updated_at'] = $nowStr;

        $day->attendance_data           = $attendance;
        $day->attendance_updated_at     = $now;
        $day->attendance_last_synced_at = $now;

        $day->saveQuietly();

        // EINZIGER LOG-EINTRAG
        Log::info('CourseDayAttendanceSyncService.applySyncResponse: attendance_data aktualisiert.', [
            'day_id'            => $day->id,
            'updated_rows'      => $updatedFromPushed + $updatedFromPulled,
            'updated_pushed'    => $updatedFromPushed,
            'updated_pulled'    => $updatedFromPulled,
        ]);
    }

    protected function buildExpectedRemoteSnapshotForRow(
        CourseDay $day,
        array $row,
        ?Carbon $courseStart,
        ?Carbon $courseEnd,
        int $totalMinutes,
        int|string $localPersonId
    ): array {
        $present   = (bool)($row['present'] ?? false);
        $excused   = (bool)($row['excused'] ?? false);
        $lateMin   = (int)($row['late_minutes'] ?? 0);
        $leftEarly = (int)($row['left_early_minutes'] ?? 0);

        $fehlStd = 0.0;
        if (!$present) {
            $fehlStd = (float)($day->std ?? 0);
        }

        if ($present && ($leftEarly > 0 || $lateMin > 0) && $totalMinutes > 0) {
            $fehlStd = round(($lateMin + $leftEarly) / 60, 2);
        }

        $fehlGrund = $this->mapReasonCode($present, $excused, $lateMin, $leftEarly);
        $note      = $this->normalizeNote($row['note'] ?? null, $day->id, $localPersonId);

        if (!$present) {
            $gekommen = '00:00';
            $gegangen = '00:00';
        } else {
            $gekommen = $this->resolveTimeForPush($row, 'arrived_at', 'in',  $courseStart);
            $gegangen = $this->resolveTimeForPush($row, 'left_at',    'out', $courseEnd);
        }

        return [
            'fehl_std'   => (float) $fehlStd,
            'fehl_grund' => (string) $fehlGrund,
            'fehl_bem'   => (string) $note,
            'gekommen'   => (string) $gekommen,
            'gegangen'   => (string) $gegangen,
        ];
    }

    protected function computeCourseTimes(CourseDay $day, string $context = 'generic'): array
    {
        $courseStart  = null;
        $courseEnd    = null;
        $totalMinutes = 0;

        $totalHours = (float)($day->std ?? 0.0);
        $date       = $day->date;

        if (!$date || $totalHours <= 0) {
            return [null, null, 0];
        }

        $totalMinutes = (int) round($totalHours * 60);
        $rawStart     = $day->start_time;

        try {
            if ($rawStart instanceof Carbon) {
                $courseStart = (clone $rawStart);
            } elseif (is_string($rawStart) && trim($rawStart) !== '') {
                $startStr = trim($rawStart);

                if (preg_match('/^\d{1,2}:\d{2}$/', $startStr)) {
                    $courseStart = Carbon::parse($date->toDateString() . ' ' . $startStr);
                } else {
                    $courseStart = Carbon::parse($startStr);
                }
            }

            if ($courseStart) {
                $courseEnd = (clone $courseStart)->addMinutes($totalMinutes);
            }
        } catch (\Throwable $e) {
            $courseStart  = null;
            $courseEnd    = null;
            $totalMinutes = 0;
        }

        return [$courseStart, $courseEnd, $totalMinutes];
    }

    protected function parseTimeOnDay(?Carbon $date, ?string $time): ?Carbon
    {
        if (!$date || !$time) {
            return null;
        }

        $time = trim($time);
        if ($time === '' || $time === '00:00' || $time === '0:00') {
            return null;
        }

        try {
            return Carbon::parse($date->toDateString() . ' ' . $time);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function resolveTimeForPush(array $row, string $directKey, string $timestampKey, ?Carbon $fallback): string
    {
        if (!empty($row[$directKey]) && is_string($row[$directKey])) {
            return $this->normalizeTimeString($row[$directKey]);
        }

        $ts = $row['timestamps'][$timestampKey] ?? null;
        if (!empty($ts) && is_string($ts)) {
            try {
                $dt = Carbon::parse($ts);
                return $dt->format('H:i');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($fallback instanceof Carbon) {
            return $fallback->format('H:i');
        }

        return '00:00';
    }

    protected function normalizeTimeString(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time;
        }

        try {
            $dt = Carbon::parse($time);
            return $dt->format('H:i');
        } catch (\Throwable $e) {
            return '00:00';
        }
    }
}
