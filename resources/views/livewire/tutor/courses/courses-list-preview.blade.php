{{-- resources/views/livewire/tutor/courses/courses-list-preview.blade.php --}}
<div>
    @if($courses->count())
        <div class="mb-4 flex items-center justify-between gap-3">
            <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-200 px-3 py-1 text-xs font-semibold">
                <i class="fas fa-layer-group text-[12px]"></i>
                {{ $courses->count() }} Kurs{{ $courses->count() === 1 ? '' : 'e' }}
            </span>

            <span class="hidden sm:inline-flex items-center gap-2 text-xs text-gray-500">
                <i class="far fa-calendar-alt"></i>
                Vorschau
            </span>
        </div>
    @endif

    <div class="grid gap-4 grid-cols-1">
        @forelse ($courses as $course)
            @php
                $start = $course->planned_start_date ? \Illuminate\Support\Carbon::parse($course->planned_start_date)->timezone('Europe/Berlin') : null;
                $end   = $course->planned_end_date   ? \Illuminate\Support\Carbon::parse($course->planned_end_date)->timezone('Europe/Berlin')   : null;

                $now = \Illuminate\Support\Carbon::now('Europe/Berlin');
                $weekStart = $now->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->startOfDay();
                $weekEnd   = $now->copy()->endOfWeek(\Illuminate\Support\Carbon::SUNDAY)->endOfDay();

                $hasWindow = $start && $end;

                $isRunningNormal = $hasWindow ? $now->between($start, $end) : false;
                $endedThisWeek   = $end ? $end->between($weekStart, $weekEnd) : false;
                $startedAlready  = $start ? $start->lte($now) : false;

                $isRunningWithGrace = ($startedAlready && ($isRunningNormal || $endedThisWeek));

                $isFuture  = $start ? $now->lt($start) : false;
                $isPast    = $end ? $now->gt($end) : false;

                // unified status pill
                $statusPill = 'bg-gray-50 text-gray-700 ring-1 ring-gray-200';
                $statusIcon = 'fas fa-calendar';
                $statusLabel = ($start || $end) ? 'geplant' : 'ohne Zeitraum';

                if ($isRunningWithGrace) {
                    $statusPill  = 'bg-amber-50 text-amber-900 ring-1 ring-amber-200';
                    $statusIcon  = 'fas fa-play-circle';
                    $statusLabel = 'läuft';
                } elseif ($isFuture) {
                    $statusPill  = 'bg-blue-50 text-blue-800 ring-1 ring-blue-200';
                    $statusIcon  = ($start && $start->isToday()) ? 'fas fa-bolt' : 'fas fa-hourglass-start';
                    $statusLabel = ($start && $start->isToday()) ? 'heute' : 'bevorstehend';
                } elseif ($isPast) {
                    $statusPill  = 'bg-gray-100 text-gray-600 ring-1 ring-gray-200';
                    $statusIcon  = 'fas fa-check-circle';
                    $statusLabel = 'beendet';
                }

                $short = data_get($course->source_snapshot, 'course.kurzbez') ?: 'Ohne Kurzbezeichnung';
                $title = $course->title ?: 'Ohne Titel';
            @endphp

            <a
                href="{{ route('tutor.courses.show', ['courseId' => $course->id]) }}"
                class="group/courselistitem relative block overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md hover:border-gray-300"
            >
                {{-- hover accent --}}
                <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 opacity-0 group-hover/courselistitem:opacity-100 transition"></div>

                <div class="p-4 sm:p-5">
                    {{-- Header row --}}
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold shadow-sm {{ $statusPill }}" title="{{ $statusLabel }}">
                                <i class="{{ $statusIcon }} text-[12px]"></i>
                                <span class="hidden sm:inline">{{ $statusLabel }}</span>
                            </span>

                            @if($start || $end)
                                <span class="inline-flex items-center gap-2 rounded-full bg-white text-gray-700 ring-1 ring-gray-200 px-3 py-1 text-[11px] font-medium">
                                    <i class="far fa-calendar-alt text-[12px] text-gray-500"></i>
                                    <span class="truncate">
                                        {{ $start ? $start->isoFormat('ll') : '—' }}
                                        <span class="text-gray-400">–</span>
                                        {{ $end ? $end->isoFormat('ll') : '—' }}
                                    </span>
                                </span>
                            @endif
                        </div>

                        {{-- Right: quick action hint --}}
                        <div class="hidden sm:flex items-center gap-2 text-xs text-gray-400">
                            <span class="inline-flex items-center gap-2">
                                Details
                                <i class="fas fa-arrow-right text-[12px] opacity-70 group-hover/courselistitem:opacity-100 transition"></i>
                            </span>
                        </div>
                    </div>

                    {{-- Title block --}}
                    <div class="mt-3 flex items-start gap-3">

                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-gray-600 truncate flex items-center gap-2">
                                <i class="fas fa-tag text-gray-400"></i>
                                {{ $short }}
                            </p>

                            <h3 class="mt-0.5 text-base sm:text-lg font-extrabold text-gray-900 leading-snug truncate">
                                {{ $title }}
                            </h3>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 px-3 py-1 text-[11px] font-semibold">
                                    <i class="fas fa-calendar-day text-[12px]"></i>
                                    {{ $course->dates_count ?? 0 }}
                                    <span class="hidden sm:inline">Termin{{ ($course->dates_count ?? 0) === 1 ? '' : 'e' }}</span>
                                </span>

                                <span class="inline-flex items-center gap-2 rounded-full bg-purple-50 text-purple-800 ring-1 ring-purple-200 px-3 py-1 text-[11px] font-semibold">
                                    <i class="fas fa-users text-[12px]"></i>
                                    {{ $course->participants_count ?? 0 }}
                                    <span class="hidden sm:inline">Teilnehmer</span>
                                </span>

                                @if(!empty($course->room))
                                    <span class="inline-flex items-center gap-2 rounded-full bg-indigo-50 text-indigo-800 ring-1 ring-indigo-200 px-3 py-1 text-[11px] font-semibold">
                                        <i class="fas fa-door-open text-[12px]"></i>
                                        <span class="hidden sm:inline">Raum</span>
                                        <span class="sm:hidden">R</span>:
                                        {{ $course->room }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm">
                <div class="mx-auto h-12 w-12 rounded-2xl border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-500">
                    <i class="fas fa-folder-open"></i>
                </div>
                <p class="mt-3 text-sm font-semibold text-gray-700">Aktuell sind dir keine Kurse zugewiesen.</p>
                <p class="mt-1 text-sm text-gray-500">Sobald Kurse zugeordnet sind, erscheinen sie hier automatisch.</p>
            </div>
        @endforelse
    </div>
</div>
