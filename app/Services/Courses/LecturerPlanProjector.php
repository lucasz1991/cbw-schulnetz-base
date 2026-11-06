<?php

namespace App\Services\Courses;

use App\Models\Course;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LecturerPlanProjector
{
    /** Only the new additive projection is changed; legacy course data remains compatible. */
    public static function apply(Course $course, ?array $source): void
    {
        if ($source === null || ($source['status'] ?? 'legacy') === 'legacy') {
            // An old API or a partial rollout must never erase an accepted history.
            return;
        }
        DB::transaction(function () use ($course, $source) {
            $fresh = Course::query()->lockForUpdate()->findOrFail($course->id);
            $revision = filter_var($source['revision'] ?? null, FILTER_VALIDATE_INT);
            try {
                if ($revision === false || $revision < 1) {
                    throw new RuntimeException('Ungültige Dozenteneinsatz-Revision.');
                }
                if ($revision < (int) $fresh->lecturer_plan_revision) {
                    return;
                }
                if (($source['version'] ?? null) !== 1 || ($source['status'] ?? null) !== 'ready') {
                    throw new RuntimeException('Die Dozentenzuordnung muss im UVS geklärt werden.');
                }
                $rows = $source['assignments'] ?? null;
                if (! is_array($rows) || ! $rows) {
                    throw new RuntimeException('Der Einsatzplan ist unvollständig.');
                }
                $normalized = [];
                $seen = [];
                $next = $fresh->planned_start_date?->toDateString();
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        throw new RuntimeException('Ungültiger Dozenteneinsatz.');
                    }
                    $id = (string) ($row['assignment_id'] ?? $row['einsatz_id'] ?? '');
                    $start = self::date($row['starts_on'] ?? $row['beginn'] ?? null);
                    $end = self::date($row['ends_on'] ?? $row['ende'] ?? null);
                    if ($id === '' || strlen($id) > 64 || isset($seen[$id]) || $start > $end || ($next && $start !== $next)) {
                        throw new RuntimeException('Der Einsatzplan enthält eine Lücke oder Überlappung.');
                    }
                    $person = Person::query()->where('person_id', (string) ($row['person_id'] ?? ''))->first();
                    if (! $person) {
                        throw new RuntimeException('Eine Dozentenperson wurde noch nicht übernommen.');
                    }
                    // Compatibility field names: both identify the existing
                    // ma_u_kla course/fee agreement, never a separate contract.
                    $contractId = $row['contract_id'] ?? null;
                    $legacyId = $row['legacy_assignment_uid'] ?? null;
                    if ((! is_string($contractId) && ! is_int($contractId)) || (! is_string($legacyId) && ! is_int($legacyId))
                        || ! preg_match('/^[1-9]\d{0,19}$/D', (string) $contractId) || (string) $contractId !== (string) $legacyId) {
                        throw new RuntimeException('Der Einsatz benötigt eine eindeutige bestehende Kursvereinbarung.');
                    }
                    $seen[$id] = true;
                    $normalized[] = ['assignment_id' => $id, 'person_id' => (int) $person->id, 'source_person_id' => (string) $person->person_id,
                        'mitarbeiter_id' => (string) ($row['mitarbeiter_id'] ?? ''), 'contract_id' => (string) $contractId,
                        'legacy_assignment_uid' => (string) $legacyId, 'starts_on' => $start, 'ends_on' => $end,
                        'reason' => $row['reason'] ?? null, 'changed_by' => $row['changed_by'] ?? null, 'changed_at' => $row['changed_at'] ?? null];
                    $next = Carbon::parse($end)->addDay()->toDateString();
                }
                if ($fresh->planned_end_date && $normalized[count($normalized) - 1]['ends_on'] !== $fresh->planned_end_date->toDateString()) {
                    throw new RuntimeException('Der Einsatzplan passt nicht zum Kursende.');
                }
                if (! empty($source['closing_assignment_id']) && ! isset($seen[$source['closing_assignment_id']])) {
                    throw new RuntimeException('Der Abschlusseinsatz ist nicht im Plan enthalten.');
                }
                $completion = $source['completion_at'] ?? null;
                if (! is_string($completion) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $completion)) {
                    throw new RuntimeException('Der Kursabschluss benötigt einen eindeutigen Zeitpunkt.');
                }
                try {
                    $completionDay = Carbon::parse($completion)->setTimezone('Europe/Berlin')->toDateString();
                } catch (\Throwable $e) {
                    throw new RuntimeException('Ungültiger Kursabschlusszeitpunkt.');
                }
                $closing = collect($normalized)->firstWhere('assignment_id', $source['closing_assignment_id'] ?? null);
                if (! $closing || $completionDay < $closing['starts_on'] || $completionDay > $closing['ends_on']) {
                    throw new RuntimeException('Der Abschlusseinsatz passt nicht zum letzten Unterrichtstag.');
                }
                if ($fresh->lecturer_closing_assignment_id) {
                    $old = collect(CourseLecturerAccess::assignments($fresh))->firstWhere('assignment_id', $fresh->lecturer_closing_assignment_id);
                    $candidate = collect($normalized)->firstWhere('assignment_id', $fresh->lecturer_closing_assignment_id);
                    $keys = ['person_id', 'contract_id', 'mitarbeiter_id', 'starts_on', 'ends_on'];
                    if (! $old || ! $candidate || array_map(fn ($key) => $old[$key] ?? null, $keys) !== array_map(fn ($key) => $candidate[$key] ?? null, $keys)) {
                        throw new RuntimeException('Die Änderung betrifft einen bereits festgehaltenen Kursabschluss.');
                    }
                    if (data_get($fresh->lecturer_plan, 'completion_at') !== ($source['completion_at'] ?? null)) {
                        throw new RuntimeException('Der Abschlusszeitpunkt eines bewerteten Kurses wurde verändert.');
                    }
                    if (($source['closing_assignment_id'] ?? null) !== data_get($fresh->lecturer_plan, 'closing_assignment_id')) {
                        throw new RuntimeException('Der bereits festgehaltene Abschlusseinsatz wurde verändert.');
                    }
                }
                $plan = ['version' => 1, 'status' => 'ready', 'assignments' => $normalized, 'contracts' => $source['contracts'] ?? [],
                    'person_ids' => array_values(array_unique(array_column($normalized, 'person_id'))), 'completion_at' => $source['completion_at'] ?? null,
                    'closing_assignment_id' => $source['closing_assignment_id'] ?? null, 'history' => $source['history'] ?? []];
                $previous = array_intersect_key($fresh->lecturer_plan ?? [], $plan);
                $previous['status'] = 'ready';
                if ($revision === (int) $fresh->lecturer_plan_revision && (int) $fresh->lecturer_plan_revision > 0
                    && ! self::equivalent($previous, $plan)) {
                    throw new RuntimeException('Widersprüchlicher Inhalt derselben Dozentenrevision.');
                }
                $fresh->forceFill(['lecturer_plan' => $plan, 'lecturer_plan_revision' => $revision, 'lecturer_plan_synced_at' => now()]);
                $current = CourseLecturerAccess::currentAssignment($fresh);
                if ($current) {
                    $fresh->primary_tutor_person_id = $current['person_id'];
                }
                $fresh->save();
            } catch (RuntimeException$e) {
                $plan = $fresh->lecturer_plan ?: ['version' => 1, 'assignments' => [], 'person_ids' => []];
                $plan['status'] = 'conflict';
                $plan['conflict'] = $e->getMessage();
                $plan['incoming_revision'] = $revision;
                $fresh->forceFill(['lecturer_plan' => $plan, 'lecturer_plan_synced_at' => now()])->save();
            }
            $course->setRawAttributes($fresh->getAttributes(), true);
            $course->unsetRelation('tutor');
        });
    }

    private static function date($value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new RuntimeException('Ungültiges Einsatzdatum.');
        }
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, 'Europe/Berlin');
        } catch (\Throwable $e) {
            throw new RuntimeException('Ungültiges Einsatzdatum.');
        }
        if (! $date || $date->toDateString() !== $value) {
            throw new RuntimeException('Ungültiges Einsatzdatum.');
        }

        return $value;
    }

    private static function equivalent(array $left, array $right): bool
    {
        $canonical = function ($value) use (&$canonical) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as $key => $item) {
                $value[$key] = $canonical($item);
            }

            return $value;
        };

        return $canonical($left) === $canonical($right);
    }
}
