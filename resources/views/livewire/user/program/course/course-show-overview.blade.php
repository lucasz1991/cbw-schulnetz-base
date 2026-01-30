<div class="">

  <section class="container mx-auto px-5 py-10">
    @if(!$hasCurrentCourseRating && !empty($course['end']) && \Illuminate\Support\Carbon::parse($course['end'])->lt(now()))
      <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white text-amber-600 border border-amber-200">
            <i class="fal fa-star text-lg"></i>
          </span>
          <div class="text-sm">
            <div class="font-semibold text-amber-900">Bewertung ausstehend</div>
            <div class="text-amber-800/90">Bitte bewerte diesen Baustein.</div>
          </div>
        </div>
        <div class="flex-1"></div>
        <x-buttons.button-basic
          :size="'sm'"
          class="!rounded-xl"
          @click="$dispatch('open-course-rating-modal', { course_id: '{{ $klassenId }}' });"
        >
          Jetzt bewerten
                  <i class="fa fa-star text-[18px] text-slate-300 ml-2 hover:text-yellow-400 animate-pulse"></i>
        </x-buttons.button-basic>
      </div>
    @endif
    <div class="flex flex-wrap items-start justify-between gap-4">

      {{-- Status --}}
      <div>
@php
  $status = $course['status'] ?? 'Offen';

  $badge = [
    'Geplant' => 'bg-yellow-50 text-yellow-800 border border-yellow-200',
    'Laufend' => 'bg-blue-50 text-blue-800 border border-blue-200',
    'Abgeschlossen' => 'bg-slate-100 text-slate-700 border border-slate-300',
    'Offen' => 'bg-white text-gray-700 border border-gray-300',
  ][$status] ?? 'bg-white text-gray-700 border border-gray-300';

  $point = [
    'Geplant' => 'bg-yellow-800',
    'Laufend' => 'bg-blue-800',
    'Abgeschlossen' => 'bg-green-700',
    'Offen' => 'bg-gray-700',
  ][$status] ?? 'bg-gray-600';
@endphp

<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium {{ $badge }}">
  <span class="w-2 h-2 rounded-full {{ $point }}"></span>
  {{ $status }}
</span>
      </div>

      {{-- Zeitraum --}}
      <div class="shrink-0">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium bg-white/15  ring-1 ring-white/20">
          <i class="fal fa-calendar-alt opacity-90"></i>
          {{ $course['zeitraum_fmt'] ?? '—' }}
        </span>
      </div>
    </div>

    <h1 class="mt-6 text-xl md:text-2xl font-semibold  leading-tight">
      {{ $course['title'] ?? '—' }}
    </h1>

    <p class="mt-2  text-sm">
      Kursübersicht & Ergebnisse
    </p>
  </section>

  {{-- =========================================================
      KPI / META CARDS
  ========================================================== --}}
  <section class="container mx-auto px-5  pb-10">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

      {{-- Tutor --}}
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs text-gray-500 mb-1">Dozent/-in</p>
            <x-user.public-info :person="$tutor" />
          </div>
          <div class="w-11 h-11 shrink-0 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
            <i class="fal fa-chalkboard-teacher text-lg"></i>
          </div>
        </div>
      </div>

      {{-- Tage --}}
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs text-gray-500">Tage</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">
              {{ $stats['tage'] ?? '—' }}
            </p>
          </div>
          <div class="w-11 h-11 shrink-0 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
            <i class="fal fa-calendar text-lg"></i>
          </div>
        </div>
      </div>

      {{-- Teilnehmer --}}
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs text-gray-500">Teilnehmer</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">
              {{ $participantsCount }}
            </p>
          </div>
          <div class="w-11 h-11 shrink-0 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center">
            <i class="fal fa-users text-lg"></i>
          </div>
        </div>
      </div>

      {{-- Raum --}}
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-xs text-gray-500">Raum</p>
            <p class="mt-1 text-xl font-semibold text-gray-900 truncate">
              {{ $course['room'] ?? '—' }}
            </p>
          </div>
          <div class="w-11 h-11bg- rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
            <i class="fal fa-door-open text-lg"></i>
          </div>
        </div>
      </div>

    </div>
  </section>

  {{-- =========================================================
      ERGEBNISSE
  ========================================================== --}}
  <section class="container mx-auto px-5 pb-12">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-lg font-semibold text-gray-900">Ergebnisse</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

      {{-- Dein Ergebnis --}}
      <div class="bg-white rounded-2xl border shadow-sm p-6">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm text-gray-500">Dein Ergebnis</p>
            <p class="mt-1 text-xs text-gray-400">Deine Bewertung in diesem Kurs</p>
          </div>

          <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-50 text-blue-700">
            @if(!is_null($participantScore))
              {{ number_format($participantScore, 0) }} / 100
            @else
              —
            @endif
          </span>
        </div>

        <div class="mt-5">
          @if(!is_null($participantScore))
            <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-2.5 bg-primary-600 rounded-full transition-all"
                   style="width: {{ max(0, min(100, (int) round($participantScore))) }}%"></div>
            </div>

            <p class="mt-3 text-sm text-gray-600">
              Note:
              <span class="font-semibold text-gray-900">{{ $participantGrade ?? '—' }}</span>
            </p>
          @else
            <p class="text-sm text-gray-500">Noch kein Ergebnis erfasst.</p>
          @endif
        </div>
      </div>

      {{-- Klassenschnitt --}}
      <div class="bg-white rounded-2xl border shadow-sm p-6">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm text-gray-500">Klassenschnitt</p>
            <p class="mt-1 text-xs text-gray-400">Ø aus allen bewerteten Bausteinen</p>
          </div>

          <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-700">
            @if(!is_null($classAverage))
              {{ $classAverage }} / 100
            @else
              —
            @endif
          </span>
        </div>

        <div class="mt-5">
          @if(!is_null($classAverage))
            <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-2.5 bg-gray-400 rounded-full"
                   style="width: {{ max(0, min(100, (int) round($classAverage))) }}%"></div>
            </div>
          @else
            <p class="text-sm text-gray-500">Noch keine Klassenergebnisse vorhanden.</p>
          @endif
        </div>
      </div>

    </div>
  </section>



</div>
