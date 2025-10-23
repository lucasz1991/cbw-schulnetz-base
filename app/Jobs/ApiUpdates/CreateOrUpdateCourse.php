<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Course;
use App\Models\CourseDay;
use App\Models\Person;
use App\Services\ApiUvs\ApiUvsService;
use App\Jobs\ApiUpdates\UpsertCourseParticipantEnrollment;
use Illuminate\Support\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;


class CreateOrUpdateCourse implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string UVS-Klassen-ID */
    public string $klassenId;

    public int $tries = 3;
    public $backoff = [10, 60, 180];

    /** Cooldown: innerhalb dieses Fensters nicht erneut syncen */
    private const COOLDOWN_MINUTES = 15;

    public function __construct(string $klassenId)
    {
        $this->klassenId = $klassenId;
        // $this->onQueue('api'); // optional
    }

    public function uniqueId(): string
    {
        return 'course-update:' . $this->klassenId;
    }

    public function handle(): void
    {
        /** @var ApiUvsService $api */
        $api = app(ApiUvsService::class);

        if (empty($this->klassenId)) {
            Log::warning('CreateOrUpdateCourse: keine klassen_id übergeben.');
            return;
        }

        $recent = Course::query()
            ->where('klassen_id', $this->klassenId)
            ->first(['id', 'updated_at']);

        if ($recent && $recent->updated_at) {
            $last = $recent->updated_at instanceof Carbon
                ? $recent->updated_at
                : Carbon::parse($recent->updated_at);

            if ($last->gte(now()->subMinutes(self::COOLDOWN_MINUTES))) {
                Log::info("CreateOrUpdateCourse: Abbruch für {$this->klassenId} – bereits synchronisiert um {$last->toDateTimeString()} (Cooldown ".self::COOLDOWN_MINUTES." Min).");
                return;
            }
        }
        // ---------------------------------------------------------------------

        Log::info("CreateOrUpdateCourse: Synchronisiere Kurs {$this->klassenId}");

        // 1) Daten holen (robust gegen unterschiedliche Response-Wrapper)
        $res = $api->getCourseByKlassenId($this->klassenId);

        // Erwartete Strukturen abdecken:
        $payloadOk = $res['ok'] ?? ($res['data']['ok'] ?? null);
        $payload   = $res['data']['data'] ?? $res['data'] ?? $res;

        if (!$payloadOk && !isset($payload['course'])) {
            Log::warning("CreateOrUpdateCourse: Keine/ungültige Daten für klassen_id={$this->klassenId}");
            return;
        }

        $courseData       = $payload['course']       ?? null;
        $participantsData = $payload['participants'] ?? [];
        $teachersData     = $payload['teachers']     ?? [];
        $daysData         = $payload['days']         ?? [];
        $materialsData    = $payload['materials']    ?? [];

        if (!$courseData) {
            Log::warning("CreateOrUpdateCourse: API-Response ohne 'course' für {$this->klassenId}");
            return;
        }

        // 2) Primären Tutor anlegen/aktualisieren (falls vorhanden)
        $tutorPersonId = null;
        $primaryTutor  = $teachersData[0] ?? null;

        if ($primaryTutor) {
            $tp = $primaryTutor;
            Log::info("CreateOrUpdateCourse: primaryTutor für {$this->klassenId} ist {$tp['person_id']} und wird gespeichert.");
            // Person aus UVS (hat p.* laut Endpoint)

            // Datumskonvertierung helpers
            $parseDate = fn($v) => $v ? Carbon::parse($v)->toDateString() : null;
            $parseDateTime = fn($v) => $v ? Carbon::parse($v)->toDateTimeString() : null;

            $tutorPerson = Person::updateOrCreate(
                ['person_id' => $tp['person_id']], // UVS-Person-ID als eindeutiger Schlüssel
                [
                    'institut_id'       => $tp['institut_id']   ?? null,
                    'person_nr'         => $tp['person_nr']     ?? null,
                    'role'              => 'tutor',
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

            $tutorPersonId = $tutorPerson->id;
        }

        // 3) Kurs anlegen/aktualisieren
        // Achtung: Stelle sicher, dass dein Endpoint 'termin_id' + 'bemerkung' liefert (siehe Hinweis unten).
        $course = Course::updateOrCreate(
            ['klassen_id' => $this->klassenId],
            [
                'termin_id'           => $courseData['termin_id']        ?? null,
                'institut_id'         => $courseData['institut_id_ks']   ?? null,
                'vtz'                 => $courseData['vtz_kennz_ks']     ?? null,
                'room'                => $courseData['unterr_raum']      ?? null,
                'title'               => $courseData['bezeichnung']      ?? ('Kurs ' . $this->klassenId),
                'description'         => $courseData['bemerkung']        ?? null,
                'educational_materials' => $materialsData                 ?? [],
                'planned_start_date'  => $courseData['beginn']           ?? null,
                'planned_end_date'    => $courseData['ende']             ?? null,
                'type'                => 'basic',
                'settings'            => [],
                'source_snapshot'     => $payload,        // gesamte Payload speichern
                'source_last_upd'     => now(),
                'is_active'           => true,
                'primary_tutor_person_id' => $tutorPersonId,
                'last_synced_at'      => now(),
            ]
        );

        Log::info("CreateOrUpdateCourse: Kurs #{$course->id} ({$this->klassenId}) aktualisiert.");

        // participants einzeln verarbeiten
        foreach (($participantsData ?? []) as $pRow) {
            Log::info("UpsertCourseParticipantEnrollment: Participant #{$pRow['person_id']} in Klasse ({$this->klassenId}) dispatch.");
            UpsertCourseParticipantEnrollment::dispatch(
                courseId: $course->id,
                klassenId: $this->klassenId,
                participantRow: $pRow,
                courseMeta: [
                    'termin_id'         => $coursedata['termin_id']      ?? null,
                    'vtz'               => $coursedata['vtz_kennz_ks']   ?? null,
                    'kurzbez_ba'        => $courseData['kurzbez']        ?? ($courseData['kurzbez_ba'] ?? null),
                    'baustein_id'       => $coursedata['baustein_id']    ?? null,
                    'participantsdata'  => $participantsData             ??  [],
                ]
            );
        }

        Log::info("Kurstage synchronisieren: Kurs #{$course->id} ({$this->klassenId}) aktualisiert.");
        if (!empty($daysData)) {
            foreach ($daysData as $d) {
                // Erwartete Felder vom API-Endpoint:
                // 'datum' (YYYY-MM-DD), 'unterr_beginn' (HH:MM), 'unterr_ende' (HH:MM), 'std' (z.B. "6.00"), 'art' (z.B. "U"/"P"/"F")
                $rawDate = $d['datum'] ?? null;
                if (!$rawDate) {
                    Log::warning("CreateOrUpdateCourse: Kurstag ohne datum übersprungen ({$this->klassenId}).");
                    continue;
                }

                $stdVal     = $d['std'] ?? null;
                if ($stdVal !== null && (float)$stdVal == 0.0) {
                    continue; // Sicherung, falls API-Filter mal fehlt
                }

                $date       = \Carbon\Carbon::parse($rawDate)->toDateString();
                $startNorm  = $this->normalizeTime($d['unterr_beginn'] ?? null);
                $endNorm    = $this->normalizeTime($d['unterr_ende']   ?? null);
                $startDT    = $this->carbonFromDateAndTime($date, $startNorm);
                $endDT      = $this->carbonFromDateAndTime($date, $endNorm);

                $type  = $d['art'] ?? null; // 'U','P','F',...
                $topic = match (strtoupper((string)$type)) {
                    'U' => 'Unterricht',
                    'P' => 'Prüfung',
                    'F' => 'Feiertag',
                    default => null,
                };

                $day = \App\Models\CourseDay::firstOrNew([
                    'course_id' => $course->id,
                    'date'      => $date,
                ]);

                $dirty = false;
                if ($startDT && (!$day->start_time || !$day->start_time->equalTo($startDT))) {
                    $day->start_time = $startDT; $dirty = true;
                }
                if ($endDT && (!$day->end_time || !$day->end_time->equalTo($endDT))) {
                    $day->end_time = $endDT; $dirty = true;
                }
                if ($stdVal !== null && (string)$day->std !== (string)$stdVal) {
                    $day->std = $stdVal; $dirty = true;
                }
                if ($type !== null && $day->type !== $type) {
                    $day->type = $type; $dirty = true;
                }
                if ($topic !== null && $day->topic !== $topic) {
                    $day->topic = $topic; $dirty = true;
                }

                if (!$day->exists || $dirty) {
                    $day->save();
                    Log::info("CreateOrUpdateCourse: CourseDay upserted ({$this->klassenId}) {$date}.");
                }

            }
        }

    }

    private function normalizeTime(?string $t): ?string
    {
        if ($t === null) return null;
        $t = trim((string)$t);
        if ($t === '') return null;

        // .,; -> :
        $t = str_replace([',', '.', ';'], ':', $t);
        // Leerzeichen raus
        $t = preg_replace('/\s+/', '', $t);

        $h = null; $m = null;

        // 1) "HHMM" -> HH:MM   (z.B. 800, 930)
        if (preg_match('/^(\d{1,2})(\d{2})$/', $t, $mch)) {
            $h = (int) $mch[1];
            $m = (int) $mch[2];
        }
        // 2) "H:M" oder "HH:MM"
        elseif (preg_match('/^(\d{1,2}):(\d{1,2})$/', $t, $mch)) {
            $h = (int) $mch[1];
            $mStr = $mch[2];
            $m = strlen($mStr) === 1 ? (int)('0'.$mStr) : (int)$mStr;
        }
        // 3) "H" oder "HH" -> HH:00
        elseif (preg_match('/^(\d{1,2})$/', $t, $mch)) {
            $h = (int) $mch[1];
            $m = 0;
        } else {
            return null;
        }

        if ($h < 0 || $h > 23) return null;
        if ($m < 0) $m = 0;
        if ($m > 59) $m = 59;

        return sprintf('%02d:%02d', $h, $m);
    }

    /** Sichere Carbon-Erzeugung für "Y-m-d H:i" */
    private function carbonFromDateAndTime(?string $date, ?string $hhmm): ?\Carbon\Carbon
    {
        if (!$date || !$hhmm) return null;
        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$date} {$hhmm}");
        } catch (Throwable $e) {
            \Log::warning('CreateOrUpdateCourse: time parse failed', ['date' => $date, 'time' => $hhmm, 'err' => $e->getMessage()]);
            return null;
        }
    }
}
