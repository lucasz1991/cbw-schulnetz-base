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

            // ⬇️ AKTUELLEN SLIDE FINDEN & ZENTRIEREN
            this.$nextTick(() => {
              const slides = [...this.$refs.coursesSwiper.querySelectorAll('.swiper-slide')];
              const currentIndex = slides.findIndex(
                slide => slide.dataset.current === '1'
              );

              if (currentIndex >= 0) {
                this.swiper.slideTo(currentIndex, 0); // sofort zentrieren
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
                @endphp

                <div
                  class="swiper-slide h-full pr-1"
                  style="width: 320px;"
                  wire:key="course-slide-{{ $rc->klassen_id }}"
                  data-current="{{ $isCurrent ? '1' : '0' }}"
                >
                  <a
                    href="{{ $isCurrent ? '#' : route('user.program.course.show', ['klassenId' => $rc->klassen_id]) }}"
                    @class([
                      'group/coursecard block h-full rounded-2xl overflow-hidden transition',
                      'border shadow-sm bg-white hover:shadow-md' => ! $isCurrent,
                      'border-2 border-blue-600 bg-blue-50 shadow-lg scale-[1.1] cursor-default pointer-events-none' => $isCurrent,
                    ])
                  >
                  <div class="p-5 flex flex-col h-full">
                    <div class="flex items-start justify-between gap-3 mb-3">
                      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                        {{ $rc->statusLabel }}
                      </span>

                      <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                        {{ $rc->days_count }}
                        <i class="fal fa-calendar-alt text-xs"></i>
                      </span>
                    </div>

                    <h4 class="text-base font-semibold text-gray-900 line-clamp-2 min-h-12">
                      {{ $rc->title }}
                    </h4>

                    <p class="text-sm text-gray-500 mt-2 line-clamp-1">
                      {{ $rc->zeitraum_fmt ?? '—' }}
                    </p>

                    
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
