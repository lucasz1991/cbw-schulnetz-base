<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Course;
use App\Models\CourseDay;
use App\Models\CourseParticipantEnrollment as Enrollment;
use App\Models\Person;
use App\Services\Helper\DateParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Synthetisiert lokale Ferien-Kurse aus den FERI-Bausteinen des Qualiprogramms.
 *
 * FERI-Bloecke haben in UVS keine Klassenzuordnung (klassen_id = null) und
 * laufen daher am regulaeren Kurs-Sync (CheckPersonsCourses ->
 * CreateOrUpdateCourse) vorbei. Damit Umschueler auch fuer Ferienwochen
 * Berichtsheft-Eintraege schreiben koennen, legt dieser Job pro
 * Ferienzeitraum einen lokalen Course (type 'ferien', synthetische
 * klassen_id 'FERI-<beginn>-<ende>') mit Werktags-CourseDays (Mo-Fr) und
 * einem Enrollment der Person an. Siehe MODEL_COORDINATION.md, Aufgabe 3.
 */
class SyncPersonFerienCourses implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const COURSE_TYPE = 'ferien';
    public const KLASSEN_ID_PREFIX = 'FERI-';

    public int $tries = 3;
    public $backoff = [10, 60, 180];

    /** Gleiches Zeitfenster wie CheckPersonsCourses */
    private const PAST_YEARS   = 2;
    private const FUTURE_YEARS = 1;

    public function __construct(public int $personPk) {}

    public function uniqueId(): string
    {
        return 'sync-person-ferien-courses:' . $this->personPk;
    }

    public function handle(): void
    {
        $person = Person::find($this->personPk);
        if (! $person) {
            return;
        }

        $pd = $person->programdata;
        if (empty($pd) || ! is_array($pd)) {
            return;
        }

        $validKlassenIds = [];

        foreach ($this->ferienBlocks($pd) as $block) {
            try {
                $klassenId = $this->syncFerienCourse($person, $pd, $block);
                if ($klassenId) {
                    $validKlassenIds[] = $klassenId;
                }
            } catch (\Throwable $e) {
                Log::error('SyncPersonFerienCourses: Block fehlgeschlagen', [
                    'person_pk' => $person->id,
                    'block'     => $block,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $this->cleanupStaleEnrollments($person, $validKlassenIds);
    }

    /**
     * FERI-Bloecke des Qualiprogramms mit gueltigen Daten im Sync-Fenster.
     *
     * @return list<array>
     */
    protected function ferienBlocks(array $programdata): array
    {
        $from = now()->startOfDay()->subYears(self::PAST_YEARS);
        $to   = now()->endOfDay()->addYears(self::FUTURE_YEARS);

        $blocks = [];

        foreach ((array) ($programdata['tn_baust'] ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (mb_strtoupper(trim((string) ($block['kurzbez'] ?? ''))) !== 'FERI') {
                continue;
            }

            $start = $this->parseDate($block['beginn_baustein'] ?? null);
            $end   = $this->parseDate($block['ende_baustein'] ?? null);

            if (! $start || ! $end || $end->lt($start)) {
                continue;
            }
            if ($start->lt($from) || $start->gt($to)) {
                continue;
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * Legt Course + CourseDays + Enrollment fuer einen Ferienblock an bzw.
     * aktualisiert sie. Gibt die synthetische klassen_id zurueck.
     */
    protected function syncFerienCourse(Person $person, array $programdata, array $block): ?string
    {
        $start = $this->parseDate($block['beginn_baustein'] ?? null);
        $end   = $this->parseDate($block['ende_baustein'] ?? null);

        if (! $start || ! $end) {
            return null;
        }

        // Kohorten uebergreifend stabil: gleicher Zeitraum => gleicher Kurs.
        $klassenId = self::KLASSEN_ID_PREFIX . $start->format('Ymd') . '-' . $end->format('Ymd');

        $course = Course::withTrashed()->firstOrNew(['klassen_id' => $klassenId]);

        if ($course->exists && $course->trashed()) {
            $course->restore();
        }

        $course->fill([
            'title'              => 'Ferien',
            'description'        => $block['langbez'] ?? 'FERI - Ferien',
            'planned_start_date' => $start->toDateString(),
            'planned_end_date'   => $end->toDateString(),
            'type'               => self::COURSE_TYPE,
            'is_active'          => true,
            'source_snapshot'    => $block,
            'source_last_upd'    => now(),
        ]);
        $course->save();

        $this->syncFerienDays($course, $start, $end);
        $this->syncEnrollment($course, $person, $programdata, $block, $klassenId);

        return $klassenId;
    }

    /** Werktage (Mo-Fr) im Ferienzeitraum als CourseDays anlegen. */
    protected function syncFerienDays(Course $course, Carbon $start, Carbon $end): void
    {
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor->lte($endDay)) {
            if ($cursor->isoWeekday() <= 5) {
                $day = CourseDay::withTrashed()->firstOrNew([
                    'course_id' => $course->id,
                    'date'      => $cursor->toDateString(),
                ]);

                if ($day->exists && $day->trashed()) {
                    $day->restore();
                }

                if (! $day->exists || $day->type !== 'FERI' || $day->topic !== 'Ferien') {
                    $day->type  = 'FERI';
                    $day->topic = 'Ferien';
                    $day->save();
                }
            }

            $cursor->addDay();
        }
    }

    protected function syncEnrollment(Course $course, Person $person, array $programdata, array $block, string $klassenId): void
    {
        $teilnehmerId = trim((string) ($programdata['teilnehmer_id'] ?? ''));
        if ($teilnehmerId === '') {
            $teilnehmerId = trim((string) ($person->teilnehmer_id ?? ''));
        }

        $enrollment = Enrollment::withTrashed()->firstOrNew([
            'course_id' => $course->id,
            'person_id' => $person->id,
        ]);

        if ($enrollment->exists && $enrollment->trashed()) {
            $enrollment->restore();
        }

        $enrollment->fill([
            'teilnehmer_id'   => $teilnehmerId !== '' ? $teilnehmerId : $enrollment->teilnehmer_id,
            'tn_baustein_id'  => $block['tn_baustein_id'] ?? $enrollment->tn_baustein_id,
            'baustein_id'     => $block['baustein_id'] ?? $enrollment->baustein_id,
            'klassen_id'      => $klassenId,
            'vtz'             => $programdata['vtz'] ?? $enrollment->vtz,
            'kurzbez_ba'      => 'FERI',
            'is_active'       => true,
            'source_snapshot' => $block,
            'source_last_upd' => now(),
            'last_synced_at'  => now(),
        ]);

        $enrollment->save();
    }

    /**
     * FERI-Enrollments der Person soft-deleten, deren Zeitraum nicht mehr im
     * aktuellen Qualiprogramm vorkommt (nur innerhalb des Sync-Fensters —
     * Historie ausserhalb des Fensters bleibt unangetastet).
     */
    protected function cleanupStaleEnrollments(Person $person, array $validKlassenIds): void
    {
        $windowStart = now()->startOfDay()->subYears(self::PAST_YEARS)->toDateString();

        $stale = Enrollment::query()
            ->where('person_id', $person->id)
            ->where('kurzbez_ba', 'FERI')
            ->whereNull('deleted_at')
            ->whereNotIn('klassen_id', $validKlassenIds ?: ['__none__'])
            ->whereHas('course', fn ($q) => $q
                ->where('type', self::COURSE_TYPE)
                ->whereDate('planned_start_date', '>=', $windowStart))
            ->get();

        if ($stale->isNotEmpty()) {
            $stale->each->delete();
            Log::info('SyncPersonFerienCourses: veraltete FERI-Enrollments soft-deleted', [
                'person_pk' => $person->id,
                'count'     => $stale->count(),
            ]);
        }
    }

    /** UVS liefert Datumswerte als "YYYY/MM/DD". */
    protected function parseDate(mixed $value): ?Carbon
    {
        $ymd = DateParser::date(is_string($value) ? $value : null);
        if (! $ymd) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $ymd)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
