<div wire:loading.class="cursor-wait opacity-50 animate-pulse" class="transition">
  <section class="relative space-y-6">
  @php
    // ----- Rohdaten (direkt aus deinem JSON) -----
    $raw = $teilnehmerDaten ?? [];

    // Utility: Numeric or null
    $num = function ($v) {
        if (is_numeric($v)) return $v + 0;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === 'passed') return 100;   // treat "passed" as 100%
            if ($v === 'not att' || $v === '---' || $v === '-') return null;
            if (is_numeric($v)) return $v + 0;
        }
        return null;
    };

    // Bausteine normalisieren (FERI aus Fortschritt rausnehmen)
    $bausteine = collect($raw['tn_baust'] ?? [])
        ->map(function ($b) use ($num) {
            return [
                'block'    => null, // nicht vorhanden in Rohdaten
                'abschnitt'=> null, // nicht vorhanden in Rohdaten
                'beginn'   => $b['beginn_baustein'] ?? null,
                'ende'     => $b['ende_baustein'] ?? null,
                'tage'     => $num($b['baustein_tage'] ?? null),
                'unterrichtsklasse' => $b['klassen_co_ks'] ?? null,
                'baustein' => $b['langbez'] ?? ($b['kurzbez'] ?? '—'),
                'kurzbez'  => $b['kurzbez'] ?? null,
                // "schnitt" in deinem alten View => wir nehmen TN-Punkte
                'schnitt'  => $num($b['tn_punkte'] ?? null),
                'punkte'   => $num($b['tn_punkte'] ?? null),
                'fehltage' => $num($b['fehltage'] ?? null),
                'klassenschnitt' => $num($b['klassenschnitt'] ?? null),
            ];
        });

    // Für Fortschritt FERI/PRUE/PRAK ausklammern
    $isZaehlbar = fn ($k) => !in_array($k, ['FERI','PRAK']);
    $bausteineProgress = $bausteine->filter(fn ($b) => $isZaehlbar($b['kurzbez'] ?? ''));

    $anzahlBausteine = $bausteineProgress->count();
    $bestandenBausteine = $bausteineProgress->filter(function ($b) {
        $v = $b['schnitt'];
        return is_numeric($v) ? $v >= 50 : false;
    })->count();

    // Aktuelles Modul = erstes ohne Ergebnis (kein numerischer "schnitt"), sonst letztes
    $aktuellesModul = $bausteineProgress->first(fn ($b) => !is_numeric($b['schnitt'])) ?? $bausteineProgress->last();

    // Nächstes Modul
    $nextIdx = $aktuellesModul ? $bausteineProgress->search($aktuellesModul) + 1 : null;
    $naechstesModul = ($nextIdx && $nextIdx < $anzahlBausteine) ? $bausteineProgress->values()->get($nextIdx) : null;

    $progress = $anzahlBausteine ? round(($bestandenBausteine / $anzahlBausteine) * 100) : 0;

    // Unterrichts-Summen aus $raw['summen']
    $summen = $raw['summen'] ?? [];
    $unterricht = [
        'tage'      => $summen['u_tage']      ?? null,
        'einheiten' => $summen['u_std']       ?? null,
        'fehltage'  => $summen['fehltage']    ?? null,
        'note'      => $summen['note_lang']   ?? null,
        'schnitt'   => $summen['tn_schnitt']  ?? null,
        'punkte'    => null, // nicht vorhanden – Feld im View kann bleiben, zeigt "—"
    ];

    // Maßnahme / Vertrag / Träger aus Top-Level ableiten
    $massnahme = [
        'titel'    => ($raw['langbez_m'] ?? $raw['langbez_w'] ?? '—')
                    . ' · ' . ($raw['massn_kurz'] ?? '—'),
        'zeitraum' => [
            'von' => $raw['vertrag_beginn'] ?? '—',
            'bis' => $raw['vertrag_ende']   ?? '—',
        ],
        'bausteine'=> $raw['vertrag_baust'] ?? count($bausteineProgress),
        'inhalte'  => ($raw['uform'] ?? $raw['uform_kurz'] ?? '—') . ' · ' . ($raw['vtz_lang'] ?? $raw['vtz'] ?? '—'),
    ];

    $vertrag = [
        'vertrag'        => $raw['vtz']           ?? '—',
        'kennung'        => $raw['massn_kurz']    ?? '—',
        'von'            => $raw['vertrag_beginn']?? '—',
        'bis'            => $raw['vertrag_ende']  ?? '—',
        'rechnungsnummer'=> $raw['rechnung_nr']   ?? '—',
        'abschlussdatum' => $raw['vertrag_datum'] ?? '—',
    ];

    $traeger = [
        'institution'   => $raw['mp_langbez'] ?? '—',
        'ansprechpartner'=> trim(($raw['mp_vorname'] ?? '') . ' ' . ($raw['mp_nachname'] ?? '')) ?: '—',
        'adresse'       => trim(($raw['mp_plz'] ?? '') . ' ' . ($raw['mp_ort'] ?? '')) ?: '—',
    ];

    $teilnehmerVm = [
        'name'          => $raw['name']          ?? '—',
        'geburt_datum'  => $raw['geburt_datum']  ?? '—',
        'teilnehmer_nr' => $raw['teilnehmer_nr'] ?? '—',
        'kunden_nr'     => $raw['kunden_nr']     ?? '—',
        'stammklasse'   => $raw['stammklasse']   ?? '—',
        'test_punkte'   => $raw['test_punkte']   ?? '—',
        'email_priv'    => $raw['email_priv']    ?? null,
      // weitere Felder bei Bedarf…
    ];

    // ===== Werte an das bestehende View binden =====
    // Dein View greift aktuell auf $teilnehmerDaten['…'] zu – wir überschreiben dafür lokal:
    $teilnehmerDaten = [
        'teilnehmer' => $teilnehmerVm,
        'massnahme'  => $massnahme,
        'vertrag'    => $vertrag,
        'traeger'    => $traeger,
        'bausteine'  => $bausteine->all(), // für die Liste
        'unterricht' => $unterricht,
        'praktikum'  => [
            'tage'    => $summen['prak_tage'] ?? null,
            'stunden' => $summen['prak_std']  ?? null,
            'bemerkung' => null,
        ],
    ];

    // Variablen, die du später im View nutzt:
    $anzahlBloecke = null; // nicht vorhanden in Daten
  @endphp



    <div class="mt-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6 ">
        <div class="bg-white shadow rounded-lg text-center col-span-2 md:col-span-1 max-md:order-3">
            <div class="card-body pr-2  grid place-content-stretch h-full">
                <div>
                    <div class="relative h-full grid place-content-stretch grid-cols-12">
                        <div class="col-span-12 text-left pt-5 pl-5  grid place-content-stretch">
                            <span class="text-gray-700 ">Bausteine</span>
                            <h4 class="my-2 font-medium text-gray-800 text-21 ">
                                <span class="counter-value" data-target="{{ $anzahlBausteine }}">{{ $anzahlBausteine }}</span>
                                
                            </h4>
                            <div class="flex items-center space-x-2 mt-1  pb-2 ">
                              <span
                                  class="text-sm py-[1px] px-1 bg-green-500/30 text-green-700 rounded font-medium ">{{ $bestandenBausteine }}</span>
                              <span class="ml-1.5 text-gray-700 text-[11px]">bestanden</span>
                          </div>
                        </div>
                        <div class="absolute inset-y-0  right-1 left-10 flex justify-end  items-center h-full">
                            <div
                                x-data="{
                                    chart: null,
                                    series: [75,65,55,65,65,75,55,75,55],
                                    colors: ['#2b5c9e','#2b5c9e','#2b5c9e','#2b5c9e','#2b5c9e','#2b5c9e','#2b5c9e','#2b5c9e','#2b5c9e'],
                                    pickColor(val){ if(!val) return null; const v=String(val).trim(); return v.startsWith('--') ? (getComputedStyle(document.documentElement).getPropertyValue(v).trim() || v) : v; },
                                    init(){
                                    const options = {
                                        series: [{ data: this.series }],
                                        chart: { type: 'bar', height: 60, width: 120, sparkline: { enabled: true } },
                                        colors: this.colors.map(this.pickColor),
                                        stroke: { curve: 'smooth', width: 2 },
                                        tooltip: {
                                        fixed: { enabled: false },
                                        x: { title: { formatter: () => 'Baustein' } },
                                        y: { title: { formatter: () => 'Punkte' } },
                                        marker: { show: true }
                                        }
                                    };
                                    this.chart = new ApexCharts(this.$el, options);
                                    this.chart.render();

                                    }
                                }"
                                class="apex-charts flex justify-end items-center"
                                wire:ignore
                                ></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
      
        <div class="bg-white shadow rounded-lg p-5 text-left col-span-1 max-md:order-1">
          <p class=" text-gray-700">Endergebnis&nbsp;Ø</p>
            <div
            x-data="{
                chart: null,
                // deine Werte / Props (unverändert, nur bereitgehalten)
                value: 85,
                size: 140,
                start: -130,
                end: 130,
                trackBg: '#f3f4f6',
                dashed: true,
                gradient: ['red','green'],


                init() {

                var options = {
                    chart: {
                        height: 140,
                        type: 'radialBar',
                        offsetY: -10
                    },
                    plotOptions: {
                        radialBar: {
                            startAngle: -130,
                            endAngle: 130,
                            dataLabels: {
                                name: { show: false },
                                value: {
                                    offsetY: 10,
                                    fontSize: '16px',
                                    color: undefined,
                                    formatter: function (val) { return val + '%'; }
                                }
                            }
                        }
                    },
                    colors: ['#f8b8b8'],
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shade: 'dark',
                            type: 'horizontal',
                            gradientToColors: ['#2fa541'],
                            shadeIntensity: 0.15,
                            inverseColors: false,
                            opacityFrom: 1,
                            opacityTo: 1,
                            stops: [20, 60]
                        },
                    },
                    stroke: { dashArray: 4 },
                    legend: { show: false },
                    series: [85],
                    labels: ['Series A'],
                };

                // Render (wie bei dir, mit ID-Selector)
                this.chart = new ApexCharts(
                    document.querySelector('#invested-overview'),
                    options
                );
                this.chart.render();
                // ================================================

                // Cleanup ohne Einfluss auf Darstellung
                this.$el.addEventListener('alpine:destroy', () => { this.chart?.destroy(); });
                }
            }"

            id="invested-overview"
            class="apex-charts max-h-28 md:max-h-24 !min-h-10 overflow-hidden"
            wire:ignore
            ></div>


                    </div>
                    <div class="bg-white shadow rounded-lg p-5 text-left col-span-1 max-md:order-2">
                     
                    <p class="text-gray-700">Fortschritt</p>
                    <div
            x-data="{
                chart: null,
                init() {
                // ====== deine Einstellungen (unverändert) ======
                var overallOptions = {
                    chart: {
                    height: 140,
                    type: 'radialBar',
                    offsetY: -10
                    },
                    series: [{{ $progress }}], // 0–100
                    labels: ['Fortschritt'],
                    colors: ['#2563eb'],
                    plotOptions: {
                    radialBar: {
                        startAngle: -130,
                        endAngle: 130,
                        hollow: { size: '55%' },
                        track: { background: '#f3f4f6' },
                        dataLabels: {
                        name: { show: false },
                        value: {
                            offsetY: 5,
                            fontSize: '16px',
                            formatter: function (val) {
                            return Math.round(val) + '%';
                            }
                        }
                        }
                    }
                    },
                    fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'light',
                        type: 'horizontal',
                        gradientToColors: ['#60a5fa' , '#2b5c9e'],
                        stops: [0, 100],
                        opacityFrom: 1,
                        opacityTo: 1
                    }
                    },
                    stroke: { lineCap: 'round' }
                };

                this.chart = new ApexCharts(
                    document.querySelector('#overall-progress'),
                    overallOptions
                );
                this.chart.render();
                // ================================================

                // Cleanup für Alpine
                this.$el.addEventListener('alpine:destroy', () => { this.chart?.destroy(); });
                }
            }"

            id="overall-progress"
            class="apex-charts   max-h-28 md:max-h-24 !min-h-10 overflow-hidden"
            ></div>

        </div>
        <div class="bg-primary shadow rounded-lg p-5 text-left col-span-1 max-md:order-2">
                      <livewire:user.program.program-pdf-modal />

                      <x-button x-data @click="$dispatch('open-program-pdf')">
                          Programm als PDF
                      </x-button>
                    

        </div>
      </div>
    </div>

    {{-- Ergebnisse + Aktuelles Modul --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      {{-- Ergebnisse nach Kurs (Bausteine) --}}
      <div class="bg-white shadow rounded-lg  overflow-hidden border border-gray-300">
        <h3 class="text-lg font-semibold bg-sky-100 p-5">Bausteine</h3>
        <ul class="divide-y divide-gray-200 max-h-[280px] overflow-y-auto scroll-container border border-gray-300 rounded-b-lg overflow-hidden bg-white snap-y touch-pan-y scroll-smooth" 
        >
          {{-- Bausteine --}}
          @forelse($bausteine as $b)
            @php
              $status = '—';
              $statusClass = 'text-gray-500';
              if (!is_null($b['schnitt'] ?? null)) {
                  if (($b['schnitt'] ?? 0) >= 50) { $status = 'Bestanden'; $statusClass = 'text-green-600 bg-green-50'; }
                  else { $status = 'Nicht bestanden'; $statusClass = 'text-red-600 bg-red-50'; }
              } else {
                  $status = 'offen';
                  $statusClass = 'text-blue-600 bg-blue-50';
              }
            @endphp
            <li class="relative h-[70px] py-3 px-4 even:bg-white odd:bg-gray-100 hover:bg-blue-100 cursor-pointer hover:pr-[45px] transition-all  delay-50 duration-500 group snap-start ">
              <div class="grid grid-cols-12 gap-1 ">
                <div class="col-span-8 font-medium text-gray-800">
                  <div class="truncate ">{{ $b['baustein'] }}</div>
                </div>
                <div class="col-span-4 text-right">
                  <span class="px-2 py-1 text-xs font-medium rounded badge  {{ $statusClass }}">{{ $status }}</span>
                </div>
              </div>
              <div class="grid grid-cols-12 gap-1 ">
                <div class="col-span-6 font-medium text-gray-800">
                  <span class="px-2 py-1 text-xs font-medium rounded badge bg-gray-50 text-gray-500">Block {{ $b['block'] }} · Abschnitt {{ $b['abschnitt'] }}</span>
                </div>
                <div class="col-span-6 text-right">
                  <span class="text-xs text-gray-500 w-max">{{ \Illuminate\Support\Carbon::parse($b['beginn'])->locale('de')->isoFormat('DD.MM.YYYY') }}&nbsp;–&nbsp;{{ \Illuminate\Support\Carbon::parse($b['ende'])->locale('de')->isoFormat('DD.MM.YYYY') }}</span>
                </div>
              </div>
              <div class="absolute h-[70px] right-2 top-0 flex items-center opacity-0 translate-x-5 group-hover:opacity-100 group-hover:translate-x-0 transition-all  delay-50 duration-500 text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg"  class="h-6   mr-1 max-md:mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
              </div>
            </li>
          @empty
            <li class="p-5 text-gray-500">Keine Bausteine vorhanden.</li>
          @endforelse
        </ul>
      </div>

      {{-- Aktueller Baustein --}}
      <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-300 grid grid-rows-[auto_1fr]">
        <h3 class="text-lg font-semibold bg-sky-100 p-5 h-max border-b border-gray-300">Aktueller Baustein</h3>
        <div class="p-5  flex flex-col justify-between place-self-stretch">
          {{-- Aktuelles Modul --}}
          @if($aktuellesModul)
              <p class="font-medium text-gray-800">{{ $aktuellesModul['baustein'] }}</p>
<p class="text-sm text-gray-600">
    {{ \Illuminate\Support\Carbon::parse($aktuellesModul['beginn'])->locale('de')->isoFormat('DD.MM.YYYY') }}
    –
    {{ \Illuminate\Support\Carbon::parse($aktuellesModul['ende'])->locale('de')->isoFormat('DD.MM.YYYY') }}
    · {{ $aktuellesModul['tage'] }} Tage
</p>
  
              {{-- Gesamt-Fortschritt über alle Bausteine --}}
              <div class="mt-2">
                <div class="w-full bg-gray-200 rounded-full h-3">
                  <div class="bg-blue-600 h-3 rounded-full" style="width: {{ $progress }}%"></div>
                </div>
                <p class="text-sm text-gray-600 mt-1">Fortschritt: {{ $progress }}%</p>
              </div>
              <div>
                <x-button class="">
                  Details
                  <svg xmlns="http://www.w3.org/2000/svg"  class="h-4   ml-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                </x-button>
              </div>
          @else
            <p class="text-gray-500">Kein aktuelles Modul ermittelbar.</p>
          @endif
        </div>
      </div>
    </div>

<!-- Accordion Wrapper -->
<div x-data="{ open: 'unterricht' }" class="space-y-4">

<!-- Panel: Teilnehmerdaten -->
<section class="bg-white rounded-lg shadow border border-gray-200">
  <button
    @click="open = (open === 'teilnehmer' ? null : 'teilnehmer')"
    :aria-expanded="open === 'teilnehmer'"
    class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition"
  >
    <span class="text-base font-semibold text-gray-800">Teilnehmerdaten</span>
    <svg class="w-5 h-5 text-gray-500 transition-transform duration-300"
         :class="open==='teilnehmer' && 'rotate-180'"
         viewBox="0 0 20 20" fill="currentColor">
      <path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/>
    </svg>
  </button>
  <div x-show="open === 'teilnehmer'" x-collapse>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm px-6 pb-6">
      <div><dt class="text-gray-600">Name</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['teilnehmer']['name'] }}</dd></div>
      <div><dt class="text-gray-600">Geburtsdatum</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['teilnehmer']['geburt_datum'] }}</dd></div>
      <div><dt class="text-gray-600">Teilnehmer-Nr</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['teilnehmer']['teilnehmer_nr'] }}</dd></div>
      <div><dt class="text-gray-600">Kunden-Nr</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['teilnehmer']['kunden_nr'] }}</dd></div>
      <div><dt class="text-gray-600">Stammklasse</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['teilnehmer']['stammklasse'] }}</dd></div>
      <div><dt class="text-gray-600">Eignungstest</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['teilnehmer']['test_punkte'] }}</dd></div>

    </dl>
  </div>
</section>

<!-- Panel: Maßnahme -->
<section class="bg-white rounded-lg shadow border border-gray-200">
  <button @click="open = (open === 'massnahme' ? null : 'massnahme')"
          :aria-expanded="open === 'massnahme'"
          class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
    <span class="text-base font-semibold text-gray-800">Maßnahme</span>
    <svg class="w-5 h-5 text-gray-500 transition-transform duration-300" :class="open==='massnahme' && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/></svg>
  </button>
  <div x-show="open === 'massnahme'" x-collapse>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm px-6 pb-6">
      <div><dt class="text-gray-600">Titel</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['massnahme']['titel'] }}</dd></div>
      <div><dt class="text-gray-600">Zeitraum</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['massnahme']['zeitraum']['von'] }} – {{ $teilnehmerDaten['massnahme']['zeitraum']['bis'] }}</dd></div>
      <div><dt class="text-gray-600">Bausteine</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['massnahme']['bausteine'] }}</dd></div>
      <div><dt class="text-gray-600">Inhalte</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['massnahme']['inhalte'] }}</dd></div>
    </dl>
  </div>
</section>

<!-- Panel: Vertrag -->
<section class="bg-white rounded-lg shadow border border-gray-200">
  <button
    @click="open = (open === 'vertrag' ? null : 'vertrag')"
    :aria-expanded="open === 'vertrag'"
    class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition"
  >
    <span class="text-base font-semibold text-gray-800">Vertrag</span>
    <svg class="w-5 h-5 text-gray-500 transition-transform duration-300"
         :class="open==='vertrag' && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor">
      <path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/>
    </svg>
  </button>
  <div x-show="open === 'vertrag'" x-collapse>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm px-6 pb-6">
      <div><dt class="text-gray-600">Vertrag</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['vertrag']['vertrag'] }}</dd></div>
      <div><dt class="text-gray-600">Kennung</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['vertrag']['kennung'] }}</dd></div>
      <div><dt class="text-gray-600">Von</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['vertrag']['von'] }}</dd></div>
      <div><dt class="text-gray-600">Bis</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['vertrag']['bis'] }}</dd></div>
      <div><dt class="text-gray-600">Rechnungsnummer</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['vertrag']['rechnungsnummer'] }}</dd></div>
      <div><dt class="text-gray-600">Abschlussdatum</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['vertrag']['abschlussdatum'] }}</dd></div>
    </dl>
  </div>
</section>

<!-- Panel: Maßnahmenträger -->
<section class="bg-white rounded-lg shadow border border-gray-200">
  <button
    @click="open = (open === 'traeger' ? null : 'traeger')"
    :aria-expanded="open === 'traeger'"
    class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition"
  >
    <span class="text-base font-semibold text-gray-800">Maßnahmenträger</span>
    <svg class="w-5 h-5 text-gray-500 transition-transform duration-300"
         :class="open==='traeger' && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor">
      <path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/>
    </svg>
  </button>
  <div x-show="open === 'traeger'" x-collapse>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm px-6 pb-6">
      <div class="md:col-span-1"><dt class="text-gray-600">Institution</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['traeger']['institution'] }}</dd></div>
      <div class="md:col-span-1"><dt class="text-gray-600">Ansprechpartner</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['traeger']['ansprechpartner'] }}</dd></div>
      <div class="md:col-span-2"><dt class="text-gray-600">Adresse</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['traeger']['adresse'] }}</dd></div>
    </dl>
  </div>
</section>

<!-- Panel: Praktikum -->
<section class="bg-white rounded-lg shadow border border-gray-200">
  <button
    @click="open = (open === 'praktikum' ? null : 'praktikum')"
    :aria-expanded="open === 'praktikum'"
    class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition"
  >
    <span class="text-base font-semibold text-gray-800">Praktikum</span>
    <svg class="w-5 h-5 text-gray-500 transition-transform duration-300"
         :class="open==='praktikum' && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor">
      <path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/>
    </svg>
  </button>
  <div x-show="open === 'praktikum'" x-collapse>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm px-6 pb-6">
      <div><dt class="text-gray-600">Tage</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['praktikum']['tage'] }}</dd></div>
      <div><dt class="text-gray-600">Stunden</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['praktikum']['stunden'] }}</dd></div>
      <div class="md:col-span-2"><dt class="text-gray-600">Bemerkung</dt><dd class="font-medium text-gray-900">{{ $teilnehmerDaten['praktikum']['bemerkung'] ?? '—' }}</dd></div>
    </dl>
  </div>
</section>

  <!-- Panel: Unterricht -->
  <section class="bg-white rounded-lg shadow-lg">
    <button
      @click="open = (open === 'unterricht' ? null : 'unterricht')"
      :aria-expanded="open === 'unterricht'"
      class="w-full flex items-center justify-between px-6 py-4 text-left"
    >
      <span class="text-xl font-semibold">Unterricht</span>
      <svg class="w-5 h-5 transition-transform" :class="open==='unterricht' && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/></svg>
    </button>
        <div x-show="open === 'unterricht'" x-collapse>
        <div class="px-6 pb-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-24  text-sm">
            
            <!-- Linke Spalte -->
            <ul class="divide-y divide-gray-100">
                <li class="flex items-center justify-between py-2">
                <span class="text-gray-600">Tage</span>
                <span class="font-medium text-gray-900">{{ $teilnehmerDaten['unterricht']['tage'] }}</span>
                </li>
                <li class="flex items-center justify-between py-2">
                <span class="text-gray-600">Einheiten</span>
                <span class="font-medium text-gray-900">{{ $teilnehmerDaten['unterricht']['einheiten'] }}</span>
                </li>
                <li class="flex items-center justify-between py-2">
                <span class="text-gray-600">Fehltage</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">
                    {{ $teilnehmerDaten['unterricht']['fehltage'] }}
                </span>
                </li>
            </ul>

            <!-- Rechte Spalte -->
            <ul class="divide-y divide-gray-100">
                <li class="flex items-center justify-between py-2">
                <span class="text-gray-600">Note</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                    {{ $teilnehmerDaten['unterricht']['note'] }}
                </span>
                </li>
                <li class="flex items-center justify-between py-2">
                <span class="text-gray-600">Schnitt</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">
                    {{ $teilnehmerDaten['unterricht']['schnitt'] }} %
                </span>
                </li>
                <li class="flex items-center justify-between py-2">
                <span class="text-gray-600">Punkte</span>
                <span class="font-medium text-gray-900">{{ $teilnehmerDaten['unterricht']['punkte'] }}</span>
                </li>
            </ul>

            </div>
        </div>
        </div>


        </section>
        </div>

  </section>

</div>
