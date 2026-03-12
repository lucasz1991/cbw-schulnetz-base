<div class="bg-slate-50">
  @php
    $status = $course['status'] ?? 'Offen';
    $statusClasses = match ($status) {
      'Geplant' => 'bg-amber-100 text-amber-800 border border-amber-200',
      'Laufend' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
      'Abgeschlossen' => 'bg-slate-200 text-slate-700 border border-slate-300',
      default => 'bg-white text-slate-700 border border-slate-300',
    };

    $showRatingReminder = !$hasCurrentCourseRating && $isCompletedCourse;
    $showMaterialsReminder = $hasCourseMaterials && !$hasCurrentCourseMaterialsAck && !$isFutureCourse;

    $participantPercent = is_null($participantScore) ? null : max(0, min(100, (int) round($participantScore)));
    $classPercent = is_null($classAverage) ? null : max(0, min(100, (int) round($classAverage)));
    $reminderBaseDelay = 320;
    $ratingReminderDelay = $reminderBaseDelay;
    $materialsReminderDelay = $showRatingReminder ? ($reminderBaseDelay * 2) : $reminderBaseDelay;
  @endphp

  <section class="container mx-auto px-5 py-8">
    @if($showRatingReminder || $showMaterialsReminder)
      <div class="mb-5 grid grid-cols-1 gap-3">
        @if($showRatingReminder)
          <x-ui.animation.anim-container
            type="fade-up"
            :duration="400"
            :delay="$ratingReminderDelay"
            :once="true"
          >
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-md">
              <div class="flex items-start gap-3">
                <div class="relative mt-0.5 h-9 w-9 shrink-0">
                  <span class="absolute inset-0 inline-flex rounded-full bg-amber-300/70 animate-ping"></span>
                  <span class="relative z-10 inline-flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-white text-amber-700">
                    <i class="fal fa-star animate-pulse"></i>
                  </span>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-amber-900">Bewertung ausstehend</p>
                  <p class="mt-1 text-sm text-amber-800">Bitte bewerte diesen Baustein, damit dein Feedback berücksichtigt wird.</p>
                </div>
                <x-buttons.button-basic
                  :size="'sm'"
                  class="!rounded-xl !bg-white"
                  @click="$dispatch('open-course-rating-modal',[{ course_id: '{{ $klassenId }}' }]);"
                >
                  <i class="fal fa-star mr-1"></i>
                  Jetzt bewerten
                </x-buttons.button-basic>
              </div>
            </div>
          </x-ui.animation.anim-container>
        @endif

        @if($showMaterialsReminder)
          <x-ui.animation.anim-container
            type="fade-up"
            :duration="400"
            :delay="$materialsReminderDelay"
            :once="true"
          >
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-md">
              <div class="flex items-start gap-3">
                <div class="relative mt-0.5 h-9 w-9 shrink-0">
                  <span class="absolute inset-0 inline-flex rounded-full bg-amber-300/70 animate-ping"></span>
                  <span class="relative z-10 inline-flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-white text-amber-700">
                    <i class="fal fa-books animate-pulse"></i>
                  </span>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-amber-900">Materialbestätigung fehlt</p>
                  <p class="mt-1 text-sm text-amber-800">Bestätige den Erhalt deiner Bildungsmittel im Tab "Materialien".</p>
                </div>
                <x-buttons.button-basic
                  :size="'sm'"
                  class="!rounded-xl !bg-white"
                  x-on:click.prevent="selectedTab = 'material'; localStorage.setItem('selectedTabcourse-{{ $course['id'] }}', JSON.stringify('material')); localStorage.setItem('acc-course-{{ $course['id'] }}', JSON.stringify('resources')); $nextTick(() => { window.dispatchEvent(new CustomEvent('accordion-set', { detail: { group: 'course-{{ $course['id'] }}', id: 'resources' } })); document.getElementById('tabpanel-material')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); });"
                >
                  <i class="fal fa-books mr-1"></i>
                  Zu Bildungsmitteln
                </x-buttons.button-basic>
              </div>
            </div>
          </x-ui.animation.anim-container>
        @endif
      </div>
    @endif

    <div class="rounded-3xl border border-blue-200 bg-gradient-to-br from-sky-50 via-blue-100 to-indigo-100 p-6 shadow-md">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
          <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium {{ $statusClasses }}">
            <span class="h-2 w-2 rounded-full {{ $status === 'Laufend' ? 'bg-emerald-700' : ($status === 'Geplant' ? 'bg-amber-700' : 'bg-slate-500') }}"></span>
            {{ $status }}
          </span>

          <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm text-slate-700">
            <i class="fal fa-calendar-alt text-slate-500"></i>
            {{ $course['zeitraum_fmt'] ?? '-' }}
          </span>
        </div>
      </div>

      <h1 class="mt-5 text-2xl font-semibold leading-tight text-slate-900 md:text-3xl">
        {{ $course['title'] ?? '-' }}
      </h1>

      <p class="mt-2 max-w-3xl text-sm text-slate-600">
        {{ !empty($course['description']) ? \Illuminate\Support\Str::limit($course['description'], 240) : '' }}
      </p>

      <div class="mt-5 flex flex-wrap items-center gap-2 text-xs text-slate-600">
        <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 ring-1 ring-slate-200">
          <i class="fal fa-door-open"></i>
          Raum: {{ filled($course['room'] ?? null) ? $course['room'] : 'folgt' }}
        </span>
        <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 ring-1 ring-slate-200">
          <i class="fal fa-calendar"></i>
          {{ $stats['tage'] ?? 0 }} Tage
        </span>
      </div>
    </div>
  </section>

  <section class="container mx-auto px-5 pb-8">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-md">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-xs uppercase tracking-wide text-slate-500">Dozent/-in</p>
            <div class="mt-2 text-sm text-slate-900">
              <x-user.public-info :person="$tutor" />
            </div>
          </div>
          <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
            <i class="fal fa-chalkboard-teacher"></i>
          </span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-md">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">Unterrichtstage</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['tage'] ?? '-' }}</p>
          </div>
          <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
            <i class="fal fa-calendar"></i>
          </span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-md">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">Einheiten</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['einheiten'] ?? '-' }}</p>
          </div>
          <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
            <i class="fal fa-clock"></i>
          </span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-md">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">Teilnehmer</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $participantsCount }}</p>
          </div>
          <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
            <i class="fal fa-users"></i>
          </span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-md">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-xs uppercase tracking-wide text-slate-500">Raum</p>
            <p class="mt-1 truncate text-xl font-semibold text-slate-900">{{ $course['room'] ?? '-' }}</p>
          </div>
          <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
            <i class="fal fa-door-open"></i>
          </span>
        </div>
      </div>
    </div>
  </section>

  <section class="container mx-auto px-5 pb-12">
    <div class="mb-5 flex items-center justify-between">
      <h2 class="text-lg font-semibold text-slate-900">Ergebnisse</h2>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-md">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm text-slate-500">Dein Ergebnis</p>
            <p class="mt-1 text-xs text-slate-400">Durchschnitt deiner Punkte in diesem Baustein</p>
          </div>
          <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800">
            {{ !is_null($participantScore) ? number_format($participantScore, 0) . ' / 100' : '-' }}
          </span>
        </div>

        <div class="mt-5">
          @if(!is_null($participantPercent))
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
              <div class="h-2.5 rounded-full bg-blue-600" style="width: {{ $participantPercent }}%"></div>
            </div>
            <p class="mt-3 text-sm text-slate-600">Fortschritt: <span class="font-semibold text-slate-900">{{ $participantPercent }}%</span></p>
          @else
            <p class="text-sm text-slate-500">Noch kein Ergebnis erfasst.</p>
          @endif
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-md">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-sm text-slate-500">Klassenschnitt</p>
            <p class="mt-1 text-xs text-slate-400">Durchschnitt aller bewerteten Teilnehmenden</p>
          </div>
          <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">
            {{ !is_null($classAverage) ? number_format($classAverage, 0) . ' / 100' : '-' }}
          </span>
        </div>

        <div class="mt-5">
          @if(!is_null($classPercent))
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
              <div class="h-2.5 rounded-full bg-slate-400" style="width: {{ $classPercent }}%"></div>
            </div>
            <p class="mt-3 text-sm text-slate-600">Klassenlevel: <span class="font-semibold text-slate-900">{{ $classPercent }}%</span></p>
          @else
            <p class="text-sm text-slate-500">Noch keine Klassenergebnisse vorhanden.</p>
          @endif
        </div>
      </div>
    </div>
  </section>
</div>
