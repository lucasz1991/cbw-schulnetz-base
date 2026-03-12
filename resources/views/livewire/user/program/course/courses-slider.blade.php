<div>
  <section class="bg-slate-100 border-t border-slate-200 overflow-x-hidden">
    <div class="container mx-auto px-5 py-12">
      <div class="flex items-center justify-between gap-4 mb-8">
        <h3 class="text-lg font-semibold text-gray-900">Weitere Bausteine</h3>
        <div class="flex items-center gap-2">
          @if($prev)
            <x-buttons.button-basic :size="'sm'"
              href="{{ route('user.program.course.show', ['klassenId' => $prev['klassen_id']]) }}"
              wire:navigate><i class="fal fa-arrow-left mr-2"></i> Vorheriger</x-buttons.button-basic>
          @endif
          @if($next)
            <x-buttons.button-basic :size="'sm'"
              href="{{ route('user.program.course.show', ['klassenId' => $next['klassen_id']]) }}"
              wire:navigate>Nächster <i class="fal fa-arrow-right ml-2"></i></x-buttons.button-basic>
          @endif
        </div>
      </div>
      {{-- SWIPER WRAPPER (Funktionalität unverändert) --}}
      <div
        x-data="{
          swiper: null,
          initSwiper() {
            this.swiper = new Swiper(this.$refs.coursesSwiper, {
              slidesPerView: 'auto',
              spaceBetween: 16,
              freeMode: true,
              speed: 500,
              keyboard: { enabled: true },
              pagination: {
                el: this.$refs.paginationEl,
                clickable: true,
              },
              breakpoints: {
                640: { spaceBetween: 20 },
                1024:{ spaceBetween: 24 },
              }
            });
            this.$nextTick(() => {
              const slides = [...this.$refs.coursesSwiper.querySelectorAll('.swiper-slide')];
              const currentIndex = slides.findIndex(
                slide => slide.dataset.current === '1'
              );
              if (currentIndex >= 0) {
                this.swiper.slideTo(currentIndex, 0);
              }
            });
          }
        }"
        x-init="initSwiper()"
        class="relative"
        wire:ignore
      >
        <div class="swiper overflow-visible h-full" x-ref="coursesSwiper">
          <div class="swiper-wrapper h-full">

            @foreach ($enrolledCourses as $index => $rc)
              @php
                $isCurrent = (string) $rc->klassen_id == (string) $klassenId;
                $status = (string) ($rc->statusLabel ?? 'Offen');
                $statusClasses = match ($status) {
                  'Laufend' => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
                  'Geplant' => 'bg-amber-100 text-amber-800 ring-1 ring-amber-200',
                  'Abgeschlossen' => 'bg-slate-200 text-slate-700 ring-1 ring-slate-300',
                  default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
                };
                $courseCode = trim((string) ($rc->course_short_name ?? ''));
                $startDate = $rc->planned_start_date ? \Illuminate\Support\Carbon::parse($rc->planned_start_date)->startOfDay() : null;
                $isFuture = $startDate ? $startDate->isFuture() : false;
                $hasRating = (bool) ($rc->has_rating ?? false);
                $hasMaterialAck = (bool) ($rc->has_material_ack ?? false);
                $ratingTitle = $isFuture ? 'Bewertung: noch nicht verfügbar' : ($hasRating ? 'Bewertung: erledigt' : 'Bewertung: offen');
                $materialTitle = $isFuture ? 'Materialbestätigung: noch nicht verfügbar' : ($hasMaterialAck ? 'Materialbestätigung: erledigt' : 'Materialbestätigung: offen');
                $ratingBadgeClasses = $isFuture
                  ? 'bg-slate-100 text-slate-400 ring-slate-200'
                  : ($hasRating ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-amber-100 text-amber-800 ring-amber-200');
                $materialBadgeClasses = $isFuture
                  ? 'bg-slate-100 text-slate-400 ring-slate-200'
                  : ($hasMaterialAck ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-amber-100 text-amber-800 ring-amber-200');
              @endphp

              <div
                class="swiper-slide h-full pr-1"
                style="width: 340px;"
                wire:key="course-slide-{{ $rc->klassen_id }}"
                data-current="{{ $isCurrent ? '1' : '0' }}"
              >
                <a
                  href="{{ $isCurrent ? '#' : route('user.program.course.show', ['klassenId' => $rc->klassen_id]) }}"
                  @class([
                    'group/coursecard relative block h-full rounded-2xl overflow-hidden transition-all duration-200',
                    'border border-slate-200 bg-white shadow-sm hover:shadow-lg hover:-translate-y-0.5' => ! $isCurrent,
                    'border-2 border-blue-600 bg-gradient-to-b from-blue-50 to-white shadow-lg scale-[1.03] cursor-default pointer-events-none' => $isCurrent,
                  ])
                >
                  @if ($isCurrent)
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-blue-600"></div>
                  @endif

                  <div class="p-5 flex flex-col h-full">
                    <div class="flex items-start justify-between gap-3 mb-4">
                      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusClasses }}">
                        {{ $status }}
                      </span>

                      <div class="flex items-center gap-2">
                        <span
                          title="{{ $ratingTitle }}"
                          class="inline-flex h-6 w-6 items-center justify-center rounded-full ring-1 {{ $ratingBadgeClasses }}"
                        >
                          <i class="fal fa-star text-xs"></i>
                        </span>
                        <span
                          title="{{ $materialTitle }}"
                          class="inline-flex h-6 w-6 items-center justify-center rounded-full ring-1 {{ $materialBadgeClasses }}"
                        >
                          <i class="fal fa-books text-xs"></i>
                        </span>
                      </div>
                    </div>

                    <h4 class="text-base font-semibold text-gray-900 line-clamp-2 min-h-12">
                      {{ $courseCode !== '' ? $courseCode . ' - ' : '' }}{{ $rc->title }}
                    </h4>

                    <div class="mt-4 space-y-2 text-sm">
                      <div class="flex items-start gap-2 text-slate-600">
                        <i class="fal fa-clock mt-0.5"></i>
                        <span class="line-clamp-1">{{ $rc->zeitraum_fmt ?? 'Zeitraum folgt' }}</span>
                      </div>

                      <div class="flex items-start gap-2 text-slate-600">
                        <i class="fal fa-map-marker-alt mt-0.5"></i>
                        <span class="line-clamp-1">{{ filled($rc->room) ? $rc->room : 'Raum folgt' }}</span>
                      </div>
                    </div>


                  </div>
                </a>
              </div>
            @endforeach

          </div>
        </div>

        {{-- Pagination (optional) --}}
        <div class="mt-6 flex justify-center">
          <div class="swiper-pagination !relative" x-ref="paginationEl"></div>
        </div>

      </div>
    </div> 
  </section> 
</div>
