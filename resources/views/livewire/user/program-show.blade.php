<div
  class="transition"
  @if($apiProgramLoading)
    wire:poll.visible.1000="pollProgram"
  @else
    wire:poll.visible.5000
  @endif
  wire:loading.class="cursor-wait opacity-50 animate-pulse"
>    {{-- Loader wenn Programm noch nicht geladen --}}
    @if($apiProgramLoading)
        <div role="status" class="h-32 w-full relative animate-pulse">
            <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 transition-opacity">
                <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                    <span class="loader"></span>
                    <span class="text-sm text-gray-700">wird geladen…</span>
                </div>
            </div>
        </div>
    @else
  <livewire:user.program.program-pdf-modal lazy />
  <livewire:user.program.course.course-rating-form-modal lazy />

  <section class="relative space-y-6">
    <div class="mt-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6 ">
        <div class="bg-white shadow rounded-lg text-center col-span-2 md:col-span-1 max-md:order-4">
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
                        <div class="absolute inset-y-0  right-1 left-10 flex justify-end  items-center h-full" >
                          <div
                            x-data="{
                              chart: null,
                              series: @js($bausteinSerie),
                              labels: @js($bausteinLabels),
                              colors: @js($bausteinColors),

                              pickColor(val){
                                if(!val) return null;
                                const v = String(val).trim();
                                return v.startsWith('--')
                                  ? (getComputedStyle(document.documentElement).getPropertyValue(v).trim() || v)
                                  : v;
                              },

                              buildOptions(){
                                return {
                                  series: [{ data: this.series }],
                                  chart: { type: 'bar', height: 60, width: 120, sparkline: { enabled: true } },
                                  colors: (this.colors?.length ? this.colors.map(c => this.pickColor(c)) : ['#2b5c9e']),
                                  plotOptions: { bar: { columnWidth: '70%', borderRadius: 3 } },
                                  dataLabels: { enabled: false },
                                  stroke: { width: 2 },
                                  tooltip: {
                                    y: {
                                      title: { formatter: () => 'Punkte' },
                                      formatter: (val) => (val ?? 0) + ' Pkt.'
                                    },
                                    x: { formatter: (val, { dataPointIndex }) => this.labels?.[dataPointIndex] ?? 'Baustein' }
                                  }
                                };
                              },

                              init(){
                                // Debug bei Bedarf:
                                // console.log('Serie:', this.series, 'Labels:', this.labels, 'Colors:', this.colors);

                                if (this.chart) { this.chart.destroy(); this.chart = null; }
                                this.chart = new ApexCharts(this.$el, this.buildOptions());
                                this.chart.render();

                                // Reaktiv bei Livewire-Updates (falls Props dynamisch neu kommen)
                                this.$watch('series', (v) => { if (this.chart) this.chart.updateSeries([{ data: v }], true); });
                                this.$watch('labels', () =>  { if (this.chart) this.chart.updateOptions({ tooltip: this.buildOptions().tooltip }, true, true); });
                                this.$watch('colors', (v) => { if (this.chart) this.chart.updateOptions({ colors: v.map(c => this.pickColor(c)) }, true, true); });
                              }
                            }"
                            x-init="init()"
                            wire:ignore
                            class="apex-charts flex justify-end items-center"
                          ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-white shadow rounded-lg p-5 text-left col-span-1 max-md:order-2">
          <p class=" text-gray-700">Endergebnis&nbsp;Ø</p>
            <div
            x-data="{
                chart: null,
                // deine Werte / Props (unverändert, nur bereitgehalten)
                value: @js($teilnehmerDaten['unterricht']['schnitt'] ?? 0),
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
                    series: [@js($teilnehmerDaten['unterricht']['schnitt'] ?? 0)],
                    labels: ['Series A'],
                };
                // Cleanup (wie bei dir, ohne Einfluss auf Darstellung)
                if (this.chart) { this.chart.destroy(); this.chart = null; }
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
            x-init="init()"
            wire:ignore
            ></div>
                    </div>
                    <div class="bg-white shadow rounded-lg p-5 text-left col-span-1 max-md:order-3">
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
                // Cleanup (wie bei dir, ohne Einfluss auf Darstellung)
                if (this.chart) { this.chart.destroy(); this.chart = null; }
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
            x-init="init()"
            id="overall-progress"
            class="apex-charts   max-h-28 md:max-h-24 !min-h-10 overflow-hidden"
            ></div>

        </div>
        <div
          class="bg-white shadow rounded-lg p-5 text-left col-span-2 md:col-span-1 max-md:order-1"
          x-data="{
            swiper: null,
            initSwiper() {
              const el = this.$refs.swiper;
              const opts = {
                slidesPerView: 1,
                spaceBetween: 12,
                loop: true,
                speed: 500,
                autoHeight: false,
                keyboard: { enabled: true },
                autoplay: {
                  delay: 5000,
                  disableOnInteraction: false   // wichtig: nicht dauerhaft stoppen
                },
                pagination: {
                  el: this.$refs.pagination,
                  clickable: true
                },
                // in Livewire/Alpine-Umgebungen hilfreich:
                observer: true,
                observeParents: true
              };

              const boot = () => { this.swiper = new Swiper(el, opts); };

              // Falls das CDN noch nicht geladen ist, warten wir einmal auf window.load
              if (window.Swiper) boot();
              else window.addEventListener('load', () => window.Swiper && boot(), { once: true });

              // Aufräumen, wenn der Knoten entfernt wird (z.B. durch Livewire-Updates)
              this.$el.addEventListener('alpine:destroy', () => { this.swiper?.destroy(true, true); });
            },
            stopSwiper() { this.swiper?.autoplay?.stop(); },
            startSwiper() { this.swiper?.autoplay?.start(); }
          }"
          x-init="initSwiper()"
          x-on:mouseenter="stopSwiper()"
          x-on:mouseleave="startSwiper()"
          class="relative w-full"
          wire:ignore
          wire:loading.class="hidden"
        >
          <div class="relative h-full pb-2">
            <div class="swiper h-full" x-ref="swiper">
              <div class="swiper-wrapper h-full">
                <div class="swiper-slide">
                  <div class="grid h-full grid-cols-1 place-content-stretch">
                    <h3 class="text-gray-800 font-semibold mb-1">Qualiprogramm als PDF</h3>
                    <p class="text-xs text-gray-600 mb-2">
                      Lade dein aktuelles Qualifizierungsprogramm als PDF herunter.
                    </p>
                    <x-buttons.button-basic :size="'sm'" :mode="'primary'" @click="$dispatch('open-program-pdf');isClicked = true; setTimeout(() => isClicked = false, 100)" class="w-full">
                      Programm als PDF
                    </x-buttons.button-basic>
                  </div>
                </div>

                <div class="swiper-slide">
                  <div class="grid h-full grid-cols-1 place-content-stretch">
                    <h3 class="text-gray-800 font-semibold mb-1">Anträge</h3>
                    <p class="text-xs text-gray-600 mb-2">
                      Übersicht über deine Anträge im Programm.
                    </p>
                    <x-buttons.button-basic :size="'sm'" :mode="'primary'"     @click="selectedTab = 'claims'"
                       class="w-full">
                      Anträge anzeigen
                    </x-buttons.button-basic>
                  </div>
                </div>

                <div class="swiper-slide">
                  <div class="grid h-full grid-cols-1 place-content-stretch">
                    <h3 class="text-gray-800 font-semibold mb-1">Baustein Dokumentation</h3>
                    <p class="text-xs text-gray-600 mb-2">
                      Übersicht über deine Bausteine exportieren.
                    </p>
                    <x-buttons.button-basic :size="'sm'" :mode="'primary'"     
                        @click="$dispatch('toast', { 
                            message: 'Baustein Dokumentation ist noch in der Entwicklung....', 
                            type: 'info' 
                        });" 
                        class="w-full">
                      anzeigen
                    </x-buttons.button-basic>
                  </div>
                </div>



              </div>
            </div>
            <!-- If we need pagination -->
            <div class="swiper-pagination !-bottom-4" x-ref="pagination"></div>
          </div>
        </div>
      </div>
    </div>

    {{-- Ergebnisse + Aktuelles Modul --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      {{-- Ergebnisse nach Kurs (Bausteine) --}}
      <div class="bg-white shadow rounded-lg  overflow-hidden border border-gray-300">
        <h3 class="text-lg font-semibold bg-sky-100 p-5">Bausteine</h3>
<ul
  class="divide-y divide-gray-200 max-h-[280px] overflow-y-auto scroll-container border border-gray-300 rounded-b-lg overflow-hidden bg-white snap-y touch-pan-y scroll-smooth"
  x-data="{
    // relative Top-Position des Elements innerhalb des Scrollers berechnen
    _relTop(el, scroller) {
      const elRect = el.getBoundingClientRect();
      const scRect = scroller.getBoundingClientRect();
      // aktuelle Scrollposition mitrechnen:
      return (elRect.top - scRect.top) + scroller.scrollTop;
    },
    scrollToCurrent() {
      const el = this.$refs.currentItem;
      const scroller = this.$refs.scroller;
      if (!el || !scroller) return;

      const elTop = this._relTop(el, scroller);
      const targetTop = elTop - (scroller.clientHeight / 2) + (el.clientHeight / 2);

      scroller.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
    }
  }"
  x-ref="scroller"
  x-init="
    $nextTick(() => scrollToCurrent());
    Livewire.hook('message.processed', () => scrollToCurrent());
    window.addEventListener('resize', () => scrollToCurrent());
  "
>

          {{-- Bausteine --}}
          @forelse($bausteine as $b)
            @php
              $typ      = $b['typ'] ?? 'kurs';
              $hasLink  = !empty($b['klassen_id']);
              $isCurrent = isset($aktuellesModul['baustein_id']) 
                          && $aktuellesModul['baustein_id'] === ($b['baustein_id'] ?? null);

              // Zeitvergleich – KEIN use im Blade, stattdessen voll qualifiziert:
              $start = !empty($b['beginn']) ? \Illuminate\Support\Carbon::parse($b['beginn']) : null;
              $ende  = !empty($b['ende'])   ? \Illuminate\Support\Carbon::parse($b['ende'])   : null;
              $today = \Illuminate\Support\Carbon::now('Europe/Berlin');

              // neue Felder sicher casten
              $punkte         = array_key_exists('punkte', $b) ? (int) $b['punkte'] : null;
              $klassenschnitt = array_key_exists('klassenschnitt', $b) ? (int) $b['klassenschnitt'] : null;
              $schnitt        = array_key_exists('schnitt', $b) ? (float) $b['schnitt'] : null;

              // Statuslogik
              $status = null;
              $statusClass = '';

              if ($typ === 'kurs') {
                  if ($start && $start->isFuture()) {
                      $status = 'Geplant';
                      $statusClass = 'text-yellow-700 bg-yellow-100';
                  } elseif ($start && $ende && $start->lte($today) && $ende->gte($today)) {
                      $status = 'Laufend';
                      $statusClass = 'text-blue-700 bg-blue-100';
                  } else {
                      // *** NEU: Wenn Punkte = 0 UND Klassenschnitt = 0 => Ergebnis offen
                      if ($punkte === 0 && $klassenschnitt === 0) {
                          $status = 'Ergebnis offen';
                          $statusClass = 'text-gray-700 bg-gray-100';
                      }
                      // Falls nicht offen durch 0/0, dann nach schnitt bewerten (falls vorhanden)
                      elseif ($schnitt !== null) {
                          if ($schnitt >= 50) {
                              $status = 'Bestanden';
                              $statusClass = 'text-green-700 bg-green-100';
                          } else {
                              $status = 'Nicht bestanden';
                              $statusClass = 'text-red-700 bg-red-100';
                          }
                      } else {
                          // Fallback
                          $status = 'offen';
                          $statusClass = 'text-gray-600 bg-gray-100';
                      }
                  }
              }

              // Zeilenstile
              $rowBase   = 'relative h-[70px] py-3 px-4 transition-all delay-50 duration-500 snap-start ';
              $rowBgs    = $isCurrent 
                            ? 'bg-emerald-50 ring-1 ring-emerald-200' 
                            : 'even:bg-white odd:bg-gray-100';
              $rowHover  = $hasLink 
                            ? 'group hover:bg-blue-100 hover:pr-[45px] cursor-pointer' 
                            : 'cursor-default opacity-70';
            @endphp


            <li class="{{ $rowBase }} {{ $rowBgs }} {{ $rowHover }}"   @if($isCurrent) x-ref="currentItem" @endif>
              @if($hasLink)
                <a href="{{ route('user.program.course.show', $b['klassen_id']) }}" wire:navigate aria-label="Baustein öffnen">
              @else 
                <div>
              @endif
              <div class="grid grid-cols-12 gap-1">
                <div class="col-span-8 font-medium text-gray-800 flex items-center gap-2">
                  <div class="truncate">{{ $b['baustein'] }}</div>
                  @if($isCurrent)
                    <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                      Aktuell
                    </span>
                  @endif
                </div>

                {{-- Status nur bei echten Kursen --}}
                @if($typ === 'kurs' && $status)
                  <div class="col-span-4 text-right">
                    <span class="px-2 py-1 text-xs font-medium rounded badge {{ $statusClass }}">{{ $status }}</span>
                  </div>
                @else
                  <div class="col-span-4"></div>
                @endif
              </div>

              <div class="grid grid-cols-12 gap-1">
                <div class="col-span-6 font-medium text-gray-800">
                  <span class="px-2 py-1 text-xs font-medium rounded badge bg-gray-50 text-gray-500">
                    {{ strtoupper($typ) }}
                  </span>
                </div>
                <div class="col-span-6 text-right">
                  <span class="text-xs text-gray-500 w-max">
                    {{ $start?->locale('de')->isoFormat('DD.MM.YYYY') }}
                    &nbsp;–&nbsp;
                    {{ $ende?->locale('de')->isoFormat('DD.MM.YYYY') }}
                  </span>
                </div>
              </div>

              @if($hasLink)
                <div class="absolute h-[70px] right-2 top-0 flex items-center opacity-0 translate-x-5 
                            group-hover:opacity-100 group-hover:translate-x-0 transition-all delay-50 duration-500 text-gray-500">
                  <a href="{{ route('user.program.course.show', $b['klassen_id']) }}" wire:navigate aria-label="Baustein öffnen">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 mr-1 max-md:mr-2" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                      <polyline points="10 17 15 12 10 7"/>
                      <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                  </a>
                </div>
                </a>
              @else 
                </div>
              @endif
            </li>
          @empty
            <li class="p-5 text-gray-500">Keine Bausteine vorhanden.</li>
          @endforelse



        </ul>
      </div>

      {{-- Aktueller Baustein --}}
      <div class="bg-white shadow rounded-lg  border border-gray-300 grid grid-rows-[auto_1fr]">
        <div class="p-5 h-max bg-sky-100 border-b border-gray-300 relative">
          <h3 class="text-lg font-semibold ">Aktueller Baustein</h3>
          <div class="absolute top-1 right-1 text-gray-500">
            <button
              x-data="{ tooltip: false }"
              @mouseenter="tooltip = true"
              @mouseleave="tooltip = false"
              class="relative p-1 rounded-full hover:bg-gray-200 transition"
            >
              <svg xmlns="http://www.w3.org/2000/svg"  class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              <div
                x-show="tooltip"
                x-transition
                class="absolute z-10 w-48 p-2 text-xs text-white bg-gray-800 rounded-lg shadow-lg -top-12 right-1/2  whitespace-normal"
                style="display: none;"
              >
                Hier findest du Informationen zu deinem aktuellen Baustein, inklusive Zeitraum und Fortschritt. Klicke auf "Details", um mehr zu erfahren.
              </div>
          </div>
        </div>
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
              <div class="mt-4">
                <div class="w-full bg-gray-200 rounded-full h-3">
                  <div class="bg-blue-600 h-3 rounded-full" style="width: {{ $currentProgress }}%"></div>
                </div>
                <p class="text-sm text-gray-600 mt-1">Fortschritt: {{ $currentProgress }}%</p>
              </div>
              <div>
                <div class="flex items-center justify-between mt-4">
                  @if($aktuellesModul['klassen_id'] != null)
                  <x-buttons.button-basic :size="'sm'" href="{{ route('user.program.course.show', $aktuellesModul['klassen_id']) }}" class="">
                    Details
                    <svg xmlns="http://www.w3.org/2000/svg"  class="h-4 text-gray-400  ml-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                  </x-buttons.button-basic>
                  <x-buttons.button-basic :size="'sm'" @click="$dispatch('open-course-rating-modal', { course_id: '{{ $aktuellesModul['klassen_id'] }}' });isClicked = true; setTimeout(() => isClicked = false, 100)"   >
                    Bewerten
                    <svg
                        class="ml-2 w-5 transition-colors duration-150 text-gray-400 hover:text-yellow-400"
                        fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.204 3.698a1 1 0 00.95.69h3.894c.969 0 1.371 1.24.588 1.81l-3.15 2.286a1 1 0 00-.364 1.118l1.204 3.698c.3.921-.755 1.688-1.54 1.118l-3.15-2.286a1 1 0 00-1.176 0l-3.15 2.286c-.784.57-1.838-.197-1.539-1.118l1.203-3.698a1 1 0 00-.364-1.118L2.414 9.125c-.783-.57-.38-1.81.588-1.81h3.894a1 1 0 00.951-.69l1.202-3.698z"/>
                    </svg>                  
                  </x-buttons.button-basic>
                  @endif
                </div>
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
  @endif
</div>
