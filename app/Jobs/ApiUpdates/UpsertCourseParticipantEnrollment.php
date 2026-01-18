<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Course;
use App\Models\Person;
use App\Models\CourseParticipantEnrollment as Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use App\Services\Helper\DateParser;


class UpsertCourseParticipantEnrollment implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public $backoff = [10, 60, 180];

    public function __construct(
        public int    $courseId,
        public string $klassenId,
        public array  $participantRow, // enthält p.* inkl. person_id
        public array  $courseMeta = [], // termin_id, vtz, kurzbez_ba, baustein_id, participantsdata (Array!)
    ) {}

    public function uniqueId(): string
    {
        $pid = $this->participantRow['person_id'] ?? 'unknown';
        return "enrollment:{$this->courseId}:{$this->klassenId}:{$pid}";
    }

    public function handle(): void
    {
        $course = Course::find($this->courseId);
        if (!$course) {
            Log::warning("Enrollment: Course {$this->courseId} nicht gefunden.");
            return;
        }

        $uvsPid = $this->participantRow['person_id'] ?? null;
        if (!$uvsPid) {
            Log::warning("Enrollment: fehlende person_id (course_id={$this->courseId}, klassen_id={$this->klassenId})");
            return;
        }

        // 1) Person minimal anlegen/updaten

        $tp = $this->participantRow;

        $parseDate = fn($v) => DateParser::date($v);
        $parseDateTime = fn($v) => DateParser::dateTime($v);

        $person = Person::updateOrCreate(
            ['person_id' => $tp['person_id']], // UVS-Person-ID als eindeutiger Schlüssel
            [
                'institut_id'       => $tp['institut_id']   ?? null,
                'person_nr'         => $tp['person_nr']     ?? null,
                'role'              => 'guest',
                'status'            => $tp['status']        ?? null,
                'upd_date'          => $parseDateTime($tp['upd_date'] ?? null),
                'nachname'          => $tp['nachname']      ?? null,
                'vorname'           => $tp['vorname']       ?? null,
                'geschlecht'        => $tp['geschlecht']    ?? null,
                'titel_kennz'       => $tp['titel_kennz']   ?? null,
                'nationalitaet'     => $tp['nationalitaet'] ?? null,
                'familien_stand'    => $tp['familien_stand']?? null,
                'geburt_datum'      => $parseDate($tp['geburt_datum'] ?? null),
                'geburt_name'       => $tp['geburt_name']   ?? null,
                'geburt_land'       => $tp['geburt_land']   ?? null,
                'geburt_ort'        => $tp['geburt_ort']    ?? null,
                'lkz'               => $tp['lkz']           ?? null,
                'plz'               => $tp['plz']           ?? null,
                'ort'               => $tp['ort']           ?? null,
                'strasse'           => $tp['strasse']       ?? null,
                'adresszusatz1'     => $tp['adresszusatz1'] ?? null,
                'adresszusatz2'     => $tp['adresszusatz2'] ?? null,
                'telefon1'          => $tp['telefon1']      ?? null,
                'telefon2'          => $tp['telefon2']      ?? null,
                'email_priv'        => $tp['email_priv']    ?? null,
                'email_cbw'         => $tp['email_cbw']     ?? null,
                'personal_nr'       => $tp['personal_nr']   ?? null,
                'angestellt_von'    => $parseDateTime($tp['angestellt_von'] ?? null),
                'angestellt_bis'    => $parseDateTime($tp['angestellt_bis'] ?? null),
                'last_api_update'   => now(),
            ]
        );
        // 2) Enrollment inkl. SoftDeletes holen und ggf. reaktivieren
        $enrollment = Enrollment::withTrashed()->firstOrNew([
            'course_id' => $course->id,
            'person_id' => $person->id,
        ]);

        if ($enrollment->trashed()) {
            $enrollment->restore();
        }

        // 3) Nur erlaubte Felder setzen
        $enrollment->fill([
            'course_id'      => $course->id,
            'person_id'      => $person->id,

            'teilnehmer_id'  => $this->participantRow['teilnehmer_id']  ?? $enrollment->teilnehmer_id,
            'tn_baustein_id' => $this->participantRow['tn_baustein_id'] ?? $enrollment->tn_baustein_id,
            'baustein_id'    => $this->courseMeta['baustein_id']        ?? $enrollment->baustein_id,

            'klassen_id'     => $this->klassenId,
            'termin_id'      => $this->courseMeta['termin_id']          ?? $enrollment->termin_id,
            'vtz'            => $this->courseMeta['vtz']                ?? $enrollment->vtz,
            'kurzbez_ba'     => $this->courseMeta['kurzbez_ba']         ?? ($this->courseMeta['kurzbez'] ?? $enrollment->kurzbez_ba),

            'source_snapshot'=> $this->participantRow,
            'source_last_upd'=> now(),
            'last_synced_at' => now(),
        ]);

        $enrollment->save();

        // 4) Cleanup (nur wenn das Remote-Set bereits über courseMeta übergeben wurde)
        $this->cleanupRemovedEnrollments($course);
    }

    protected function cleanupRemovedEnrollments(Course $course): void
    {
        $remoteArray = $this->courseMeta['participantsdata'] ?? null;
        if (!is_array($remoteArray)) {
            // Kein Remote-Set mitgegeben -> kein Cleanup in diesem Job
            return;
        }

        // Lock, damit Cleanup für diese Klasse nicht parallel mehrfach läuft
        $lock = Cache::lock("cleanup:enrollments:{$this->klassenId}", 20);
        if (!$lock->get()) {
            return;
        }

        try {
            $remoteUvIds = collect($remoteArray)
                ->pluck('person_id')
                ->filter()
                ->unique()
                ->values();

            // aktive lokale Enrollments dieses Kurses
            $active = Enrollment::where('course_id', $course->id)
                ->whereNull('deleted_at')
                ->get(['id','person_id']);

            // Map: lokale person.id -> UVS person_id
            $mapLocalToUv = Person::whereIn('id', $active->pluck('person_id'))
                ->pluck('person_id', 'id'); // [local_person_id => uvs_person_id]

            $toSoftDelete = $active->filter(function ($enr) use ($remoteUvIds, $mapLocalToUv) {
                $uvsPid = $mapLocalToUv[$enr->person_id] ?? null;
                return $uvsPid && !$remoteUvIds->contains($uvsPid);
            });

            if ($toSoftDelete->isNotEmpty()) {
                $toSoftDelete->each->delete(); // SoftDeletes
                Log::info("Enrollment-Cleanup: SoftDeleted {$toSoftDelete->count()} (course_id={$course->id}, klassen_id={$this->klassenId}).");
            }
        } catch (\Throwable $e) {
            Log::error("Enrollment-Cleanup Fehler (klassen_id={$this->klassenId}): ".$e->getMessage());
        } finally {
            optional($lock)->release();
        }
    }
}
