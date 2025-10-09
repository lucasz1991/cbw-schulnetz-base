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
    private const PAST_YEARS   = 1; // ab jetzt -2 Jahre
    private const FUTURE_YEARS = 1; // bis jetzt +1 Jahr

    public function __construct(public int $personPk) {}

    public function uniqueId(): string
    {
        return 'check-persons-courses:' . $this->personPk;
    }

    public function handle(): void
    {
        $person = Person::find($this->personPk);
        if (! $person) {
            Log::warning("CheckPersonsCourses: Person {$this->personPk} nicht gefunden.");
            return;
        }

        $role = $person->role ?? 'guest';
        $pd   = $person->programdata ?? null;

        if (empty($pd)) {
            Log::info("CheckPersonsCourses: Keine programdata für Person #{$person->id} (role={$role}).");
            return;
        }

        $klassenIds = $role === 'tutor'
            ? $this->extractTutorKlassenIds($pd)
            : $this->extractGuestKlassenIds($pd);

        if ($klassenIds->isEmpty()) {
            Log::info("CheckPersonsCourses: Keine klassen_id im Fenster {$this->windowStart()->toDateString()} bis {$this->windowEnd()->toDateString()} (role={$role}, person #{$person->id}).");
            return;
        }

        foreach ($klassenIds as $kid) {
            Log::info("CheckPersonsCourses: Dispatch CreateOrUpdateCourse für klassen_id={$kid} (person #{$person->id}).");
            CreateOrUpdateCourse::dispatch($kid);
        }
    }

    protected function windowStart(): Carbon { return now()->startOfDay()->subMonths(6); }
    protected function windowEnd(): Carbon   { return now()->endOfDay()->addMonths(6); }

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
