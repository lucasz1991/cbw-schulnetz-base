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

    protected const ENDPOINT_SYNC = '/api/course/courseday/syncattendancedata';
    protected const ENDPOINT_LOAD = '/api/course/courseday/loadattendancedata';

    public const STATE_DIRTY  = 'dirty';
    public const STATE_DRAFT  = 'draft';
    public const STATE_SYNCED = 'synced';
    public const STATE_REMOTE = 'remote';

    public function __construct(ApiUvsService $api)
    {
        $this->api = $api;
    }

    /**
     * SYNC:
     * - Push: nur dirty/neu (optional: nur bestimmte local person ids)
     * - Pull: nur die betreffenden Teilnehmer (optional: nur bestimmte local person ids)
     */
    public function syncToRemote(CourseDay $day, ?array $onlyLocalPersonIds = null): bool
    {
        if (! $this->isSyncable($day)) {
            Log::warning('CourseDayAttendanceSyncService.syncToRemote: day nicht syncbar.', [
                'day_id'    => $day->id,
                'course_id' => $day->course_id,
            ]);
            return false;
        }

        // Push: nur dirty/neu (+ optional nur 1 Person)
        [$changes, $localKeysForPush] = $this->mapAttendanceToUvsChangesDirtyOnly($day, $onlyLocalPersonIds);

        // Pull: optional nur die eine Person
        $teilnehmerIds = $this->collectTeilnehmerIds($day, $onlyLocalPersonIds);

        if (empty($teilnehmerIds) && empty($changes)) {
            return true;
        }

        $payload = [
            'termin_id'      => (string) $day->course->termin_id,
            'date'           => $day->date->toDateString(),
            'teilnehmer_ids' => $teilnehmerIds,
            'changes'        => $changes,
        ];

        $response = $this->api->request('POST', self::ENDPOINT_SYNC, $payload, []);

        if (! empty($response['ok'])) {
            $this->markRowsSyncedAfterPush($day, $localKeysForPush);
            $this->applySyncResponseSafe($day, $response, $onlyLocalPersonIds);
            return true;
        }

        Log::error('CourseDayAttendanceSyncService.syncToRemote: UVS-Response nicht ok.', [
            'day_id'   => $day->id,
            'response' => $response,
        ]);

        return false;
    }

    /**
     * LOAD (Pull-only, UVS ist Master):
     * - Optional: nur bestimmte local person ids laden.
     */
    public function loadFromRemote(CourseDay $day, ?array $onlyLocalPersonIds = null): bool
    {
        if (! $this->isSyncable($day)) {
            Log::warning('CourseDayAttendanceSyncService.loadFromRemote: day nicht loadbar.', [
                'day_id'    => $day->id,
                'course_id' => $day->course_id,
            ]);
            return false;
        }

        $teilnehmerIds = $this->collectTeilnehmerIds($day, $onlyLocalPersonIds);

        if (empty($teilnehmerIds)) {
            return true;
        }

        $payload = [
            'termin_id'      => (string) $day->course->termin_id,
            'date'           => $day->date->toDateString(),
            'teilnehmer_ids' => $teilnehmerIds,
        ];

        $response = $this->api->request('POST', self::ENDPOINT_LOAD, $payload, []);

        if (! empty($response['ok'])) {
            $this->applyLoadResponseHard($day, $response, $onlyLocalPersonIds);
            return true;
        }

        Log::error('CourseDayAttendanceSyncService.loadFromRemote: UVS-Response nicht ok.', [
            'day_id'   => $day->id,
            'response' => $response,
        ]);

        return false;
    }

    /* -------------------------------------------------------------------------
     | Core helpers (FILTER-FÄHIG)
     * ---------------------------------------------------------------------- */

    protected function isSyncable(CourseDay $day): bool
    {
        return (bool) ($day->course && $day->course->termin_id && $day->date);
    }

    /**
     * Sammle remote teilnehmer_ids:
     * - Standard: alle Kursteilnehmer
     * - Optional: nur Personen in $onlyLocalPersonIds
     */
    protected function collectTeilnehmerIds(CourseDay $day, ?array $onlyLocalPersonIds = null): array
    {
        $participants = $day->course->participants ?? collect();

        if ($participants->isEmpty()) {
            return [];
        }

        if (is_array($onlyLocalPersonIds) && ! empty($onlyLocalPersonIds)) {
            $only = array_map('intval', $onlyLocalPersonIds);
            $participants = $participants->filter(fn ($p) => in_array((int) $p->id, $only, true));
        }

        return $participants
            ->map(fn ($p) => (string) $p->teilnehmer_id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * PUSH Changes bauen:
     * - Standard: alle dirty/neu
     * - Optional: nur local ids in $onlyLocalPersonIds
     */
    protected function mapAttendanceToUvsChangesDirtyOnly(CourseDay $day, ?array $onlyLocalPersonIds = null): array
    {
        $attendance   = $day->attendance_data ?? [];
        $participants = $attendance['participants'] ?? [];

        if (! is_array($participants) || empty($participants)) {
            return [[], []];
        }

        $only = null;
        if (is_array($onlyLocalPersonIds) && ! empty($onlyLocalPersonIds)) {
            $only = array_map('intval', $onlyLocalPersonIds);
        }

        $terminId  = (string) $day->course->termin_id;
        $date      = $day->date->toDateString();
        $tutorName = $day->course->tutor
            ? trim(($day->course->tutor->vorname ?? '') . ' ' . ($day->course->tutor->nachname ?? '') . ' (Schulnetz)')
            : 'Baustein-Dozent (Schulnetz)';

        [$courseStart, $courseEnd, $totalMinutes] = $this->computeCourseTimes($day);

        $localIds = array_keys($participants);

        if ($only !== null) {
            $localIds = array_values(array_intersect(array_map('intval', $localIds), $only));
        }

        if (empty($localIds)) {
            return [[], []];
        }

        $persons = Person::whereIn('id', $localIds)->get()->keyBy('id');

        $changes = [];
        $pushedLocalPersonIds = [];

        foreach ($participants as $localPersonId => $row) {
            $localPersonId = (int) $localPersonId;

            if ($only !== null && ! in_array($localPersonId, $only, true)) {
                continue;
            }

            /** @var Person|null $person */
            $person = $persons->get($localPersonId);
            if (! $person || ! $person->teilnehmer_id) {
                continue;
            }

            $teilnehmerId = (string) $person->teilnehmer_id;
            $institutId   = (int) ($person->institut_id ?? ($day->course->institut_id ?? 0));

            $state     = $row['state'] ?? null;
            $hasRemote = ! empty($row['src_api_id']);

            // dirty wenn: draft|dirty oder ohne remote id (neu)
            $isDirty = in_array($state, [self::STATE_DRAFT, self::STATE_DIRTY], true) || ! $hasRemote;
            if (! $isDirty) {
                continue;
            }

            $present   = (bool) ($row['present'] ?? false);
            $excused   = (bool) ($row['excused'] ?? false);
            $lateMin   = (int)  ($row['late_minutes'] ?? 0);
            $leftEarly = (int)  ($row['left_early_minutes'] ?? 0);

            $isFullyPresent = $present && ! $excused && $lateMin === 0 && $leftEarly === 0;

            // Vollständig anwesend:
            // - wenn remote existiert -> delete
            // - wenn remote nie existierte -> nichts senden
            if ($isFullyPresent) {
                if ($hasRemote) {
                    $tnFehltageId = $teilnehmerId . '-' . $terminId;

                    $changes[] = [
                        'tn_fehltage_id' => (string) $tnFehltageId,
                        'teilnehmer_id'  => (string) $teilnehmerId,
                        'institut_id'    => (int) $institutId,
                        'termin_id'      => (string) $terminId,
                        'date'           => (string) $date,
                        'fehl_grund'     => '',
                        'fehl_bem'       => '',
                        'gekommen'       => '00:00',
                        'gegangen'       => '00:00',
                        'fehl_std'       => 0.0,
                        'status'         => 1,
                        'upd_user'       => (string) $tutorName,
                        'action'         => 'delete',
                    ];

                    $pushedLocalPersonIds[] = $localPersonId;
                }

                continue;
            }

            // Fehlzeit/Teilzeit
            $fehlStd = 0.0;

            if (! $present) {
                $fehlStd = (float) ($day->std ?? 0);
            }

            // Zeiten
            if (! $present) {
                $gekommen = '00:00';
                $gegangen = '00:00';
            } else {
                $gekommen = $this->resolveTimeForPush($row, 'arrived_at', 'in', $courseStart);
                $gegangen = $this->resolveTimeForPush($row, 'left_at', 'out', $courseEnd);
            }

            if ($present && ($leftEarly > 0 || $lateMin > 0) && $totalMinutes > 0) {
                $fehlStd = round(($lateMin + $leftEarly) / 60, 2);
            }

            $fehlGrund = $this->mapReasonCode($present, $excused, $lateMin, $leftEarly);
            $note      = $this->normalizeNote($row['note'] ?? null);

            $tnFehltageId = $teilnehmerId . '-' . $terminId;

            $changes[] = [
                'tn_fehltage_id' => (string) $tnFehltageId,
                'teilnehmer_id'  => (string) $teilnehmerId,
                'institut_id'    => (int) $institutId,
                'termin_id'      => (string) $terminId,
                'date'           => (string) $date,
                'fehl_grund'     => (string) $fehlGrund,
                'fehl_bem'       => (string) $note,
                'gekommen'       => (string) $gekommen,
                'gegangen'       => (string) $gegangen,
                'fehl_std'       => (float) $fehlStd,
                'status'         => 1,
                'upd_user'       => (string) $tutorName,
                'action'         => 'update',
            ];

            $pushedLocalPersonIds[] = $localPersonId;
        }

        $pushedLocalPersonIds = array_values(array_unique($pushedLocalPersonIds));

        return [$changes, $pushedLocalPersonIds];
    }

    /**
     * Nach PUSH: nur die gepushten lokalen ids als synced markieren.
     */
    protected function markRowsSyncedAfterPush(CourseDay $day, array $localPersonIds): void
    {
        if (empty($localPersonIds)) {
            return;
        }

        $attendance   = $day->attendance_data ?? [];
        $participants = $attendance['participants'] ?? [];

        if (! is_array($participants) || empty($participants)) {
            return;
        }

        $now = Carbon::now()->toDateTimeString();

        foreach ($localPersonIds as $pid) {
            $pid = (int) $pid;

            if (! isset($participants[$pid]) || ! is_array($participants[$pid])) {
                continue;
            }

            $participants[$pid]['state']      = self::STATE_SYNCED;
            $participants[$pid]['updated_at'] = $now;
        }

        $attendance['participants'] = $participants;

        $day->attendance_data           = $attendance;
        $day->attendance_updated_at     = Carbon::now();
        $day->attendance_last_synced_at = Carbon::now();
        $day->saveQuietly();
    }

    /* -------------------------------------------------------------------------
     | Pull apply (optional gefiltert)
     * ---------------------------------------------------------------------- */

    protected function applySyncResponseSafe(CourseDay $day, array $response, ?array $onlyLocalPersonIds = null): void
    {
        $only = null;
        if (is_array($onlyLocalPersonIds) && ! empty($onlyLocalPersonIds)) {
            $only = array_map('intval', $onlyLocalPersonIds);
        }

        $outerData = $response['data'] ?? [];
        $innerData = $outerData['data'] ?? $outerData;

        $pulled  = $innerData['pulled'] ?? null;
        $items   = (is_array($pulled) && ! empty($pulled['items'])) ? $pulled['items'] : [];

        $pushed  = $innerData['pushed'] ?? null;
        $results = is_array($pushed) ? ($pushed['results'] ?? []) : [];

        if (empty($items) && empty($results)) {
            return;
        }

        $attendance   = $day->attendance_data ?? [];
        $participants = $attendance['participants'] ?? [];
        if (! is_array($participants)) $participants = [];

        $targetLocalIds = array_map('intval', array_keys($participants));
        if ($only !== null) {
            $targetLocalIds = array_values(array_intersect($targetLocalIds, $only));
        }

        if (empty($targetLocalIds)) {
            return;
        }

        $persons = Person::whereIn('id', $targetLocalIds)->get()->keyBy('id');

        $tnToLocal = [];
        foreach ($targetLocalIds as $localId) {
            $p = $persons->get($localId);
            if ($p && ! empty($p->teilnehmer_id)) {
                $tnToLocal[$p->teilnehmer_id][] = $localId;
            }
        }

        $now    = Carbon::now();
        $nowStr = $now->toDateTimeString();
        $dayIso = $day->date?->toDateString();

        [$courseStart, $courseEnd, $totalMinutes] = $this->computeCourseTimes($day);

        // 1) pushed.results: src_api_id/state aktualisieren (dirty bleibt dirty)
        foreach ($results as $result) {
            $uid          = $result['uid'] ?? null;
            $teilnehmerId = $result['teilnehmer_id'] ?? null;
            $action       = $result['action'] ?? null;

            if (! $teilnehmerId || empty($tnToLocal[$teilnehmerId])) continue;

            foreach ($tnToLocal[$teilnehmerId] as $localPersonId) {
                $row = $participants[$localPersonId] ?? [];

                $state  = $row['state'] ?? null;
                $isDirty = in_array($state, [self::STATE_DRAFT, self::STATE_DIRTY], true);
                if ($isDirty) continue;

                if ($action === 'deleted') {
                    $row['src_api_id']         = null;
                    $row['state']              = null;
                    $row['late_minutes']       = 0;
                    $row['left_early_minutes'] = 0;
                    $row['excused']            = false;
                } else {
                    if ($uid) $row['src_api_id'] = $uid;
                    $row['state'] = self::STATE_SYNCED;
                }

                $row['updated_at'] = $nowStr;
                $participants[$localPersonId] = $row;
            }
        }

        // 2) pulled.items übernehmen (aber nicht über dirty/draft)
        foreach ($items as $item) {
            $uid          = $item['uid']            ?? null;
            $teilnehmerId = $item['teilnehmer_id']  ?? null;
            $fehlDatumIso = $item['fehl_datum_iso'] ?? null;

            if (! $teilnehmerId || empty($tnToLocal[$teilnehmerId])) continue;
            if ($dayIso && $fehlDatumIso && $fehlDatumIso !== $dayIso) continue;

            $fehlStdRemote   = (float) ($item['fehl_std'] ?? 0.0);
            $fehlGrundRemote = (string) ($item['fehl_grund'] ?? '');
            $fehlBemRemote   = trim((string) ($item['fehl_bem'] ?? ''));
            $gekommenRemote  = $this->normalizeRemoteTime($item['gekommen'] ?? null);
            $gegangenRemote  = $this->normalizeRemoteTime($item['gegangen'] ?? null);

            $gekommenCarbon = $this->parseTimeOnDay($day->date, $gekommenRemote);
            $gegangenCarbon = $this->parseTimeOnDay($day->date, $gegangenRemote);

            foreach ($tnToLocal[$teilnehmerId] as $localPersonId) {
                $row = $participants[$localPersonId] ?? [];

                $state  = $row['state'] ?? null;
                $isDirty = in_array($state, [self::STATE_DRAFT, self::STATE_DIRTY], true);
                if ($isDirty) continue;

                $row['src_api_id'] = $uid;
                $row['state']      = self::STATE_SYNCED;
                $row['updated_at'] = $nowStr;

                if ($fehlBemRemote !== '') $row['note'] = $fehlBemRemote;

                if ($gekommenRemote !== null) $row['arrived_at'] = $gekommenRemote;
                if ($gegangenRemote !== null) $row['left_at']    = $gegangenRemote;

                $this->hydrateLateEarlyMinutes($row, $courseStart, $courseEnd, $gekommenCarbon, $gegangenCarbon);

                if ($totalMinutes > 0) {
                    $totalHours     = $totalMinutes / 60.0;
                    $row['present'] = $fehlStdRemote < ($totalHours - 0.01);
                } else {
                    $row['present'] = ($gekommenCarbon || $gegangenCarbon) ? true : (bool) ($row['present'] ?? false);
                }

                $reverse = $this->reverseMapReasonCode($fehlGrundRemote);
                if ($reverse['excused'] !== null) $row['excused'] = $reverse['excused'];
                if ($reverse['present'] !== null) $row['present'] = $reverse['present'];

                $participants[$localPersonId] = $row;
            }
        }

        $attendance['participants'] = $participants;

        $day->attendance_data           = $attendance;
        $day->attendance_updated_at     = $now;
        $day->attendance_last_synced_at = $now;
        $day->saveQuietly();
    }

    protected function applyLoadResponseHard(CourseDay $day, array $response, ?array $onlyLocalPersonIds = null): void
    {
        $outerData = $response['data'] ?? [];
        $innerData = $outerData['data'] ?? $outerData;

        $pulled = $innerData['pulled'] ?? null;
        $items  = (is_array($pulled) && ! empty($pulled['items'])) ? $pulled['items'] : [];

        if (empty($items)) return;

        $only = null;
        if (is_array($onlyLocalPersonIds) && ! empty($onlyLocalPersonIds)) {
            $only = array_map('intval', $onlyLocalPersonIds);
        }

        $attendance   = $day->attendance_data ?? [];
        $participants = $attendance['participants'] ?? [];
        if (! is_array($participants)) $participants = [];

        $now    = Carbon::now();
        $nowStr = $now->toDateTimeString();
        $dayIso = $day->date?->toDateString();

        [$courseStart, $courseEnd, $totalMinutes] = $this->computeCourseTimes($day);

        $targetLocalIds = array_map('intval', array_keys($participants));
        if ($only !== null) $targetLocalIds = array_values(array_intersect($targetLocalIds, $only));

        if (empty($targetLocalIds)) return;

        $persons = Person::whereIn('id', $targetLocalIds)->get()->keyBy('id');

        $tnToLocal = [];
        foreach ($targetLocalIds as $localId) {
            $p = $persons->get($localId);
            if ($p && ! empty($p->teilnehmer_id)) {
                $tnToLocal[$p->teilnehmer_id][] = $localId;
            }
        }

        foreach ($items as $item) {
            $uid          = $item['uid']            ?? null;
            $teilnehmerId = $item['teilnehmer_id']  ?? null;
            $fehlDatumIso = $item['fehl_datum_iso'] ?? null;

            if (! $teilnehmerId || empty($tnToLocal[$teilnehmerId])) continue;
            if ($dayIso && $fehlDatumIso && $fehlDatumIso !== $dayIso) continue;

            $fehlStdRemote   = (float) ($item['fehl_std'] ?? 0.0);
            $fehlGrundRemote = (string) ($item['fehl_grund'] ?? '');
            $fehlBemRemote   = trim((string) ($item['fehl_bem'] ?? ''));
            $gekommenRemote  = $this->normalizeRemoteTime($item['gekommen'] ?? null);
            $gegangenRemote  = $this->normalizeRemoteTime($item['gegangen'] ?? null);

            $gekommenCarbon = $this->parseTimeOnDay($day->date, $gekommenRemote);
            $gegangenCarbon = $this->parseTimeOnDay($day->date, $gegangenRemote);

            foreach ($tnToLocal[$teilnehmerId] as $localPersonId) {
                $row = $participants[$localPersonId] ?? [];

                $row['src_api_id'] = $uid;
                $row['state']      = self::STATE_REMOTE;
                $row['updated_at'] = $nowStr;

                $row['note'] = $fehlBemRemote !== '' ? $fehlBemRemote : ($row['note'] ?? null);

                // ✅ "00:00" nicht als echte Zeit speichern
                $row['arrived_at'] = $gekommenRemote !== null ? $gekommenRemote : null;
                $row['left_at']    = $gegangenRemote !== null ? $gegangenRemote : null;

                // ✅ Minuten NICHT kaputt überschreiben, wenn remote nichts liefert
                $this->hydrateLateEarlyMinutes($row, $courseStart, $courseEnd, $gekommenCarbon, $gegangenCarbon);

                if ($totalMinutes > 0) {
                    $totalHours     = $totalMinutes / 60.0;
                    $row['present'] = $fehlStdRemote < ($totalHours - 0.01);
                } else {
                    $row['present'] = ($gekommenCarbon || $gegangenCarbon) ? true : (bool) ($row['present'] ?? false);
                }

                $reverse = $this->reverseMapReasonCode($fehlGrundRemote);
                if ($reverse['excused'] !== null) $row['excused'] = $reverse['excused'];
                if ($reverse['present'] !== null) $row['present'] = $reverse['present'];

                $participants[$localPersonId] = $row;
            }
        }

        $attendance['participants'] = $participants;

        $day->attendance_data           = $attendance;
        $day->attendance_updated_at     = $now;
        $day->attendance_last_synced_at = $now;
        $day->saveQuietly();
    }

    /* -------------------------------------------------------------------------
     | Minute hydration (wichtig für "Load überschreibt nicht kaputt")
     * ---------------------------------------------------------------------- */

    /**
     * Behalte bestehende Minuten, falls remote keine Zeit liefert.
     * Rechne nur neu, wenn die jeweilige Zeit vorhanden ist.
     */
    protected function hydrateLateEarlyMinutes(array &$row, ?Carbon $courseStart, ?Carbon $courseEnd, ?Carbon $gekommenCarbon, ?Carbon $gegangenCarbon): void
    {
        $lateMinutes      = (int) ($row['late_minutes'] ?? 0);
        $leftEarlyMinutes = (int) ($row['left_early_minutes'] ?? 0);

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
    }

    /**
     * Remote-Zeit normalisieren:
     * - '' / null / '00:00' / '00:00:00' => null
     * - 'HH:MM:SS' => 'HH:MM'
     * - 'HH:MM' bleibt
     */
    protected function normalizeRemoteTime(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));

        if ($v === '' || $v === '00:00' || $v === '00:00:00' || $v === '0:00') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
            return substr($v, 0, 5);
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $v)) {
            return str_pad(explode(':', $v)[0], 2, '0', STR_PAD_LEFT) . ':' . explode(':', $v)[1];
        }

        // fallback: parsebare Datetime?
        try {
            return Carbon::parse($v)->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* -------------------------------------------------------------------------
     | Small utils (wie gehabt)
     * ---------------------------------------------------------------------- */

    protected function mapReasonCode(bool $present, bool $excused, int $lateMinutes, int $leftEarlyMinutes): string
    {
        if (! $present) return $excused ? 'E' : 'UE';
        if ($lateMinutes > 0 || $leftEarlyMinutes > 0) return 'TA';
        return 'E';
    }

    protected function reverseMapReasonCode(string $fehlGrund): array
    {
        $code = strtoupper(trim($fehlGrund));

        return match ($code) {
            'E', 'K'  => ['present' => null, 'excused' => true],
            'UE', 'F' => ['present' => false, 'excused' => false],
            'TA', 'T' => ['present' => true, 'excused' => false],
            default   => ['present' => null, 'excused' => null],
        };
    }

    protected function normalizeNote(mixed $rawNote): string
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

    protected function computeCourseTimes(CourseDay $day): array
    {
        $courseStart  = null;
        $courseEnd    = null;
        $totalMinutes = 0;

        $totalHours = (float) ($day->std ?? 0.0);
        $date       = $day->date;

        if (! $date || $totalHours <= 0) {
            return [null, null, 0];
        }

        $totalMinutes = (int) round($totalHours * 60);
        $rawStart     = $day->start_time;

        try {
            if ($rawStart instanceof Carbon) {
                $courseStart = (clone $rawStart);
            } elseif (is_string($rawStart) && trim($rawStart) !== '') {
                $startStr = trim($rawStart);
                $courseStart = preg_match('/^\d{1,2}:\d{2}$/', $startStr)
                    ? Carbon::parse($date->toDateString() . ' ' . $startStr)
                    : Carbon::parse($startStr);
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
        if (! $date || ! $time) return null;

        $time = trim($time);
        if ($time === '' || $time === '00:00' || $time === '0:00') return null;

        try {
            return Carbon::parse($date->toDateString() . ' ' . $time);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function resolveTimeForPush(array $row, string $directKey, string $timestampKey, ?Carbon $fallback): string
    {
        if (! empty($row[$directKey]) && is_string($row[$directKey])) {
            return $this->normalizeTimeString($row[$directKey]);
        }

        $ts = $row['timestamps'][$timestampKey] ?? null;
        if (! empty($ts) && is_string($ts)) {
            try {
                return Carbon::parse($ts)->format('H:i');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $fallback instanceof Carbon ? $fallback->format('H:i') : '00:00';
    }

    protected function normalizeTimeString(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time;
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable $e) {
            return '00:00';
        }
    }

}
