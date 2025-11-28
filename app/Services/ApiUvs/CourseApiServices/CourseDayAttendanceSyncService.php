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
        Log::info('CourseDayAttendanceSyncService.syncToRemote: Start', [
            'day_id'    => $day->id,
            'course_id' => $day->course?->id,
            'termin_id' => $day->course?->termin_id,
            'date'      => $day->date?->toDateString(),
        ]);

        // Ohne Kurs / Termin / Datum macht Sync keinen Sinn
        if (!$day->course || !$day->course->termin_id || !$day->date) {
            Log::warning("CourseDayAttendanceSyncService: Sync-Job abgebrochen - fehlende Daten (CourseDay #{$day->id}).", [
                'course_id' => $day->course->id ?? null,
                'termin_id' => $day->course->termin_id ?? null,
                'date'      => $day->date?->toDateString(),
            ]);
            return false;
        }

        // 1) Attendance in UVS-"changes" Struktur mappen (nur "auffällige" Fälle)
        $changes = $this->mapAttendanceToUvsChanges($day);

        if (empty($changes)) {
            // trotzdem syncen, damit UVS-Änderungen gepulled werden können.
            Log::info("CourseDayAttendanceSyncService: Keine lokalen Attendance-Änderungen für CourseDay #{$day->id}, starte dennoch Pull-Sync.");
        } else {
            Log::info("CourseDayAttendanceSyncService: Attendance-Sync für CourseDay #{$day->id} mit " . count($changes) . " Änderungen vorbereitet.");
        }

        // 2) Teilnehmersammlung für den Pull:
        //    Alle Teilnehmer des Tages, nicht nur die mit Änderungen.
        $teilnehmerIds = $this->collectTeilnehmerIds($day);

        if (empty($teilnehmerIds) && empty($changes)) {
            Log::info("CourseDayAttendanceSyncService: Keine Teilnehmer und keine lokalen Änderungen für CourseDay #{$day->id}, Sync übersprungen.");
            return true;
        }

        $payload = [
            'termin_id'      => $day->course->termin_id,
            'date'           => $day->date->toDateString(),
            'teilnehmer_ids' => $teilnehmerIds,
            'changes'        => $changes,
        ];

        Log::info('CourseDayAttendanceSyncService.syncToRemote: Payload vorbereitet', [
            'day_id'               => $day->id,
            'termin_id'            => $day->course->termin_id,
            'payload_date'         => $payload['date'],
            'teilnehmer_ids_count' => count($teilnehmerIds),
            'changes_count'        => count($changes),
            'changes_sample'       => array_slice($changes, 0, 3),
        ]);

        // 3) API-Call (Body = $payload, Query leer)
        $response = $this->api->request(
            'POST',
            '/api/course/courseday/syncattendancedata',
            $payload,
            []
        );

        Log::info('CourseDayAttendanceSyncService.syncToRemote: Response erhalten', [
            'day_id'        => $day->id,
            'response_type' => gettype($response),
            'response_keys' => is_array($response) ? array_keys($response) : null,
        ]);

        // 4) Response auswerten & zurück ins Model mappen
        if (!empty($response['ok'])) {
            $this->applySyncResponse($day, $response);

            Log::info('CourseDayAttendanceSyncService: UVS-Attendance-Sync durchgeführt.', [
                'day_id'    => $day->id,
                'termin_id' => $day->course->termin_id,
                'payload'   => $payload,
                'response'  => $response,
            ]);

            return true;
        }

        Log::warning('CourseDayAttendanceSyncService: UVS-Sync ohne ok-Flag.', [
            'day_id'    => $day->id,
            'termin_id' => $day->course->termin_id,
            'response'  => $response,
        ]);

        return false;
    }

    /**
     * Alle UVS-Teilnehmer-IDs für einen CourseDay sammeln.
     * (Unabhängig davon, ob es lokale Änderungen gibt.)
     */
    protected function collectTeilnehmerIds(CourseDay $day): array
    {
        $participants = $day->course->participants ?? collect();

        Log::info('CourseDayAttendanceSyncService.collectTeilnehmerIds: Start', [
            'day_id'             => $day->id,
            'participants_count' => $participants->count(),
        ]);

        if ($participants->isEmpty()) {
            return [];
        }

        $ids = $participants
            ->map(fn($person) => (string) $person->teilnehmer_id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        Log::info('CourseDayAttendanceSyncService.collectTeilnehmerIds: Ergebnis', [
            'day_id'         => $day->id,
            'teilnehmer_ids' => $ids,
        ]);

        return $ids;
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

        Log::info('CourseDayAttendanceSyncService.mapAttendanceToUvsChanges: Start', [
            'day_id'             => $day->id,
            'has_attendance'     => !empty($attendance),
            'participants_count' => is_array($participants) ? count($participants) : 0,
        ]);

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

        Log::info('CourseDayAttendanceSyncService.mapAttendanceToUvsChanges: Persons geladen', [
            'day_id'          => $day->id,
            'local_ids_count' => count($localIds),
            'persons_count'   => $persons->count(),
        ]);

        $changes = [];

        foreach ($participants as $localPersonId => $row) {
            /** @var Person|null $person */
            $person = $persons->get($localPersonId);
            if (!$person) {
                Log::warning('CourseDayAttendanceSyncService.mapAttendanceToUvsChanges: Person nicht gefunden', [
                    'day_id'        => $day->id,
                    'localPersonId' => $localPersonId,
                ]);
                continue;
            }

            $teilnehmerId = $person->teilnehmer_id ?? null;
            if (!$teilnehmerId) {
                Log::warning('CourseDayAttendanceSyncService.mapAttendanceToUvsChanges: Person ohne teilnehmer_id, wird übersprungen.', [
                    'day_id'        => $day->id,
                    'localPersonId' => $localPersonId,
                    'person_id'     => $person->id,
                ]);
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

                    Log::info('CourseDayAttendanceSyncService.mapAttendanceToUvsChanges: Delete-Change erzeugt (vollständig anwesend, vorher remote vorhanden).', [
                        'day_id'        => $day->id,
                        'localPersonId' => $localPersonId,
                        'teilnehmer_id' => $teilnehmerId,
                        'tn_fehltage_id'=> $tnFehltageId,
                    ]);
                } else {
                    Log::debug('CourseDayAttendanceSyncService.mapAttendanceToUvsChanges: Teilnehmer vollständig anwesend, kein Change notwendig.', [
                        'day_id'        => $day->id,
                        'localPersonId' => $localPersonId,
                        'teilnehmer_id' => $teilnehmerId,
                        'hasRemote'     => $hasRemote,
                        'state'         => $state,
                    ]);
                }

                continue;
            }

            // FALL 2: Fehlzeit, aber nicht dirty & schon remote -> nichts senden
            if (!$isDirty && $hasRemote) {
                Log::debug('CourseDayAttendanceSyncService.mapAttendanceToUvsChanges: Nicht vollständig anwesend, aber nicht dirty & bereits remote, kein Change.', [
                    'day_id'        => $day->id,
                    'localPersonId' => $localPersonId,
                    'teilnehmer_id' => $teilnehmerId,
                    'state'         => $state,
                ]);
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

        Log::info('CourseDayAttendanceSyncService.mapAttendanceToUvsChanges: Fertig', [
            'day_id'         => $day->id,
            'changes_count'  => count($changes),
            'changes_sample' => array_slice($changes, 0, 3),
        ]);

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
            Log::warning('CourseDayAttendanceSyncService: note ist ein Array, wird zu String flach gemacht.', [
                'day_id'        => $dayId,
                'localPersonId' => $localPersonId,
                'note'          => $rawNote,
            ]);

            $note = implode(' | ', array_map(
                static fn($v) => is_scalar($v) ? (string) $v : '',
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
        Log::info('CourseDayAttendanceSyncService.applySyncResponse: Start', [
            'day_id'        => $day->id,
            'response_type' => gettype($response),
            'response_keys' => is_array($response) ? array_keys($response) : null,
        ]);

        $outerData = $response['data'] ?? [];
        $innerData = $outerData['data'] ?? $outerData;

        $pushed  = $innerData['pushed'] ?? null;
        $pulled  = $innerData['pulled'] ?? null;
        $results = $pushed['results'] ?? [];

        $pulledItems = [];
        if (is_array($pulled) && !empty($pulled['items']) && is_array($pulled['items'])) {
            $pulledItems = $pulled['items'];
        }

        Log::info('CourseDayAttendanceSyncService.applySyncResponse: Parsed response structure', [
            'has_outer_data' => !empty($outerData),
            'has_inner_data' => !empty($innerData),
            'has_pushed'     => !empty($pushed),
            'has_pulled'     => !empty($pulled),
            'results_count'  => is_countable($results) ? count($results) : 0,
            'pulled_items'   => is_countable($pulledItems) ? count($pulledItems) : 0,
        ]);

        if (empty($results) && empty($pulledItems)) {
            Log::warning('CourseDayAttendanceSyncService.applySyncResponse: Weder pushed.results noch pulled.items vorhanden, breche ab.', [
                'day_id' => $day->id,
            ]);
            return;
        }

        $attendance   = $day->attendance_data ?? [];
        $participants = $attendance['participants'] ?? [];

        if (!is_array($participants) || empty($participants)) {
            Log::warning('CourseDayAttendanceSyncService.applySyncResponse: Keine participants-Struktur vorhanden, nichts zu mappen.', [
                'day_id' => $day->id,
            ]);
            return;
        }

        $localIds = array_keys($participants);
        $persons  = Person::whereIn('id', $localIds)->get()->keyBy('id');

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

        Log::info('CourseDayAttendanceSyncService.applySyncResponse: Mapping teilnehmer_id → localPersonId erstellt', [
            'day_id'                  => $day->id,
            'teilnehmerIdToLocal_map' => $teilnehmerIdToLocal,
        ]);

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
                    Log::warning('CourseDayAttendanceSyncService.applySyncResponse: pushed-Result ohne uid oder teilnehmer_id.', [
                        'day_id'      => $day->id,
                        'result_item' => $result,
                    ]);
                    continue;
                }

                if (empty($teilnehmerIdToLocal[$teilnehmerId])) {
                    Log::warning('CourseDayAttendanceSyncService.applySyncResponse: pushed-Result mit unbekannter teilnehmer_id, kein lokales Mapping.', [
                        'day_id'        => $day->id,
                        'teilnehmer_id' => $teilnehmerId,
                        'uid'           => $uid,
                    ]);
                    continue;
                }

                foreach ($teilnehmerIdToLocal[$teilnehmerId] as $localPersonId) {
                    $row = $participants[$localPersonId] ?? [];

                    Log::debug('CourseDayAttendanceSyncService.applySyncResponse: Aktualisiere Teilnehmer-Zeile (pushed)', [
                        'day_id'        => $day->id,
                        'localPersonId' => $localPersonId,
                        'teilnehmer_id' => $teilnehmerId,
                        'uid'           => $uid,
                        'action'        => $action,
                        'row_before'    => $row,
                    ]);

                    if ($action === 'deleted') {
                        $row['src_api_id'] = null;
                        $row['state']      = null;
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
                    Log::warning('CourseDayAttendanceSyncService.applySyncResponse: pulled-Item ohne uid oder teilnehmer_id.', [
                        'day_id' => $day->id,
                        'item'   => $item,
                    ]);
                    continue;
                }

                if ($dayDate && $fehlDatumIso && $fehlDatumIso !== $dayDate) {
                    Log::debug('CourseDayAttendanceSyncService.applySyncResponse: pulled-Item gehört nicht zu diesem Tag, wird übersprungen.', [
                        'day_id'        => $day->id,
                        'fehl_datum'    => $fehlDatumIso,
                        'day_date'      => $dayDate,
                        'teilnehmer_id' => $teilnehmerId,
                        'uid'           => $uid,
                    ]);
                    continue;
                }

                if (empty($teilnehmerIdToLocal[$teilnehmerId])) {
                    Log::warning('CourseDayAttendanceSyncService.applySyncResponse: pulled-Item mit unbekannter teilnehmer_id, kein lokales Mapping.', [
                        'day_id'        => $day->id,
                        'teilnehmer_id' => $teilnehmerId,
                        'uid'           => $uid,
                    ]);
                    continue;
                }

                $fehlStdRemote   = (float)($item['fehl_std'] ?? 0.0);
                $fehlGrundRemote = (string)($item['fehl_grund'] ?? '');
                $fehlBemRemote   = trim((string)($item['fehl_bem'] ?? ''));
                $gekommenRemote  = trim((string)($item['gekommen'] ?? ''));
                $gegangenRemote  = trim((string)($item['gegangen'] ?? ''));

                $statusRemote   = isset($item['status']) ? (int)$item['status'] : null;
                $updUserRemote  = (string)($item['upd_user'] ?? '');

                $isSchulnetzItem = ($statusRemote === 1) && str_contains($updUserRemote, '(Schulnetz)');

                $gekommenCarbon = $this->parseTimeOnDay($day->date, $gekommenRemote);
                $gegangenCarbon = $this->parseTimeOnDay($day->date, $gegangenRemote);

                foreach ($teilnehmerIdToLocal[$teilnehmerId] as $localPersonId) {
                    $row = $participants[$localPersonId] ?? [];

                    Log::debug('CourseDayAttendanceSyncService.applySyncResponse: pulled-Item verarbeitet', [
                        'day_id'          => $day->id,
                        'localPersonId'   => $localPersonId,
                        'teilnehmer_id'   => $teilnehmerId,
                        'uid'             => $uid,
                        'fehl_datum'      => $fehlDatumIso,
                        'status_remote'   => $statusRemote,
                        'upd_user_remote' => $updUserRemote,
                        'is_schulnetz'    => $isSchulnetzItem,
                        'row_before'      => $row,
                    ]);

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

                    Log::info('CourseDayAttendanceSyncService.applySyncResponse: Vergleich remote vs expected', [
                        'day_id'        => $day->id,
                        'localPersonId' => $localPersonId,
                        'uid'           => $uid,
                        'is_schulnetz'  => $isSchulnetzItem,
                        'remote'        => [
                            'fehl_std'   => $fehlStdRemote,
                            'fehl_grund' => $fehlGrundRemote,
                            'fehl_bem'   => $fehlBemRemote,
                            'gekommen'   => $gekommenRemote,
                            'gegangen'   => $gegangenRemote,
                        ],
                        'expected'      => $expected,
                        'diffFehlStd'   => $diffFehlStd,
                        'sameGrund'     => $sameGrund,
                        'sameBem'       => $sameBem,
                        'sameGekommen'  => $sameGekommen,
                        'sameGegangen'  => $sameGegangen,
                        'noChange'      => $noChange,
                    ]);

                    // Schulnetz-Item ohne Änderungen -> neutralisieren
                    if ($isSchulnetzItem && $noChange) {
                        Log::info('CourseDayAttendanceSyncService.applySyncResponse: Schulnetz-Item ohne Änderungen, wird neutralisiert.', [
                            'day_id'        => $day->id,
                            'localPersonId' => $localPersonId,
                            'teilnehmer_id' => $teilnehmerId,
                            'uid'           => $uid,
                        ]);

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
                        $diff = $courseStart->diffInMinutes($gekommenCarbon, false);
                        $lateMinutes = $diff > 0 ? $diff : 0;
                    }

                    if ($courseEnd && $gegangenCarbon) {
                        $diff = $gegangenCarbon->diffInMinutes($courseEnd, false);
                        $leftEarlyMinutes = $diff < 0 ? abs($diff) : 0;
                    }

                    $row['late_minutes']       = $lateMinutes;
                    $row['left_early_minutes'] = $leftEarlyMinutes;

                    if ($totalMinutes > 0) {
                        $totalHours       = $totalMinutes / 60.0;
                        $row['present']   = $fehlStdRemote < ($totalHours - 0.01);
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
            Log::warning('CourseDayAttendanceSyncService.applySyncResponse: Es konnten keine Teilnehmer-Zeilen aktualisiert werden.', [
                'day_id' => $day->id,
            ]);
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

        Log::info('CourseDayAttendanceSyncService.applySyncResponse: Fertig, Attendance gespeichert.', [
            'day_id'            => $day->id,
            'updated_rows'      => $updatedFromPushed + $updatedFromPulled,
            'updated_pushed'    => $updatedFromPushed,
            'updated_pulled'    => $updatedFromPulled,
            'attendance_status' => $attendance['status'],
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
            'fehl_std'   => (float)$fehlStd,
            'fehl_grund' => (string)$fehlGrund,
            'fehl_bem'   => (string)$note,
            'gekommen'   => (string)$gekommen,
            'gegangen'   => (string)$gegangen,
        ];
    }

    protected function computeCourseTimes(CourseDay $day, string $context = 'generic'): array
    {
        $courseStart  = null;
        $courseEnd    = null;
        $totalMinutes = 0;

        $totalHours = (float)($day->std ?? 0.0);
        $date       = $day->date;

        Log::info('CourseDayAttendanceSyncService.computeCourseTimes: Start', [
            'context'        => $context,
            'day_id'         => $day->id,
            'date'           => $date?->toDateString(),
            'std'            => $day->std,
            'total_hours'    => $totalHours,
            'raw_start'      => $day->start_time,
            'raw_start_type' => gettype($day->start_time),
        ]);

        if (!$date || $totalHours <= 0) {
            Log::info('CourseDayAttendanceSyncService.computeCourseTimes: Kein Datum oder keine Stunden, breche ab.', [
                'context' => $context,
                'day_id'  => $day->id,
            ]);
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
            } else {
                Log::warning('CourseDayAttendanceSyncService.computeCourseTimes: Keine sinnvolle start_time gesetzt.', [
                    'context'   => $context,
                    'day_id'    => $day->id,
                    'raw_start' => $rawStart,
                ]);
            }

            if ($courseStart) {
                $courseEnd = (clone $courseStart)->addMinutes($totalMinutes);
            }
        } catch (\Throwable $e) {
            Log::warning('CourseDayAttendanceSyncService.computeCourseTimes: Konnte start_time nicht parsen.', [
                'context'       => $context,
                'day_id'        => $day->id,
                'raw_start'     => $rawStart,
                'total_hours'   => $totalHours,
                'total_minutes' => $totalMinutes,
                'error'         => $e->getMessage(),
            ]);
            $courseStart  = null;
            $courseEnd    = null;
            $totalMinutes = 0;
        }

        Log::info('CourseDayAttendanceSyncService.computeCourseTimes: Ergebnis', [
            'context'       => $context,
            'day_id'        => $day->id,
            'course_start'  => $courseStart?->toDateTimeString(),
            'course_end'    => $courseEnd?->toDateTimeString(),
            'total_minutes' => $totalMinutes,
        ]);

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
            $dt = Carbon::parse($date->toDateString() . ' ' . $time);

            Log::debug('CourseDayAttendanceSyncService.parseTimeOnDay: Parsed', [
                'date' => $date->toDateString(),
                'time' => $time,
                'dt'   => $dt->toDateTimeString(),
            ]);

            return $dt;
        } catch (\Throwable $e) {
            Log::warning('CourseDayAttendanceSyncService: Konnte Zeit nicht parsen.', [
                'date'  => $date?->toDateString(),
                'time'  => $time,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function resolveTimeForPush(array $row, string $directKey, string $timestampKey, ?Carbon $fallback): string
    {
        Log::debug('CourseDayAttendanceSyncService.resolveTimeForPush: Start', [
            'directKey'      => $directKey,
            'timestampKey'   => $timestampKey,
            'direct_value'   => $row[$directKey] ?? null,
            'timestamps_in'  => $row['timestamps']['in']  ?? null,
            'timestamps_out' => $row['timestamps']['out'] ?? null,
        ]);

        if (!empty($row[$directKey]) && is_string($row[$directKey])) {
            $value = $this->normalizeTimeString($row[$directKey]);

            Log::debug('CourseDayAttendanceSyncService.resolveTimeForPush: Direktfeld verwendet.', [
                'directKey' => $directKey,
                'value'     => $value,
            ]);

            return $value;
        }

        $ts = $row['timestamps'][$timestampKey] ?? null;
        if (!empty($ts) && is_string($ts)) {
            try {
                $dt    = Carbon::parse($ts);
                $value = $dt->format('H:i');

                Log::debug('CourseDayAttendanceSyncService.resolveTimeForPush: timestamps verwendet.', [
                    'key'   => $timestampKey,
                    'value' => $value,
                ]);

                return $value;
            } catch (\Throwable $e) {
                Log::warning('CourseDayAttendanceSyncService: Konnte timestamps-Zeit nicht parsen (Push).', [
                    'key'   => $timestampKey,
                    'value' => $ts,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($fallback instanceof Carbon) {
            $value = $fallback->format('H:i');

            Log::debug('CourseDayAttendanceSyncService.resolveTimeForPush: Fallback-Kurszeit verwendet.', [
                'directKey' => $directKey,
                'value'     => $value,
            ]);

            return $value;
        }

        Log::debug('CourseDayAttendanceSyncService.resolveTimeForPush: Kein Wert gefunden, default 00:00.', [
            'directKey'    => $directKey,
            'timestampKey' => $timestampKey,
        ]);

        return '00:00';
    }

    protected function normalizeTimeString(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time;
        }

        try {
            $dt   = Carbon::parse($time);
            $norm = $dt->format('H:i');

            Log::debug('CourseDayAttendanceSyncService.normalizeTimeString: Normalisiert.', [
                'original' => $time,
                'parsed'   => $dt->toDateTimeString(),
                'norm'     => $norm,
            ]);

            return $norm;
        } catch (\Throwable $e) {
            Log::warning('CourseDayAttendanceSyncService: Konnte Zeitstring nicht normalisieren.', [
                'time'  => $time,
                'error' => $e->getMessage(),
            ]);
            return '00:00';
        }
    }
}
