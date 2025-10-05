<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

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

    /** Listener */
    protected $listeners = ['refreshParent' => '$refresh'];

    public function mount(): void
    {
        $this->userData = Auth::user();
        if (! $this->userData?->person?->last_api_update || $this->userData?->person?->last_api_update->lt(now()->subHours(1))) {
            $this->userData?->person?->apiupdate();
        }
        $this->raw = $this->userData?->person?->programdata ?? [];

        $this->buildViewModelFromRaw($this->raw);
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

    /**
     * ViewModel aufbauen (entspricht deinem @php-Block)
     */
    private function buildViewModelFromRaw(array $raw): void
    {
        // ---------- Bausteine normalisieren ----------
        $bausteine = collect($raw['tn_baust'] ?? [])->map(function ($b) {
            return [
                'baustein_id'        => $b['baustein_id'] ?? null,
                'block'              => null, // nicht vorhanden in Rohdaten
                'abschnitt'          => null, // nicht vorhanden in Rohdaten
                'beginn'             => $b['beginn_baustein'] ?? null,
                'ende'               => $b['ende_baustein'] ?? null,
                'tage'               => $this->num($b['baustein_tage'] ?? null),
                'unterrichtsklasse'  => $b['klassen_co_ks'] ?? null,
                'baustein'           => $b['langbez'] ?? ($b['kurzbez'] ?? '—'),
                'kurzbez'            => $b['kurzbez'] ?? null,
                // "schnitt" in deinem alten View => wir nehmen TN-Punkte
                'schnitt'            => $this->num($b['tn_punkte'] ?? null),
                'punkte'             => $this->num($b['tn_punkte'] ?? null),
                'fehltage'           => $this->num($b['fehltage'] ?? null),
                'klassenschnitt'     => $this->num($b['klassenschnitt'] ?? null),
            ];
        });

        // Für Fortschritt FERI/PRUE/PRAK ausklammern (du hattest FERI & PRAK; PRUE war nur im Kommentar)
        $isZaehlbar = fn (?string $k) => !in_array($k, ['FERI', 'PRAK'], true);
        $bausteineProgress = $bausteine->filter(fn ($b) => $isZaehlbar($b['kurzbez'] ?? ''));

        $anzahlBausteine = $bausteineProgress->count();
        $bestandenBausteine = $bausteineProgress
            ->filter(function ($b) {
                $v = $b['schnitt'];
                return is_numeric($v) ? $v >= 50 : false;
            })
            ->count();

        // Aktuelles Modul = erstes ohne numerischen "schnitt", sonst letztes
        $aktuellesModul = $bausteineProgress->first(fn ($b) => !is_numeric($b['schnitt'])) ?? $bausteineProgress->last();

        // Nächstes Modul
        $naechstesModul = null;
        if ($aktuellesModul) {
            $nextIdx = $bausteineProgress->search($aktuellesModul) + 1;
            if ($nextIdx && $nextIdx < $anzahlBausteine) {
                $naechstesModul = $bausteineProgress->values()->get($nextIdx);
            }
        }

        $progress = $anzahlBausteine ? (int) round(($bestandenBausteine / $anzahlBausteine) * 100) : 0;

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
            'bausteine'=> $raw['vertrag_baust'] ?? $anzahlBausteine,
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
        $this->anzahlBausteine      = (int) $anzahlBausteine;
        $this->bestandenBausteine   = (int) $bestandenBausteine;
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
        ]);
    }
}
