@php
    use Carbon\Carbon;

    $start = $course->planned_start_date ? Carbon::parse($course->planned_start_date) : null;
    $end   = $course->planned_end_date   ? Carbon::parse($course->planned_end_date)   : null;

    $now = now();
    $isRunning = $start && $end ? $now->between($start, $end) : false;
    $isFuture  = $start ? $now->lt($start) : false;
    $isPast    = $end ? $now->gt($end) : false;

    $statusClasses = 'bg-gray-100 text-gray-700';
    $statusIcon    = 'fa-calendar';
    $statusLabel   = $start || $end ? 'geplant' : 'ohne Zeitraum';

    if ($isRunning) {
        $statusClasses = 'bg-amber-100 text-amber-800';
        $statusIcon    = 'fa-play-circle';
        $statusLabel   = 'läuft';
    } elseif ($isFuture) {
        $statusClasses = 'bg-blue-100 text-blue-800';
        $statusIcon    = 'fa-hourglass-start';
        $statusLabel   = $start && $start->isToday() ? 'heute' : 'bevorstehend';
    } elseif ($isPast) {
        $statusClasses = 'bg-gray-200 text-gray-600';
        $statusIcon    = 'fa-check-circle';
        $statusLabel   = 'beendet';
    }

    $short = data_get($course->source_snapshot, 'course.kurzbez') ?: 'Ohne Kurzbezeichnung';
@endphp

<div class="mb-24">
    <x-slot name="header">
        <div class="px-4 flex items-center gap-3">
            <x-back-button />
            <span class="flex items-center gap-2">
                <i class="fas fa-chalkboard-teacher text-gray-500"></i>
                Kurs im Detail
            </span>
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- =========================
            DESIGN UPDATE: HERO HEADER
        ========================== --}}
        <div class="">
            <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

                <div class="relative p-5 sm:p-6">
                    <div class="flex flex-col lg:flex-row gap-4 lg:gap-6">

 

                        {{-- middle: title + chips --}}
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-2 {{ $statusClasses }} text-[11px] font-semibold px-3 py-1 rounded-full">
                                    <i class="fas {{ $statusIcon }} text-[12px]"></i>
                                    {{ $statusLabel }}
                                </span>

                                <span class="inline-flex items-center gap-2 bg-white/80 backdrop-blur border border-gray-200 text-gray-700 text-[11px] font-semibold px-3 py-1 rounded-full">
                                    <i class="fas fa-tag text-gray-400 text-[12px]"></i>
                                    <span class="truncate max-w-[220px] sm:max-w-[420px]">{{ $short }}</span>
                                </span>
                            </div>

                            <div class="min-w-0">
                                <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 leading-tight truncate">
                                    {{ $course->title }}
                                </h1>
                            </div>

                            {{-- stats row --}}
                            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 w-min">
                                <div class="flex-1 rounded-xl border border-gray-200 bg-white/70 backdrop-blur p-3">
                                    <div class="flex items-center justify-between  gap-2">
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                <i class="fas fa-calendar-day"></i>
                                            </span>
                                            <span class="text-sm font-semibold">Termine</span>
                                        </div>
                                        <div class="text-lg font-extrabold text-gray-900 leading-none">
                                            {{ $course->dates_count ?? 0 }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex-1 rounded-xl border border-gray-200 bg-white/70 backdrop-blur p-3">
                                    <div class="flex items-center justify-between  gap-2">
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-700 border border-purple-100">
                                                <i class="fas fa-users"></i>
                                            </span>
                                            <span class="text-sm font-semibold">Teilnehmer</span>
                                        </div>
                                        <div class="text-lg font-extrabold text-gray-900 leading-none">
                                            {{ $course->participants_count ?? 0 }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- right: timeframe --}}
                        <div class="w-full lg:w-[320px] shrink-0">
                            <div class="h-full rounded-2xl border border-gray-200 bg-white/70 backdrop-blur p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2 text-gray-800">
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700 border border-blue-100">
                                            <i class="fas fa-clock"></i>
                                        </span>
                                        <span class="text-sm font-semibold">Zeitraum</span>
                                    </div>

                                    @if($start || $end)
                                        <span class="text-[11px] font-semibold text-gray-500 inline-flex items-center gap-1">
                                            <i class="fas fa-calendar-alt"></i>
                                            {{ $start ? $start->diffForHumans($now, ['parts' => 2, 'short' => true]) : '—' }}
                                        </span>
                                    @endif
                                </div>

                                <div class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                                <i class="fas fa-play text-[11px]"></i>
                                            </span>
                                            <span class="font-semibold">Start</span>
                                        </div>
                                        <div class="text-gray-700 font-medium">
                                            {{ $start ? $start->isoFormat('ll') : '—' }}
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                                <i class="fas fa-flag-checkered text-[11px]"></i>
                                            </span>
                                            <span class="font-semibold">Ende</span>
                                        </div>
                                        <div class="text-gray-700 font-medium">
                                            {{ $end ? $end->isoFormat('ll') : '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- Accordion bleibt unverändert --}}
        <x-ui.accordion.tabs
            :tabs="[
                'anwesenheit' => ['label' => 'Anwesenheit',   'icon' => 'fad fa-user-clock'],
                'doku'        => ['label' => 'Dokumentation', 'icon' => 'fad fa-file-signature'],
                'medien'      => ['label' => 'Materialien',   'icon' => 'fad fa-books'],
                'results'     => ['label' => 'Ergebnisse',    'icon' => 'fad fa-poll-people'],
                'invoice'     => ['label' => 'Rechnung',      'icon' => 'fad fa-file-invoice-dollar']
            ]"
            :collapseAt="'lg'"
            default="doku"
            class="mt-8"
        >
            <x-ui.accordion.tab-panel for="anwesenheit">
                <livewire:tutor.courses.participants-table :courseId="$course->id" />
            </x-ui.accordion.tab-panel>
            <x-ui.accordion.tab-panel for="doku">
                <livewire:tutor.courses.course-documentation-panel :courseId="$course->id" />
            </x-ui.accordion.tab-panel>
            <x-ui.accordion.tab-panel for="medien">
                <livewire:tutor.courses.manage-course-media :course="$course" lazy />
            </x-ui.accordion.tab-panel>
            <x-ui.accordion.tab-panel for="results">
                <livewire:tutor.courses.manage-course-results :course="$course" lazy />
            </x-ui.accordion.tab-panel>
            <x-ui.accordion.tab-panel for="invoice">
                <livewire:tutor.courses.manage-course-invoice :course="$course" lazy />
            </x-ui.accordion.tab-panel>
        </x-ui.accordion.tabs>

    </div>
</div>
