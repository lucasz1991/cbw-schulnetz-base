<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;


class ProgramShow extends Component
{
    use WithPagination;

    /** Eingeloggter User (fürs View, falls nötig) */
    public $userData;

    /** Rohdaten direkt aus der DB (user->person->programdata) */
    public array $raw = [];

    /** ViewModel / Normalisierte Daten (ersetzt den @php-Block im Blade) */
    public array $teilnehmerDaten = [];
    public array $bausteine = [];
    public ?array $aktuellesModul = null;
    public ?array $naechstesModul = null;
    public int $anzahlBausteine = 0;
    public int $bestandenBausteine = 0;
    public int $progress = 0;
    public int $currentProgress = 0;

    public array $excludeFromProgress = ['FERI', 'PRAK', 'PRUE']; // alles, was nicht als Kurs zählt

    public $bausteinSerie;
    public $bausteinLabels;
    public $bausteinColors;

    public bool $apiProgramLoading = false;

    /** Listener */
    protected $listeners = ['refreshParent' => '$refresh'];

    public function mount(): void
    {
        $this->userData = Auth::user();
        if (! $this->userData?->person?->last_api_update || $this->userData?->person?->last_api_update->lt(now()->subHours(1))) {
            $this->userData?->person?->apiupdate();
        }
        $this->raw = $this->userData?->person?->programdata ?? [];

        if (empty($this->raw)) {
            // Keine Daten vorhanden
            $this->apiProgramLoading = true;
            return;
        }
        $this->buildViewModelFromRaw($this->raw);
    }

    public function pollProgram(): void
    {
        if (! $this->apiProgramLoading) return;

        $new = $this->userData?->person?->programdata ?? [];

        if (!empty($new)) {
            $this->apiProgramLoading = false;   // Polling beenden
            $this->raw = $new;
            $this->buildViewModelFromRaw($new);
        }
    }


    /**
     * Utility: numeric value or null
     */
    private function num(mixed $v): ?float
    {
        if (is_numeric($v)) {
            return $v + 0;
        }
        if (is_string($v)) {
            $v = trim($v);
            if ($v === 'passed') return 100.0;        // "passed" als 100%
            if (in_array($v, ['not att', '---', '-'], true)) return null;
            if (is_numeric($v)) return $v + 0;
        }
        return null;
    }

    private function toCarbon(null|string $v): ?Carbon
    {
        if (!$v) return null;
        try {
            // Rohdaten kommen als "YYYY/MM/DD" → für parse robuster machen
            return Carbon::parse(str_replace('/', '-', trim($v)));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * ViewModel aufbauen (entspricht deinem @php-Block)
     */
    private function buildViewModelFromRaw(array $raw): void
{
    // ---------- Bausteine normalisieren ----------
    $bausteine = collect($raw['tn_baust'] ?? [])->map(function ($b) {
        $kurz = $b['kurzbez'] ?? null;
        $typ  = $this->detectBausteinTyp($kurz);

        return [
            'klassen_id'         => $b['klassen_id'] ?? null,
            'baustein_id'        => $b['baustein_id'] ?? null,
            'block'              => null,
            'abschnitt'          => null,
            'beginn'             => $b['beginn_baustein'] ?? null,
            'ende'               => $b['ende_baustein'] ?? null,
            'tage'               => $this->num($b['baustein_tage'] ?? null),
            'unterrichtsklasse'  => $b['klassen_co_ks'] ?? null,
            'baustein'           => $b['langbez'] ?? ($b['kurzbez'] ?? '—'),
            'kurzbez'            => $kurz,
            'schnitt'            => $this->num($b['tn_punkte'] ?? null),
            'punkte'             => $this->num($b['tn_punkte'] ?? null),
            'fehltage'           => $this->num($b['fehltage'] ?? null),
            'klassenschnitt'     => $this->num($b['klassenschnitt'] ?? null),

            // Typisierung
            'typ'                => $typ,                // 'kurs' | 'ferien' | 'praktikum' | 'pruefung'
            'is_non_course'      => $typ !== 'kurs',     // true = NICHT als Kurs werten
        ];
    });

    // ---------- Zähl-/Progress-Basis: nur echte Kurse ----------
    $bausteineKurse = $bausteine->filter(fn ($b) => ($b['typ'] ?? null) === 'kurs');

    // optional: per Kurzcode bestimmte Bausteine ausschließen
    $bausteineKurse = $bausteineKurse->reject(function ($b) {
        $k = strtoupper((string) ($b['kurzbez'] ?? ''));
        return in_array($k, $this->excludeFromProgress, true);
    });

    // „Ergebnis offen“: Punkte==0 UND Klassenschnitt==0
    $istOffen = fn ($b) =>
        isset($b['punkte'], $b['klassenschnitt'])
        && (float)$b['punkte'] === 0.0
        && (float)$b['klassenschnitt'] === 0.0;

    // Kennzahlen
    $anzahlBausteine    = $bausteineKurse->count(); // NUR echte Kurse
    $bestandenBausteine = $bausteineKurse
        ->reject($istOffen)                                    // offene nicht als bestanden zählen
        ->filter(fn ($b) => is_numeric($b['schnitt']) && $b['schnitt'] >= 50)
        ->count();

    $progress = $anzahlBausteine ? (int) round(($bestandenBausteine / $anzahlBausteine) * 100) : 0;

    // ---------- Nur echte Kurse (typ === 'kurs'), mit gültigen Daten und sortiert ----------
    $kurseMitDatum = collect($bausteine)
        ->filter(fn ($b) => ($b['typ'] ?? null) === 'kurs')
        ->map(function ($b) {
            $b['_start'] = $this->toCarbon($b['beginn'] ?? null);
            $b['_end']   = $this->toCarbon($b['ende']   ?? null);
            return $b;
        })
        ->filter(fn ($b) => $b['_start'] && $b['_end'])
        ->sortBy('_start')
        ->values();

    $heute = Carbon::now('Europe/Berlin')->startOfDay();

    // Laufender Kurs: start <= heute <= end
    $aktuellesModul = $kurseMitDatum->first(
        fn ($b) => $b['_start']->lte($heute) && $b['_end']->gte($heute)
    );

    // Falls keiner läuft: nächster zukünftiger Kurs
    if (!$aktuellesModul) {
        $aktuellesModul = $kurseMitDatum->first(
            fn ($b) => $b['_start']->gt($heute)
        );
    }

    // Nächstes Modul: direkt nach dem aktuellen in der Timeline
    $naechstesModul = null;
    if ($aktuellesModul) {
        $idx = $kurseMitDatum->search(
            fn ($x) => ($x['baustein_id'] ?? null) === ($aktuellesModul['baustein_id'] ?? null)
        );
        if ($idx !== false && $idx + 1 < $kurseMitDatum->count()) {
            $naechstesModul = $kurseMitDatum->get($idx + 1);
        }
    }

    // Aufräumen der internen Felder und setzen der Public Props
    $strip = function (?array $b) {
        if (!$b) return null;
        unset($b['_start'], $b['_end']);
        return $b;
    };

    $this->aktuellesModul = $strip($aktuellesModul ? (array) $aktuellesModul : null);
    $this->naechstesModul = $strip($naechstesModul ? (array) $naechstesModul : null);

    $this->currentProgress = $this->calcCurrentProgress($this->aktuellesModul);

    // ---------- Summen ----------
    $summen = $raw['summen'] ?? [];
    $unterricht = [
        'tage'      => $summen['u_tage']     ?? null,
        'einheiten' => $summen['u_std']      ?? null,
        'fehltage'  => $summen['fehltage']   ?? null,
        'note'      => $summen['note_lang']  ?? null,
        'schnitt'   => $summen['tn_schnitt'] ?? null,
        'punkte'    => null, // nicht vorhanden
    ];

    // ---------- Maßnahme / Vertrag / Träger ----------
    $massnahme = [
        'titel'    => ($raw['langbez_m'] ?? $raw['langbez_w'] ?? '—')
                      . ' · ' . ($raw['massn_kurz'] ?? '—'),
        'zeitraum' => [
            'von' => $raw['vertrag_beginn'] ?? '—',
            'bis' => $raw['vertrag_ende']   ?? '—',
        ],
        'bausteine'=> $raw['vertrag_baust'] ?? $anzahlBausteine, // nur echte Kurse
        'inhalte'  => ($raw['uform'] ?? $raw['uform_kurz'] ?? '—')
                      . ' · ' . ($raw['vtz_lang'] ?? $raw['vtz'] ?? '—'),
    ];

    $vertrag = [
        'vertrag'         => $raw['vtz']            ?? '—',
        'kennung'         => $raw['massn_kurz']     ?? '—',
        'von'             => $raw['vertrag_beginn'] ?? '—',
        'bis'             => $raw['vertrag_ende']   ?? '—',
        'rechnungsnummer' => $raw['rechnung_nr']    ?? '—',
        'abschlussdatum'  => $raw['vertrag_datum']  ?? '—',
    ];

    $traeger = [
        'institution'     => $raw['mp_langbez'] ?? '—',
        'ansprechpartner' => trim(($raw['mp_vorname'] ?? '') . ' ' . ($raw['mp_nachname'] ?? '')) ?: '—',
        'adresse'         => trim(($raw['mp_plz'] ?? '') . ' ' . ($raw['mp_ort'] ?? '')) ?: '—',
    ];

    $teilnehmerVm = [
        'name'          => $raw['name']          ?? '—',
        'geburt_datum'  => $raw['geburt_datum']  ?? '—',
        'teilnehmer_nr' => $raw['teilnehmer_nr'] ?? '—',
        'kunden_nr'     => $raw['kunden_nr']     ?? '—',
        'stammklasse'   => $raw['stammklasse']   ?? '—',
        'test_punkte'   => $raw['test_punkte']   ?? '—',
        'email_priv'    => $raw['email_priv']    ?? null,
    ];

    $praktikum = [
        'tage'      => $summen['prak_tage'] ?? null,
        'stunden'   => $summen['prak_std']  ?? null,
        'bemerkung' => null,
    ];

    // ---------- Public Props füllen ----------
    $this->bausteine            = $bausteine->all();
    $this->aktuellesModul       = $aktuellesModul ? (array) $aktuellesModul : null;
    $this->naechstesModul       = $naechstesModul ? (array) $naechstesModul : null;
    $this->anzahlBausteine      = (int) $anzahlBausteine;     // nur echte Kurse
    $this->bestandenBausteine   = (int) $bestandenBausteine;  // offene nicht gezählt
    $this->progress             = (int) $progress;

    $this->teilnehmerDaten = [
        'teilnehmer' => $teilnehmerVm,
        'massnahme'  => $massnahme,
        'vertrag'    => $vertrag,
        'traeger'    => $traeger,
        'bausteine'  => $this->bausteine, // für die Liste
        'unterricht' => $unterricht,
        'praktikum'  => $praktikum,
    ];

    // --- Chart-Serien bauen: nur KURSE, nur ABGESCHLOSSEN, Werte als INT ---
    $heute = Carbon::now('Europe/Berlin')->startOfDay();

    $chartBausteine = collect($this->bausteine)
        ->filter(fn ($b) => ($b['typ'] ?? null) === 'kurs')                 // nur echte Kurse
        ->map(function ($b) {                                               // Start/Ende als Carbon
            $b['_start'] = $this->toCarbon($b['beginn'] ?? null);
            $b['_end']   = $this->toCarbon($b['ende']   ?? null);
            return $b;
        })
        ->filter(fn ($b) => $b['_start'] && $b['_end'])                     // nur mit Datum
        ->filter(fn ($b) => $b['_end']->lt($heute))                         // nur abgeschlossen (Ende < heute)
        ->map(function ($b) {                                               // Wert bestimmen + runden
            // Priorität: 'punkte' -> 'schnitt'
            $rawVal = null;
            if (isset($b['punkte']) && is_numeric($b['punkte'])) {
                $rawVal = (float) $b['punkte'];
            } elseif (isset($b['schnitt']) && is_numeric($b['schnitt'])) {
                $rawVal = (float) $b['schnitt'];
            }
            $value = $this->toInt($rawVal);                                 // -> INT oder null

            return [
                'label' => $b['kurzbez'] ?? ($b['baustein'] ?? 'Baustein'),
                'end'   => $b['_end'],
                'value' => $value,
            ];
        })
        ->filter(fn ($x) => !is_null($x['value']))                          // nur mit Wert
        ->sortBy('end')                                                     // chronologisch nach Ende
        ->values()
        ->take(-9);                                                         // z.B. letzte 9

    // Öffentliche Props für Alpine/Apex
    $this->bausteinSerie  = $chartBausteine->pluck('value')->all();        // [78,64,55,...] (ints)
    $this->bausteinLabels = $chartBausteine->map(
        fn ($x) => $x['label'].' · '.$x['end']->format('d.m.')
    )->all();                                                               // ["Modul · 03.10.", ...]
    $this->bausteinColors = array_fill(0, count($this->bausteinSerie), '#2b5c9e'); // optional: einfarbig
}

private function calcCurrentProgress(?array $modul): int
{
    if (!$modul) return 0;

    $start = $this->toCarbon($modul['beginn'] ?? null);
    $end   = $this->toCarbon($modul['ende']   ?? null);
    if (!$start || !$end) return 0;

    // Dauer in Sekunden (Ende >= Start), 0-Dauer absichern
    $total = max(1, $end->endOfDay()->diffInSeconds($start->startOfDay()));
    $now   = Carbon::now('Europe/Berlin');

    // Vor / Nach Zeitraum → 0% / 100%
    if ($now->lt($start)) return 0;
    if ($now->gt($end->endOfDay())) return 100;

    $done = $now->diffInSeconds($start->startOfDay());
    return (int) round(min(100, max(0, ($done / $total) * 100)));
}

    private function detectBausteinTyp(?string $kurzbez): string
    {
        $k = strtoupper((string) $kurzbez);
        return match (true) {
            $k === 'FERI'                         => 'ferien',
            $k === 'PRAK'                         => 'praktikum',
            $k === 'PRUE' || str_starts_with($k, 'PRUE') => 'pruefung',
            default                               => 'kurs',
        };
    }

    private function toInt(?float $v): ?int
    {
        return is_null($v) ? null : (int) round($v);
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class=" animate-pulse">
                <section class="relative ">
                    <div class="mt-4  space-y-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 ">
                            <div class="bg-white h-[160px] shadow rounded-lg text-center col-span-2 md:col-span-1 max-md:order-3 flex items-center justify-center">
                                <div class="w-full space-y-2 p-4">
                                    <div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div>
                                    <div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div>
                                    <div class="h-2 bg-gray-300 rounded-full mb-2.5"></div>
                                </div>
                            </div>
                            <div class="bg-white h-[160px] shadow rounded-lg text-center col-span-2 md:col-span-1 max-md:order-3  flex items-center justify-center">
                                <div class="w-full space-y-2 p-4">
                                    <div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div>
                                    <div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div>
                                    <div class="h-2 bg-gray-300 rounded-full mb-2.5"></div>
                                </div>
                            </div>
                            <div class="bg-white h-[160px] shadow rounded-lg text-center col-span-2 md:col-span-1 max-md:order-3 flex items-center justify-center">
                                <div class="w-full space-y-2  p-4">
                                    <div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div>
                                    <div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div>
                                    <div class="h-2 bg-gray-300 rounded-full mb-2.5"></div>
                                </div>
                            </div>
                            <div class="bg-white h-[160px] shadow rounded-lg text-center col-span-2 md:col-span-1 max-md:order-3 flex items-center justify-center">
                                <div class="w-full space-y-2 p-4">
                                    <div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div>
                                    <div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div>
                                    <div class="h-2 bg-gray-300 rounded-full mb-2.5"></div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2  gap-6 ">
                            <div class="bg-white h-[160px] shadow rounded-lg text-center col-span-2 md:col-span-1 max-md:order-3 flex items-center justify-center">
                                <div class="w-full space-y-2 p-4">
                                    <div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div>
                                    <div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div>
                                    <div class="h-2 bg-gray-300 rounded-full mb-2.5"></div>
                                </div>
                            </div>
                            <div class="bg-white h-[160px] shadow rounded-lg text-center col-span-2 md:col-span-1 max-md:order-3 flex items-center justify-center">
                                <div class="w-full space-y-2 p-4">
                                    <div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div>
                                    <div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div>
                                    <div class="h-2 bg-gray-300 rounded-full mb-2.5"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        HTML;
    }

    public function render()
    {
        // Optional: explizit an das Blade übergeben (oder direkt über Public Props nutzen)
        return view('livewire.user.program-show', [
            'user'                => $this->userData,
            'data'                => $this->raw,
            'teilnehmerDaten'     => $this->teilnehmerDaten,
            'bausteine'           => $this->bausteine,
            'aktuellesModul'      => $this->aktuellesModul,
            'naechstesModul'      => $this->naechstesModul,
            'anzahlBausteine'     => $this->anzahlBausteine,
            'bestandenBausteine'  => $this->bestandenBausteine,
            'progress'            => $this->progress,
            'bausteinSerie'            => $this->bausteinSerie,
            'bausteinLabels'            => $this->bausteinLabels,
            'bausteinColors'            => $this->bausteinColors,
        ]);
    }
}
