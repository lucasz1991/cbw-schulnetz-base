<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Person;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class CheckPersonsCourses implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public $backoff = [10, 60, 180];

    // Fenster konfigurieren: Vergangenheit/Zukunft
    private const PAST_YEARS   = 1; // ab jetzt -1 Jahr
    private const FUTURE_YEARS = 1; // bis jetzt +1 Jahr

    public function __construct(public int $personPk) {}

    public function uniqueId(): string
    {
        return 'check-persons-courses:' . $this->personPk;
    }

    public function handle(): void
    {
        // Fenster einmal berechnen (für Log)
        $windowStart = $this->windowStart();
        $windowEnd   = $this->windowEnd();

        // Zentrales Log-Array
        $log = [
            'person_pk'    => $this->personPk,
            'person_id'    => null,
            'role'         => null,
            'status'       => null,
            'messages'     => [],
            'window_start' => $windowStart->toDateString(),
            'window_end'   => $windowEnd->toDateString(),
            'klassen_ids'  => [],
            'jobs_dispatched' => 0,
        ];

        $writeLog = function (string $level = 'info') use (&$log) {
            $log['messages'] = array_values(array_unique($log['messages']));
            Log::$level('CheckPersonsCourses summary', $log);
        };

        $person = Person::find($this->personPk);
        if (! $person) {
            $log['status']    = 'person_not_found';
            $log['messages'][] = "Person nicht gefunden.";
            $writeLog('warning');
            return;
        }

        $role = $person->role ?? 'guest';
        $pd   = $person->programdata ?? null;

        $log['person_id'] = $person->id;
        $log['role']      = $role;

        if (empty($pd)) {
            $log['status']    = 'no_programdata';
            $log['messages'][] = "Keine programdata vorhanden.";
            $writeLog('info');
            return;
        }

        $klassenIds = $role === 'tutor'
            ? $this->extractTutorKlassenIds($pd)
            : $this->extractGuestKlassenIds($pd);

        if ($klassenIds->isEmpty()) {
            $log['status']    = 'no_klassen_ids';
            $log['messages'][] = "Keine klassen_id im Fenster.";
            $writeLog('info');
            return;
        }

        // Jobs dispatchen (ohne Einzel-Logs)
        foreach ($klassenIds as $kid) {
            CreateOrUpdateCourse::dispatch($kid);
        }

        $log['status']          = 'ok';
        $log['klassen_ids']     = $klassenIds->values()->all();
        $log['jobs_dispatched'] = $klassenIds->count();
        $log['messages'][]      = "CreateOrUpdateCourse Jobs dispatched.";

        $writeLog('info');
    }

    protected function windowStart(): Carbon
    {
        // aktuell 6 Monate zurück – wenn du die YEAR-Const nutzen willst,
        // kannst du das hier leicht umbauen.
        return now()->startOfDay()->subMonths(6);
    }

    protected function windowEnd(): Carbon
    {
        return now()->endOfDay()->addMonths(6);
    }

    /**
     * Versucht ein Datum aus Strings wie "YYYY/MM/DD", "YYYY-MM-DD",
     * "DD.MM.YYYY", "DD-MM-YYYY" zu parsen.
     */
    protected function parseDate(?string $value): ?Carbon
    {
        if (!is_string($value) || ($value = trim($value)) === '') {
            return null;
        }

        // Normalisiere Punkte zu Bindestrich, damit auch "DD.MM.YYYY" etc. abgefangen werden kann
        $normalized = str_replace('.', '-', $value);

        $formats = ['Y/m/d', 'Y-m-d', 'd-m-Y', 'd/m/Y'];
        foreach ($formats as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, $normalized);
                if ($c !== false) {
                    return $c->startOfDay();
                }
            } catch (\Throwable $e) {
                // weiterprobieren
            }
        }

        // letzter Versuch: Carbon::parse (best effort)
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Teilnehmer (guest):
     * programdata['tn_baust'][].beginn_baustein + .klassen_id
     * Filter: start ∈ [windowStart, windowEnd]
     */
    protected function extractGuestKlassenIds(array $programdata)
    {
        $from = $this->windowStart();
        $to   = $this->windowEnd();

        $rows = Arr::get($programdata, 'tn_baust', []);
        return collect($rows)
            ->filter(function ($row) use ($from, $to) {
                if (!is_array($row)) return false;
                $start = $this->parseDate($row['beginn_baustein'] ?? null);
                if (!$start) return false;
                return $start->gte($from) && $start->lte($to);
            })
            ->map(fn($row) => $row['klassen_id'] ?? null)
            ->filter(fn($v) => is_string($v) && trim($v) !== '')
            ->map(fn($v) => trim($v))
            ->unique()
            ->values();
    }

    /**
     * Tutor:
     * programdata['courses'][].beginn + .klassen_id
     * Filter: start ∈ [windowStart, windowEnd]
     */
    protected function extractTutorKlassenIds(array $programdata)
    {
        $from = $this->windowStart();
        $to   = $this->windowEnd();

        $rows = Arr::get($programdata, 'courses', []);
        return collect($rows)
            ->filter(function ($row) use ($from, $to) {
                if (!is_array($row)) return false;
                $start = $this->parseDate($row['beginn'] ?? null);
                if (!$start) return false;
                return $start->gte($from) && $start->lte($to);
            })
            ->map(fn($row) => $row['klassen_id'] ?? null)
            ->filter(fn($v) => is_string($v) && trim($v) !== '')
            ->map(fn($v) => trim($v))
            ->unique()
            ->values();
    }
}
