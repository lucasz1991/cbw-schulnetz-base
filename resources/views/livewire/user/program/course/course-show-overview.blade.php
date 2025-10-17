<div class="">
  <header class="container mx-auto px-5 py-6">
    <div class="flex items-start justify-between">      
      <div class="flex items-center gap-2">
        <div>
          @php
            $status = $course['status'] ?? 'Offen';
            $badge = [
              'Geplant' => 'bg-yellow-100 text-yellow-800',
              'Laufend' => 'bg-blue-100 text-blue-800',
              'Abgeschlossen' => 'bg-gray-200 text-gray-800',
              'Offen' => 'bg-gray-200 text-gray-800',
            ][$status] ?? 'bg-gray-200 text-gray-800';
          @endphp
          <span class="inline-block px-2 py-0.5 rounded mb-4 {{ $badge }}">{{ $status }}</span>
        </div>
      </div>
      <div>
        <div class="">
          <x-ui.badge.badge :color="'blue'">
            {{ $course['zeitraum_fmt'] ?? '—' }}
          </x-ui.badge.badge>
        </div>
      </div>
    </div>
    <h1 class="text-2xl font-semibold">{{ $course['title'] ?? '—' }}</h1>
  </header>
  <section class="container mx-auto px-5 pb-24">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg border shadow p-4">
        <x-user.public-info :person="$tutor" />
      </div>
      <div class="bg-white rounded-lg border shadow p-4">
        <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-gray-500 mb-4">Tage</p>
        <x-ui.badge.badge :color="'gray'">
          {{ $stats['tage'] ?? '—' }}
        </x-ui.badge.badge>
        </div>
      </div>
      <div class="bg-white rounded-lg border shadow p-4">
        <div class="flex items-center justify-between gap-3">
          <p class="text-xs text-gray-500 mb-4">Teilnehmer</p>
          <x-ui.badge.badge :color="'gray'">
            {{ $participantsCount }}
          </x-ui.badge.badge>
        </div>
      </div>
      <div class="bg-white rounded-lg border shadow p-4">
        <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-gray-500 mb-4">Raum</p>
         <x-ui.badge.badge :color="'yellow'">
          {{ $course['room'] ?? '—' }}
        </x-ui.badge.badge>
        </div>
      </div>
    </div>
</section>
<section class="bg-white border-b-2 border-t-2  border-secondary">
  <div class="container mx-auto px-5 py-10 space-y-8  pb-24">
      <h2 class="text-lg font-semibold">Baustein-Beschreibungen</h2>
      <p class="text-sm text-gray-500">Noch keine Baustein-Beschreibungen hinterlegt.</p>
  </div>
</section>



<section class="bg-blue-50 overflow-x-hidden">
  <div class="container mx-auto px-5 py-10  pb-24" >
    <div class="flex items-center justify-between mb-8">
      <h3 class="text-lg font-semibold">Weitere Kurse</h3>
      <div class="flex items-center gap-2">
        @if($prev)
          <x-buttons.button-basic :size="'sm'"
            href="{{ route('user.program.course.show', ['klassenId' => $prev['klassen_id']]) }}"
            wire:navigate>← Vorheriger</x-buttons.button-basic>
        @endif
        @if($next)
          <x-buttons.button-basic :size="'sm'"
            href="{{ route('user.program.course.show', ['klassenId' => $next['klassen_id']]) }}"
            wire:navigate>Nächster →</x-buttons.button-basic>
        @endif
      </div>
    </div>

    {{-- SWIPER: Weitere Kurse --}}
    <div
      x-data="{
        swiper: null,
        initSwiper() {
          this.swiper = new Swiper(this.$refs.coursesSwiper, {
            slidesPerView: 'auto',
            spaceBetween: 16,
            slidesOffsetBefore: 0,
            slidesOffsetAfter: 0,
            speed: 500,
            loop: false,
            freeMode: true,
            autoHeight: false,
            keyboard: { enabled: true },
            navigation: {
              nextEl: this.$refs.nextBtn,
              prevEl: this.$refs.prevBtn,
            },
            pagination: {
              el: this.$refs.paginationEl,
              clickable: true,
            },
            breakpoints: {
              640: { spaceBetween: 20 },
              1024:{ spaceBetween: 24 },
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
            @foreach ($enrolledCourses as $rc)
              <div class="swiper-slide h-full pr-1 aspect-video" style="width: 300px;" wire:key="course-slide-{{ $rc->klassen_id }}">
                <a href="{{ route('user.program.course.show', ['klassenId' => $rc->klassen_id]) }}" 
                  class="block bg-white border rounded-xl shadow-sm p-4 hover:shadow-md transition h-full">
                  <div class="flex flex-col h-full">
                    <div>
                      <div class="flex items-start justify-between mb-2">
                        <x-ui.badge.badge :color="'gray'">{{ $rc->statusLabel }}</x-ui.badge.badge>
                        <x-ui.badge.badge :color="'gray'">
                          {{ $rc->days_count }}
                          <svg class="h-5 w-5 text-gray-500" fill="currentColor" viewBox="0 0 640 640" aria-hidden="true">
                            <path d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM160 304L160 336C160 344.8 167.2 352 176 352L208 352C216.8 352 224 344.8 224 336L224 304C224 295.2 216.8 288 208 288L176 288C167.2 288 160 295.2 160 304zM288 304L288 336C288 344.8 295.2 352 304 352L336 352C344.8 352 352 344.8 352 336L352 304C352 295.2 344.8 288 336 288L304 288C295.2 288 288 295.2 288 304zM432 288C423.2 288 416 295.2 416 304L416 336C416 344.8 423.2 352 432 352L464 352C472.8 352 480 344.8 480 336L480 304C480 295.2 472.8 288 464 288L432 288zM160 432L160 464C160 472.8 167.2 480 176 480L208 480C216.8 480 224 472.8 224 464L224 432C224 423.2 216.8 416 208 416L176 416C167.2 416 160 423.2 160 432zM304 416C295.2 416 288 423.2 288 432L288 464C288 472.8 295.2 480 304 480L336 480C344.8 480 352 472.8 352 464L352 432C352 423.2 344.8 416 336 416L304 416zM416 432L416 464C416 472.8 423.2 480 432 480L464 480C472.8 480 480 472.8 480 464L480 432C480 423.2 472.8 416 464 416L432 416C423.2 416 416 423.2 416 432z"/>
                          </svg>
                        </x-ui.badge.badge>
                      </div>
                      <h3 class="font-medium line-clamp-2 pe-2">{{ $rc->title }}</h3>
                      <p class="text-sm text-gray-500 mt-1 line-clamp-1">{{ $rc->zeitraum_fmt ?? '—' }}</p>
                    </div>
                    <div class="mt-3 flex items-center justify-between mt-auto">
                      
                      <span class="text-xs text-blue-700 hover:underline">Details</span>
                    </div>
                  </div>
                </a>
              </div>
            @endforeach


          {{-- Optionaler „alle Kurse“-Slide --}}
          <div class="swiper-slide h-full pr-1" style="width: 300px;">
            <a href="{{ route('dashboard') }}"
               class="block  overflow-hidden rounded-xl shadow-sm border hover:shadow-md transition  aspect-video">
              <div class="bg-white px-4 pt-10 pb-4 h-full flex flex-col items-center justify-between">
                <div class="w-16 h-16 rounded-full bg-[#223d65] flex items-center justify-center mt-2">
                  <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                  </svg>
                </div>
                <div class="h-16 flex items-end justify-center text-center text-xs font-medium text-gray-600">
                  alle Kurse ansehen
                </div>
              </div>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>




</div>