<div
  class="transition"
  x-data="{
    courseNavigationVisible: false,
    courseNavigationDelayed: false,
    courseNavigationTimedOut: false,
    courseNavigationTarget: '',
    courseNavigationPrevious: window.location.href,
    courseNavigationTimers: [],
    clearCourseNavigationTimers() {
      this.courseNavigationTimers.forEach((timer) => clearTimeout(timer));
      this.courseNavigationTimers = [];
    },
    beginCourseNavigation(url) {
      this.clearCourseNavigationTimers();

      const startedAt = Date.now();
      const target = new URL(url, window.location.href);
      target.searchParams.set('loading_started_at', String(startedAt));

      this.courseNavigationTarget = target.toString();
      this.courseNavigationPrevious = window.location.href;
      this.courseNavigationVisible = true;
      this.courseNavigationDelayed = false;
      this.courseNavigationTimedOut = false;
      this.$nextTick(() => this.$refs.courseNavigationDialog?.focus());

      this.courseNavigationTimers.push(setTimeout(() => {
        this.courseNavigationDelayed = true;
      }, 3000));

      this.courseNavigationTimers.push(setTimeout(() => {
        this.courseNavigationTimedOut = true;
        this.$nextTick(() => this.$refs.courseNavigationRetry?.focus());
      }, 10000));

      return this.courseNavigationTarget;
    },
    startCourseNavigation(link) {
      link.href = this.beginCourseNavigation(link.href);
    },
    retryCourseNavigation() {
      window.location.assign(this.beginCourseNavigation(this.courseNavigationTarget));
    },
    cancelCourseNavigation() {
      this.clearCourseNavigationTimers();
      window.stop();
      window.location.assign(this.courseNavigationPrevious);
    },
    trapCourseNavigationFocus(event) {
      if (!this.courseNavigationTimedOut) return;

      const controls = [
        this.$refs.courseNavigationCancel,
        this.$refs.courseNavigationRetry
      ].filter(Boolean);
      if (!controls.length) return;

      const currentIndex = controls.indexOf(document.activeElement);
      const direction = event.shiftKey ? -1 : 1;
      const nextIndex = currentIndex === -1
        ? 0
        : (currentIndex + direction + controls.length) % controls.length;

      controls[nextIndex].focus();
    },
    destroy() {
      this.clearCourseNavigationTimers();
    }
  }"
  x-on:click.capture="
    const link = $event.target.closest('[data-course-navigation]');
    if (
      link
      && $event.button === 0
      && ! $event.ctrlKey
      && ! $event.metaKey
      && ! $event.shiftKey
      && ! $event.altKey
    ) {
      startCourseNavigation(link);
    }
  "
  x-on:keydown.escape.window="if (courseNavigationTimedOut) cancelCourseNavigation()"
  x-bind:aria-busy="courseNavigationVisible ? 'true' : 'false'"
  @if($apiProgramLoading)
    wire:poll.visible.2000="pollProgram"
  @endif
  wire:loading.class="cursor-wait opacity-50 animate-pulse"
>
    <div
      x-cloak
      x-show="courseNavigationVisible"
      x-ref="courseNavigationDialog"
      tabindex="-1"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 px-4 backdrop-blur-[2px]"
      role="dialog"
      aria-modal="true"
      x-bind:aria-label="courseNavigationTimedOut ? 'Baustein noch nicht verfügbar' : 'Baustein wird geladen'"
      aria-describedby="course-navigation-description"
      x-on:keydown.tab.prevent="trapCourseNavigationFocus($event)"
    >
      <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white px-5 py-8 text-center shadow-xl sm:px-8 sm:py-10">
        <template x-if="!courseNavigationTimedOut">
          <div role="status" aria-live="polite" aria-atomic="true">
            <svg
              class="mx-auto h-12 w-12 animate-spin text-blue-600 motion-reduce:animate-none"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <circle class="opacity-20" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
              <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
            </svg>
            <span class="sr-only">Baustein wird geladen.</span>
            <p
              id="course-navigation-title"
              x-cloak
              x-show="courseNavigationDelayed"
              class="mt-5 text-base font-semibold text-slate-800"
            >
              Seite wird geladen...
            </p>
            <p id="course-navigation-description" class="sr-only">Bitte warte, während der Baustein geöffnet wird.</p>
          </div>
        </template>

        <template x-if="courseNavigationTimedOut">
          <div role="alert">
            <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700" aria-hidden="true">
              <i class="fal fa-clock"></i>
            </span>
            <h2 id="course-navigation-title" class="mt-5 text-xl font-semibold text-slate-900">Baustein noch nicht verfügbar</h2>
            <p id="course-navigation-description" class="mt-3 text-sm leading-6 text-slate-600">
              Die Synchronisierung dauert etwas länger. Bitte versuche es erneut oder brich den Vorgang ab.
            </p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
              <button
                type="button"
                x-ref="courseNavigationCancel"
                x-on:click="cancelCourseNavigation()"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
              >
                Abbrechen
              </button>
              <button
                type="button"
                x-ref="courseNavigationRetry"
                x-on:click="retryCourseNavigation()"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
              >
                Erneut laden
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- Loader wenn Programm noch nicht geladen --}}
    @if($apiProgramLoading)
        <div role="status" class="h-32 w-full relative animate-pulse" wire:ignore>
            <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 transition-opacity">
                <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                    <span class="loader"></span>
                    <span class="text-sm text-gray-700">Programm Daten werden geladen…</span>
                </div>
            </div>
        </div>
    @else
  <livewire:user.program.program-pdf-modal lazy />
<livewire:user.program.course.course-rating-form-modal />

{{-- Client-seitiger Trigger für required Modal (nach Hydration) --}}
<div
  x-data
  x-init="
    const payload = @js($pendingRequiredRating ?? []);
    if (payload.course_id) {
      $dispatch('open-course-rating-required-modal',[ payload]);
    }
  "
></div>

  <section class="relative space-y-6">
    <div class="mt-8">
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
                              tooltipHideTimer: null,
                              series: @js($bausteinSerie),
                              labels: @js($bausteinLabels),
                              colors: @js($bausteinColors),
                              tooltips: @js($bausteinTooltips),

                              pickColor(val){
                                if(!val) return null;
                                const v = String(val).trim();
                                return v.startsWith('--')
                                  ? (getComputedStyle(document.documentElement).getPropertyValue(v).trim() || v)
                                  : v;
                              },

                              hideTooltip(delay = 0){
                                clearTimeout(this.tooltipHideTimer);
                                this.tooltipHideTimer = setTimeout(() => {
                                  const tooltip = this.$el.querySelector('.apexcharts-tooltip');
                                  if (tooltip) tooltip.classList.remove('apexcharts-active');
                                }, delay);
                              },

                              hideTooltipUnlessOnBar(event){
                                const bar = event.target.matches?.('.apexcharts-bar-area')
                                  ? event.target
                                  : event.target.closest?.('.apexcharts-bar-area');
                                if (bar && this.$el.contains(bar)) {
                                  clearTimeout(this.tooltipHideTimer);
                                  return;
                                }
                                this.hideTooltip(150);
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
                                      title: { formatter: () => '' },
                                      formatter: (val, { dataPointIndex }) => this.tooltips?.[dataPointIndex] ?? ((val ?? 0) + ' Pkt.')
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
                                this.$el.addEventListener('mouseleave', () => this.hideTooltip());

                                // Reaktiv bei Livewire-Updates (falls Props dynamisch neu kommen)
                                this.$watch('series', (v) => { if (this.chart) this.chart.updateSeries([{ data: v }], true); });
                                this.$watch('labels', () =>  { if (this.chart) this.chart.updateOptions({ tooltip: this.buildOptions().tooltip }, true, true); });
                                this.$watch('tooltips', () =>  { if (this.chart) this.chart.updateOptions({ tooltip: this.buildOptions().tooltip }, true, true); });
                                this.$watch('colors', (v) => { if (this.chart) this.chart.updateOptions({ colors: v.map(c => this.pickColor(c)) }, true, true); });
                              }
                            }"
                            x-init="init()"
                            x-on:mousemove.window="hideTooltipUnlessOnBar($event)"
                            x-on:pointerdown.window="hideTooltipUnlessOnBar($event)"
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
                    <x-buttons.button-basic :size="'sm'" :mode="'secondary'" @click="$dispatch('open-program-pdf');isClicked = true; setTimeout(() => isClicked = false, 100)" class="w-full">
                      Programm als PDF
                    </x-buttons.button-basic>
                  </div>
                </div>
                @if(Auth::user()?->person?->isEducation())
                  <div class="swiper-slide">
                    <div class="grid h-full grid-cols-1 place-content-stretch">
                      <h3 class="text-gray-800 font-semibold mb-1">Berichtsheft</h3>
                      <p class="text-xs text-gray-600 mb-2">
                        Übersicht über deine Berichtsheft.
                      </p>
                      <x-buttons.button-basic :size="'sm'" :mode="'secondary'"  href="/user/reportbook" 
                        class="w-full">
                        Berichtsheft anzeigen
                      </x-buttons.button-basic>
                    </div>
                  </div>
                @endif
                <div class="swiper-slide">
                  <div class="grid h-full grid-cols-1 place-content-stretch">
                    <h3 class="text-gray-800 font-semibold mb-1">Anträge</h3>
                    <p class="text-xs text-gray-600 mb-2">
                      Übersicht über deine Anträge im Programm.
                    </p>
                    <x-buttons.button-basic :size="'sm'" :mode="'secondary'"  href="/user/user-requests" 
                       class="w-full">
                      Anträge anzeigen
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
            {{-- Header --}}
    <div class="px-5 py-4 bg-gradient-to-r from-sky-200 to-sky-50 border-b border-slate-200">
      <div class="flex items-center justify-between">
        <h3 class="text-base md:text-lg font-semibold text-slate-900 flex items-center gap-2">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white text-sky-700">
            <i class="fal fa-layer-group"></i>
          </span>
          Bausteine
        </h3>

        <div class="text-xs text-slate-500 flex items-center gap-2">
          <i class="fal fa-mouse-pointer"></i>
          <span>Tippe / klicke für Details</span>
        </div>
      </div>
    </div>
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
                      // Normalisierter Ergebnisstatus aus ProgramShow::ergebnisStatus()
                      // (UVS-Kennwoerter wie 'failed' duerfen nicht als "Ergebnis ausstehend" enden)
                      $ergebnisStatus = $b['ergebnis_status'] ?? null;

                      if ($ergebnisStatus === 'passed') {
                          $status = 'Bestanden';
                          $statusClass = 'text-green-700 bg-green-100';
                      } elseif ($ergebnisStatus === 'failed') {
                          $status = 'Nicht bestanden';
                          $statusClass = 'text-red-700 bg-red-100';
                      } elseif ($ergebnisStatus === 'not_attended') {
                          $status = 'Nicht teilgenommen';
                          $statusClass = 'text-gray-700 bg-gray-100';
                      } elseif ($ergebnisStatus === 'open') {
                          $status = 'Ergebnis ausstehend';
                          $statusClass = 'text-gray-700 bg-gray-100';
                      }
                      // Fallback fuer ViewModels, die noch ohne ergebnis_status aufgebaut wurden
                      elseif ($punkte === 0 && $klassenschnitt === 0) {
                          $status = 'Ergebnis ausstehend';
                          $statusClass = 'text-gray-700 bg-gray-100';
                      } elseif ($schnitt !== null) {
                          if ($schnitt >= 50) {
                              $status = 'Bestanden';
                              $statusClass = 'text-green-700 bg-green-100';
                          } else {
                              $status = 'Nicht bestanden';
                              $statusClass = 'text-red-700 bg-red-100';
                          }
                      } else {
                          $status = 'offen';
                          $statusClass = 'text-gray-600 bg-gray-100';
                      }
                  }
              }

              // Zeilenstile
              $rowBase   = 'relative h-[70px] py-3 px-4 transition-all delay-50 duration-500 snap-start ';
              $rowBgs    = $isCurrent 
                            ? 'even:bg-white odd:bg-gray-100  border-l-4 !border-l-emerald-500' 
                            : 'even:bg-white odd:bg-gray-100';
              $rowHover  = $hasLink 
                            ? 'group hover:bg-blue-100 hover:pr-[45px] cursor-pointer' 
                            : 'cursor-default opacity-70';
            @endphp


            <li class="{{ $rowBase }} {{ $rowBgs }} {{ $rowHover }}"   @if($isCurrent) x-ref="currentItem" @endif>
              @if($hasLink)
                <a
                  href="{{ route('user.program.course.show', $b['klassen_id']) }}"
                  wire:navigate
                  data-course-navigation="true"
                  class="block h-full focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-600"
                  aria-label="Baustein {{ $b['baustein'] }} öffnen"
                >
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

                @if($typ === 'kurs' && $status)
                  <div class="col-span-4 text-right">
                    <span class="px-2 py-1 text-xs font-medium rounded badge {{ $statusClass }}">{{ $status }}</span>
                  </div>
                @else
                  <div class="col-span-4"></div>
                @endif
              </div>

              <div class="">
                <div class="">
                  <span class="text-xs text-blue-900 ">
                    <i class="far fa-calendar-alt mr-2 text-gray-500"></i>
                    {{ $start?->locale('de')->isoFormat('DD.MM.YYYY') }}
                    &nbsp;–&nbsp;
                    {{ $ende?->locale('de')->isoFormat('DD.MM.YYYY') }}
                  </span>
                </div>
              </div>

              @if($hasLink)
                <div class="absolute h-[70px] right-2 top-0 flex items-center opacity-0 translate-x-5 
                            group-hover:opacity-100 group-hover:translate-x-0 transition-all delay-50 duration-500 text-gray-500">
                  <span aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 mr-1 max-md:mr-2" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                      <polyline points="10 17 15 12 10 7"/>
                      <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                  </span>
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
        <div class="h-max px-5 py-4 bg-gradient-to-r from-sky-200 to-sky-50 border-b border-slate-200 relative">
        <h3 class="text-base md:text-lg font-semibold text-slate-900 flex items-center gap-2">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white text-emerald-700">
            <i class="fal fa-location text-emerald-700"></i>
          </span>
          Aktueller Baustein
        </h3>
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
        @php
          $currentStartDate = !empty($aktuellesModul['beginn'])
            ? \Illuminate\Support\Carbon::parse(str_replace('/', '-', (string) $aktuellesModul['beginn']))->startOfDay()
            : null;
          $isCurrentModuleFuture = $currentStartDate ? $currentStartDate->isFuture() : false;

          $ratingTitle = $isCurrentModuleFuture
            ? 'Bewertung: noch nicht verfügbar'
            : ($hasCurrentCourseRating ? 'Bewertung: erledigt' : 'Bewertung: offen');
          $materialTitle = $isCurrentModuleFuture
            ? 'Materialbestätigung: noch nicht verfügbar'
            : ($hasCurrentCourseMaterialsAck ? 'Materialbestätigung: erledigt' : 'Materialbestätigung: offen');

          $ratingBadgeClasses = $isCurrentModuleFuture
            ? 'bg-slate-100 text-slate-400 ring-slate-200'
            : ($hasCurrentCourseRating ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-amber-100 text-amber-800 ring-amber-200');
          $materialBadgeClasses = $isCurrentModuleFuture
            ? 'bg-slate-100 text-slate-400 ring-slate-200'
            : ($hasCurrentCourseMaterialsAck ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-amber-100 text-amber-800 ring-amber-200');
          $ratingNeedsAction = ! $isCurrentModuleFuture && ! $hasCurrentCourseRating;
          $materialNeedsAction = ! $isCurrentModuleFuture && ! $hasCurrentCourseMaterialsAck;
          $showMaterialsConfirmButton = $materialNeedsAction && !empty($currentCourseId);
        @endphp

        <div class="space-y-6">
          <div class="space-y-6">
            <div class="flex items-start justify-between gap-3">
              <p class="text-base font-semibold text-slate-900">
                {{ $aktuellesModul['baustein'] }}
              </p>

              <div class="flex items-center gap-2">
                <div class="relative h-6 w-6">
                  @if($ratingNeedsAction)
                    <span class="absolute inset-0 inline-flex rounded-full bg-amber-300/70 animate-ping"></span>
                  @endif
                  <span
                    title="{{ $ratingTitle }}"
                    class="relative z-10 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full ring-1 {{ $ratingBadgeClasses }}"
                  >
                    <i class="fal fa-star text-xs {{ $ratingNeedsAction ? 'animate-pulse' : '' }}"></i>
                  </span>
                </div>

                <div class="relative h-6 w-6">
                  @if($materialNeedsAction)
                    <span class="absolute inset-0 inline-flex rounded-full bg-amber-300/70 animate-ping"></span>
                  @endif
                  <span
                    title="{{ $materialTitle }}"
                    class="relative z-10 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full ring-1 {{ $materialBadgeClasses }}"
                  >
                    <i class="fal fa-books text-xs {{ $materialNeedsAction ? 'animate-pulse' : '' }}"></i>
                  </span>
                </div>
              </div>
            </div>

            <p class="mt-1 text-sm text-slate-600 flex flex-wrap items-center gap-x-2 gap-y-1">
              <span class="inline-flex items-center gap-2">
                <i class="fal fa-calendar-alt text-slate-400"></i>
                {{ \Illuminate\Support\Carbon::parse($aktuellesModul['beginn'])->locale('de')->isoFormat('DD.MM.YYYY') }}
                –
                {{ \Illuminate\Support\Carbon::parse($aktuellesModul['ende'])->locale('de')->isoFormat('DD.MM.YYYY') }}
              </span>

              <span class="text-slate-300">·</span>

              <span class="inline-flex items-center gap-2">
                <i class="fal fa-clock text-slate-400"></i>
                {{ $aktuellesModul['tage'] }} Tage
              </span>
            </p>
          </div>

          {{-- Fortschritt --}}
          <div class="pt-1">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 mb-2">
              <span class="inline-flex items-center gap-2">
                <i class="fal fa-chart-line text-slate-400"></i>
                Fortschritt
              </span>
              <span class="text-slate-900">{{ $currentProgress }}%</span>
            </div>

            <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
              <div class="bg-blue-600 h-3 rounded-full transition-all" style="width: {{ $currentProgress }}%"></div>
            </div>
          </div>
        </div>

        {{-- Actions --}}
        <div class="pt-5">
          <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            @if($aktuellesModul['klassen_id'] != null)
              <div class="flex w-full flex-col gap-3 md:w-auto md:flex-row md:items-center">
                @if(!$hasCurrentCourseRating)
                  <x-buttons.button-basic
                    :size="'sm'"
                    class="!rounded-xl !w-full md:!w-auto"
                    @click="$dispatch('open-course-rating-modal', [{ course_id: '{{ $aktuellesModul['klassen_id'] }}' }]);isClicked = true; setTimeout(() => isClicked = false, 100)"
                  >
                    Bewerten
                    <i class="fa fa-star text-[18px] text-slate-300 ml-2 hover:text-yellow-400 animate-pulse"></i>
                  </x-buttons.button-basic>
                @endif

                @if($showMaterialsConfirmButton && !empty($aktuellesModul['klassen_id']))
                  <x-buttons.button-basic
                    :size="'sm'"
                    href="{{ route('user.program.course.show', $aktuellesModul['klassen_id']) }}"
                    wire:navigate
                    data-course-navigation="true"
                    class="!rounded-xl !w-full md:!w-auto !bg-amber-50 !text-amber-800 !border-amber-200"
                    x-on:click="localStorage.setItem('selectedTabcourse-{{ $currentCourseId }}', JSON.stringify('material')); localStorage.setItem('acc-course-{{ $currentCourseId }}', JSON.stringify('resources'));"
                  >
                    Zu Bestätigungen
                    <i class="fal fa-books ml-2"></i>
                  </x-buttons.button-basic>
                @endif
              </div>

              <x-buttons.button-basic
                :size="'sm'"
                href="{{ route('user.program.course.show', $aktuellesModul['klassen_id']) }}"
                wire:navigate
                data-course-navigation="true"
                class="!rounded-xl !w-full md:!w-auto md:ml-auto"
              >
                Details
                <i class="fal fa-arrow-right ml-2 text-slate-400"></i>
              </x-buttons.button-basic>

            @endif
          </div>
        </div>

      @else
        <p class="text-slate-500">Kein aktuelles Modul ermittelbar.</p>
      @endif
        </div>
      </div>
    </div>

    <!-- Accordion Wrapper -->
    <div 
        x-data="{ open: 'teilnehmer' }"
        class="space-y-4"
    >

      <!-- ========== Teilnehmerdaten ========== -->
      <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <button
              @click="open = (open === 'teilnehmer' ? null : 'teilnehmer')"
              :aria-expanded="open === 'teilnehmer'"
              class="w-full flex items-center justify-between px-6 py-4 text-left transition
                    hover:bg-gray-50"
          >
              <div class="flex items-center gap-3">
                  <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                      <i class="fal fa-user"></i>
                  </span>
                  <span class="text-sm font-semibold text-gray-800">
                      Teilnehmerdaten
                  </span>
              </div>

              <svg
                  class="w-5 h-5 text-gray-400 transition-transform duration-300"
                  :class="open === 'teilnehmer' && 'rotate-180'"
                  viewBox="0 0 20 20"
                  fill="currentColor"
              >
                  <path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/>
              </svg>
          </button>

          <div x-show="open === 'teilnehmer'" x-collapse>
              <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 px-6 pb-6 text-sm">
                  <div>
                      <dt class="text-xs text-gray-500">Name</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['teilnehmer']['name'] }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Geburtsdatum</dt>
                      <dd class="font-medium text-gray-900">
                          {{ \Illuminate\Support\Carbon::parse($teilnehmerDaten['teilnehmer']['geburt_datum'])->locale('de')->isoFormat('DD.MM.YYYY') }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Teilnehmer-Nr.</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['teilnehmer']['teilnehmer_nr'] }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Kunden-Nr.</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['teilnehmer']['kunden_nr'] }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Stammklasse</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['teilnehmer']['stammklasse'] }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Eignungstest</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['teilnehmer']['test_punkte'] }}
                      </dd>
                  </div>
              </dl>
          </div>
      </section>

      <!-- ========== Maßnahme ========== -->
      <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <button
              @click="open = (open === 'massnahme' ? null : 'massnahme')"
              :aria-expanded="open === 'massnahme'"
              class="w-full flex items-center justify-between px-6 py-4 text-left transition hover:bg-gray-50"
          >
              <div class="flex items-center gap-3">
                  <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                      <i class="fal fa-graduation-cap"></i>
                  </span>
                  <span class="text-sm font-semibold text-gray-800">
                      Maßnahme
                  </span>
              </div>

              <svg class="w-5 h-5 text-gray-400 transition-transform duration-300"
                  :class="open === 'massnahme' && 'rotate-180'"
                  viewBox="0 0 20 20" fill="currentColor">
                  <path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/>
              </svg>
          </button>

          <div x-show="open === 'massnahme'" x-collapse>
              <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 px-6 pb-6 text-sm">
                  <div>
                      <dt class="text-xs text-gray-500">Titel</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['massnahme']['titel'] }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Zeitraum</dt>
                      <dd class="font-medium text-gray-900">
                          {{ \Illuminate\Support\Carbon::parse($teilnehmerDaten['massnahme']['zeitraum']['von'])->locale('de')->isoFormat('DD.MM.YYYY') }}
                          –
                          {{ \Illuminate\Support\Carbon::parse($teilnehmerDaten['massnahme']['zeitraum']['bis'])->locale('de')->isoFormat('DD.MM.YYYY') }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Bausteine</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['massnahme']['bausteine'] }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Unterrichtsform</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['massnahme']['inhalte'] }}
                      </dd>
                  </div>
              </dl>
          </div>
      </section>

      <!-- ========== Maßnahmenträger ========== -->
      <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <button
              @click="open = (open === 'traeger' ? null : 'traeger')"
              :aria-expanded="open === 'traeger'"
              class="w-full flex items-center justify-between px-6 py-4 text-left transition hover:bg-gray-50"
          >
              <div class="flex items-center gap-3">
                  <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                      <i class="fal fa-building"></i>
                  </span>
                  <span class="text-sm font-semibold text-gray-800">
                      Maßnahmenträger
                  </span>
              </div>

              <svg class="w-5 h-5 text-gray-400 transition-transform duration-300"
                  :class="open === 'traeger' && 'rotate-180'"
                  viewBox="0 0 20 20" fill="currentColor">
                  <path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/>
              </svg>
          </button>

          <div x-show="open === 'traeger'" x-collapse>
              <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 px-6 pb-6 text-sm">
                  <div>
                      <dt class="text-xs text-gray-500">Institution</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['traeger']['institution'] }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Ansprechpartner</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['traeger']['ansprechpartner'] }}
                      </dd>
                  </div>

                  <div class="md:col-span-2">
                      <dt class="text-xs text-gray-500">Adresse</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['traeger']['adresse'] }}
                      </dd>
                  </div>
              </dl>
          </div>
      </section>

      <!-- ========== Praktikum ========== -->
      <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <button
              @click="open = (open === 'praktikum' ? null : 'praktikum')"
              :aria-expanded="open === 'praktikum'"
              class="w-full flex items-center justify-between px-6 py-4 text-left transition hover:bg-gray-50"
          >
              <div class="flex items-center gap-3">
                  <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 text-orange-600">
                      <i class="fal fa-briefcase"></i>
                  </span>
                  <span class="text-sm font-semibold text-gray-800">
                      Praktikum
                  </span>
              </div>

              <svg class="w-5 h-5 text-gray-400 transition-transform duration-300"
                  :class="open === 'praktikum' && 'rotate-180'"
                  viewBox="0 0 20 20" fill="currentColor">
                  <path d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z"/>
              </svg>
          </button>

          <div x-show="open === 'praktikum'" x-collapse>
              <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 px-6 pb-6 text-sm">
                  <div>
                      <dt class="text-xs text-gray-500">Tage</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['praktikum']['tage'] }}
                      </dd>
                  </div>

                  <div>
                      <dt class="text-xs text-gray-500">Stunden</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['praktikum']['stunden'] }}
                      </dd>
                  </div>

                  <div class="md:col-span-2">
                      <dt class="text-xs text-gray-500">Bemerkung</dt>
                      <dd class="font-medium text-gray-900">
                          {{ $teilnehmerDaten['praktikum']['bemerkung'] ?? '—' }}
                      </dd>
                  </div>
              </dl>
          </div>
      </section>

    </div>


  </section>
  @endif
</div>
