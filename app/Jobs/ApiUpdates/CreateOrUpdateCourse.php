<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Course;
use App\Models\CourseDay;
use App\Models\Person;
use App\Models\User;
use App\Services\ApiUvs\ApiUvsService;
use App\Services\Helper\DateParser;
use App\Jobs\ApiUpdates\UpsertCourseParticipantEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CreateOrUpdateCourse implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string UVS-Klassen-ID */
    public string $klassenId;

    public int $tries = 3;
    public $backoff = [10, 60, 180];

    /** Cooldown: innerhalb dieses Fensters nicht erneut syncen */
    private const COOLDOWN_MINUTES = 10;

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

        // Zentrales Log-Array (wird am Ende in EINEM Log geschrieben)
        $log = [
            'klassen_id'         => $this->klassenId,
            'cooldown_minutes'   => self::COOLDOWN_MINUTES,
            'status'             => null,
            'messages'           => [],
            'course_id'          => null,
            'participants_count' => 0,
            'days_total'         => 0,
            'days_changed'       => 0,
        ];

        // Helper zum finalen Loggen
        $writeLog = function (string $level = 'info') use (&$log) {
            $log['messages'] = array_values(array_unique($log['messages']));
            // Log::$level('CreateOrUpdateCourse summary', $log);
        };

        if (empty($this->klassenId)) {
            $log['status'] = 'no_klassen_id';
            $log['messages'][] = 'Keine klassen_id übergeben.';
            $writeLog('warning');
            return;
        }

        // COOLDOWN über Cache
        $cacheKey = "course-sync-cooldown:{$this->klassenId}";

        // add() legt den Key nur an, wenn er noch nicht existiert.
        // Rückgabe false => Cooldown aktiv -> Job überspringen.
        if (!Cache::add($cacheKey, now()->toDateTimeString(), now()->addMinutes(self::COOLDOWN_MINUTES))) {
            $log['status'] = 'cooldown_active';
            $log['messages'][] = 'Abbruch: Cooldown aktiv, Kurs zuletzt vor kurzem synchronisiert.';
            $log['last_run_at'] = Cache::get($cacheKey);
            $writeLog('info');
            return;
        }

        $log['messages'][] = 'Cooldown gesetzt, Kurs wird synchronisiert.';

        // ---------------------------------------------------------------------
        // 1) Daten holen (robust gegen unterschiedliche Response-Wrapper)
        // ---------------------------------------------------------------------
        $res = $api->getCourseByKlassenId($this->klassenId);

        // Erwartete Strukturen abdecken:
        $payloadOk = $res['ok'] ?? ($res['data']['ok'] ?? null);
        $payload   = $res['data']['data'] ?? $res['data'] ?? $res;

        if (!$payloadOk && !isset($payload['course'])) {
            $log['status'] = 'invalid_payload';
            $log['messages'][] = 'Keine/ungültige Daten vom UVS-API.';
            // Cooldown wieder freigeben, damit beim nächsten Versuch direkt neu geholt werden kann
            Cache::forget($cacheKey);
            $writeLog('warning');
            return;
        }

        $courseData       = $payload['course']       ?? null;
        $participantsData = $payload['participants'] ?? [];
        $teachersData     = $payload['teachers']     ?? [];
        $daysData         = $payload['days']         ?? [];
        $materialsData    = $payload['materials']    ?? [];

        if (!$courseData) {
            $log['status'] = 'missing_course_data';
            $log['messages'][] = "API-Response ohne 'course'-Daten.";
            Cache::forget($cacheKey);
            $writeLog('warning');
            return;
        }

        // ---------------------------------------------------------------------
        // 2) Primären Tutor anlegen/aktualisieren (falls vorhanden)
        // ---------------------------------------------------------------------
        $tutorPersonId = null;
        $primaryTutor  = $teachersData[0] ?? null;

        if ($primaryTutor) {
            $tp = $primaryTutor;
            $log['messages'][] = "PrimaryTutor vorhanden (person_id={$tp['person_id']}).";

            // Robust: UVS-Rohwerte niemals direkt via Carbon::parse() parsen
            $parseDate     = fn($v) => DateParser::date($v);
            $parseDateTime = fn($v) => DateParser::dateTime($v);

            // Person anhand person_id upserten (jede UVS-Person -> eigener Datensatz)
            $tutorPerson = Person::updateOrCreate(
                ['person_id' => $tp['person_id']], // UVS-Person-ID als eindeutiger Schlüssel
                [
                    'institut_id'     => $tp['institut_id']   ?? null,
                    'person_nr'       => $tp['person_nr']     ?? null,
                    'role'            => 'tutor',
                    'status'          => $tp['status']        ?? null,
                    'upd_date'        => $parseDateTime($tp['upd_date'] ?? null),
                    'nachname'        => $tp['nachname']      ?? null,
                    'vorname'         => $tp['vorname']       ?? null,
                    'geschlecht'      => $tp['geschlecht']    ?? null,
                    'titel_kennz'     => $tp['titel_kennz']   ?? null,
                    'nationalitaet'   => $tp['nationalitaet'] ?? null,
                    'familien_stand'  => $tp['familien_stand'] ?? null,
                    'geburt_datum'    => $parseDate($tp['geburt_datum'] ?? null),
                    'geburt_name'     => $tp['geburt_name']   ?? null,
                    'geburt_land'     => $tp['geburt_land']   ?? null,
                    'geburt_ort'      => $tp['geburt_ort']    ?? null,
                    'lkz'             => $tp['lkz']           ?? null,
                    'plz'             => $tp['plz']           ?? null,
                    'ort'             => $tp['ort']           ?? null,
                    'strasse'         => $tp['strasse']       ?? null,
                    'adresszusatz1'   => $tp['adresszusatz1'] ?? null,
                    'adresszusatz2'   => $tp['adresszusatz2'] ?? null,
                    'telefon1'        => $tp['telefon1']      ?? null,
                    'telefon2'        => $tp['telefon2']      ?? null,
                    'email_priv'      => $tp['email_priv']    ?? null,
                    'email_cbw'       => $tp['email_cbw']     ?? null,
                    'personal_nr'     => $tp['personal_nr']   ?? null,
                    'angestellt_von'  => $parseDateTime($tp['angestellt_von'] ?? null),
                    'angestellt_bis'  => $parseDateTime($tp['angestellt_bis'] ?? null),
                    'last_api_update' => now(),
                ]
            );

            // Falls der Dozent bereits ein User-Konto (Register-Flow) besitzt:
            if (empty($tutorPerson->user_id) && !empty($tp['email_priv'])) {
                $user = User::where('email', $tp['email_priv'])->first();
                if ($user) {
                    $tutorPerson->user_id = $user->id;
                    $tutorPerson->save();
                    $log['messages'][] = "Tutor-Person {$tutorPerson->person_id} mit User #{$user->id} verknüpft.";
                } else {
                    $log['messages'][] = "Kein User mit E-Mail {$tp['email_priv']} gefunden, keine Verknüpfung.";
                }
            }

            $tutorPersonId = $tutorPerson->id;
        } else {
            $log['messages'][] = 'Kein primaryTutor im teachersData gefunden.';
        }

        // ---------------------------------------------------------------------
        // 3) Kurs anlegen/aktualisieren
        // ---------------------------------------------------------------------
        $course = Course::updateOrCreate(
            ['klassen_id' => $this->klassenId],
            [
                'termin_id'               => $courseData['termin_id']      ?? null,
                'institut_id'             => $courseData['institut_id_ks'] ?? null,
                'vtz'                     => $courseData['vtz_kennz_ks']   ?? null,
                'room'                    => $courseData['unterr_raum']    ?? null,
                'title'                   => $courseData['bezeichnung']    ?? ('Kurs ' . $this->klassenId),
                'description'             => $courseData['bemerkung']      ?? null,
                'educational_materials'   => $materialsData                ?? [],
                'planned_start_date'      => DateParser::date($courseData['beginn'] ?? null),
                'planned_end_date'        => DateParser::date($courseData['ende']   ?? null),
                'type'                    => 'basic',
                'settings'                => [],
                'source_snapshot'         => $payload, // gesamte Payload speichern
                'source_last_upd'         => now(),
                'is_active'               => true,
                'primary_tutor_person_id' => $tutorPersonId,
                'last_synced_at'          => now(),
            ]
        );

        $log['course_id']  = $course->id;
        $log['messages'][] = "Kurs upserted (id={$course->id}).";

        // ---------------------------------------------------------------------
        // 4) Participants einzeln verarbeiten (ohne Einzel-Logs)
        // ---------------------------------------------------------------------
        $participantsCount = 0;

        foreach (($participantsData ?? []) as $pRow) {
            $participantsCount++;

            UpsertCourseParticipantEnrollment::dispatch(
                courseId: $course->id,
                klassenId: $this->klassenId,
                participantRow: $pRow,
                courseMeta: [
                    'termin_id'        => $courseData['termin_id']    ?? null,
                    'vtz'              => $courseData['vtz_kennz_ks']  ?? null,
                    'kurzbez_ba'       => $courseData['kurzbez']       ?? ($courseData['kurzbez_ba'] ?? null),
                    'baustein_id'      => $courseData['baustein_id']   ?? null,
                    'participantsdata' => $participantsData            ?? [],
                ]
            );
        }

        $log['participants_count'] = $participantsCount;
        $log['messages'][] = "Teilnehmer-Jobs dispatched: {$participantsCount}.";

        // ---------------------------------------------------------------------
        // 5) Kurstage synchronisieren (zusammenfassendes Logging)
        // ---------------------------------------------------------------------
        $daysTotal   = 0;
        $daysChanged = 0;

        if (!empty($daysData)) {
            foreach ($daysData as $d) {
                $daysTotal++;

                // Erwartete Felder vom API-Endpoint:
                // 'datum' (YYYY-MM-DD), 'unterr_beginn' (HH:MM), 'unterr_ende' (HH:MM), 'std' (z.B. "6.00"), 'art' (z.B. "U"/"P"/"F")
                $rawDate = $d['datum'] ?? null;
                if (!$rawDate) {
                    continue;
                }

                $stdVal = $d['std'] ?? null;
                if ($stdVal !== null && (float)$stdVal == 0.0) {
                    // Sicherung, falls API-Filter mal fehlt
                    continue;
                }

                // Robust: niemals Carbon::parse auf UVS-Rohwert
                $date = DateParser::date($rawDate);
                if (!$date) {
                    continue;
                }

                $startNorm = $this->normalizeTime($d['unterr_beginn'] ?? null);
                $endNorm   = $this->normalizeTime($d['unterr_ende']   ?? null);
                $startDT   = $this->carbonFromDateAndTime($date, $startNorm);
                $endDT     = $this->carbonFromDateAndTime($date, $endNorm);

                $type  = $d['art'] ?? null; // 'U','P','F',...
                $topic = match (strtoupper((string)$type)) {
                    'U' => 'Unterricht',
                    'P' => 'Prüfung',
                    'F' => 'Feiertag',
                    default => null,
                };

                $day = CourseDay::firstOrNew([
                    'course_id' => $course->id,
                    'date'      => $date,
                ]);

                $dirty = false;

                if ($startDT && (!$day->start_time || !$day->start_time->equalTo($startDT))) {
                    $day->start_time = $startDT;
                    $dirty = true;
                }

                if ($endDT && (!$day->end_time || !$day->end_time->equalTo($endDT))) {
                    $day->end_time = $endDT;
                    $dirty = true;
                }

                if ($stdVal !== null && (string)$day->std !== (string)$stdVal) {
                    $day->std = $stdVal;
                    $dirty = true;
                }

                if ($type !== null && $day->type !== $type) {
                    $day->type = $type;
                    $dirty = true;
                }

                if ($topic !== null && $day->topic !== $topic) {
                    $day->topic = $topic;
                    $dirty = true;
                }

                if (!$day->exists || $dirty) {
                    $day->save();
                    $daysChanged++;
                }
            }
        }

        $log['days_total']   = $daysTotal;
        $log['days_changed'] = $daysChanged;
        $log['messages'][]   = "Kurstage verarbeitet: total={$daysTotal}, geändert/neu={$daysChanged}.";

        // Fertig
        $log['status'] = 'ok';
        $writeLog('info');
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

        $h = null;
        $m = null;

        // 1) "HHMM" -> HH:MM   (z.B. 800, 930)
        if (preg_match('/^(\d{1,2})(\d{2})$/', $t, $mch)) {
            $h = (int)$mch[1];
            $m = (int)$mch[2];
        }
        // 2) "H:M" oder "HH:MM"
        elseif (preg_match('/^(\d{1,2}):(\d{1,2})$/', $t, $mch)) {
            $h = (int)$mch[1];
            $mStr = $mch[2];
            $m = strlen($mStr) === 1 ? (int)('0' . $mStr) : (int)$mStr;
        }
        // 3) "H" oder "HH" -> HH:00
        elseif (preg_match('/^(\d{1,2})$/', $t, $mch)) {
            $h = (int)$mch[1];
            $m = 0;
        } else {
            return null;
        }

        if ($h < 0 || $h > 23) return null;
        if ($m < 0) $m = 0;
        if ($m > 59) $m = 59;

        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * Sichere Carbon-Erzeugung aus Datum (Y-m-d) + Uhrzeit (HH:MM)
     * Log bleibt bewusst erhalten (Diagnose)
     */
    private function carbonFromDateAndTime(?string $date, ?string $hhmm): ?\Carbon\Carbon
    {
        $date = trim((string)$date);
        $hhmm = trim((string)$hhmm);

        if ($date === '' || $hhmm === '') return null;

        // Schutz gegen Platzhalter / Müll
        if ($date === '/' || $date === '//' || $date === '0' || $hhmm === '0') {
            return null;
        }

        // Erwartete Formate hart validieren
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        if (!preg_match('/^\d{1,2}:\d{2}$/', $hhmm)) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$date} {$hhmm}");
        } catch (Throwable $e) {
            Log::warning('CreateOrUpdateCourse: time parse failed', [
                'date' => $date,
                'time' => $hhmm,
                'err'  => $e->getMessage(),
            ]);
            return null;
        }
    }
}
