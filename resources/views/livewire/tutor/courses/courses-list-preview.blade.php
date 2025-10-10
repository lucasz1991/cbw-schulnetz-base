<div>
    @if($courses->count())
        <div class="mb-4">
            <span class="inline-block bg-blue-100 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">
                {{ $courses->count() }} Kurs{{ $courses->count() === 1 ? '' : 'e' }}
            </span>
        </div>
    @endif

    <div class="grid gap-4 grid-cols-1">
        @forelse ($courses as $course)
            @php
                $start = $course->planned_start_date ? \Carbon\Carbon::parse($course->planned_start_date) : null;
                $end   = $course->planned_end_date   ? \Carbon\Carbon::parse($course->planned_end_date)   : null;

                $now = now();
                $isRunning = $start && $end ? $now->between($start, $end) : false;
                $isFuture  = $start ? $now->lt($start) : false;
                $isPast    = $end ? $now->gt($end) : false;

                $statusClasses = 'bg-gray-100 text-gray-700';
                $statusLabel   = $start || $end ? 'geplant' : 'ohne Zeitraum';
                if ($isRunning) { $statusClasses = 'bg-yellow-100 text-yellow-800'; $statusLabel = 'läuft'; }
                elseif ($isFuture) {
                    $statusClasses = 'bg-blue-100 text-blue-800';
                    $statusLabel   = $start && $start->isToday() ? 'heute' : 'bevorstehend';
                }
                elseif ($isPast) { $statusClasses = 'bg-gray-200 text-gray-600'; $statusLabel = 'beendet'; }
            @endphp

            <a href="{{ route('tutor.courses.show', ['courseId' => $course->id]) }}"
               class="block bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition hover:bg-gray-50">

                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-2">
                    {{-- Linke Seite: Titel + Badges --}}
                    <div class="min-w-0">
                        <span class="inline-block {{ $statusClasses }} text-[11px] font-semibold px-2.5 py-1 rounded-full">
                                {{ $statusLabel }}
                        </span>
                    </div>

                    {{-- Rechte Seite: Zähler + ID --}}
                    <div class="flex flex-wrap gap-2 sm:justify-end">
                        @if($start || $end)
                                <span class="inline-block bg-gray-100 text-gray-700 text-[11px] font-medium px-2.5 py-1 rounded-full">
                                    {{ $start ? $start->isoFormat('ll') : '—' }} – {{ $end ? $end->isoFormat('ll') : '—' }}
                                </span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    {{-- Linke Seite: Titel + Badges --}}
                    <div class="min-w-0">
                        <h3 class="text-base sm:text-md font-semibold text-gray-600 truncate">
                            {{ $course->title ?: 'Ohne Titel' }}
                        </h3>

                        <div class="mt-2 flex flex-wrap items-center gap-2">



                            </div>
                        </div>
                        
                        {{-- Rechte Seite: Zähler + ID --}}
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            {{-- Linke Seite: Titel + Badges --}}
                            <div class="min-w-0">
                                <span class="inline-flex items-center bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full">
                                    {{ $course->dates_count ?? 0 }} Termin{{ ($course->dates_count ?? 0) === 1 ? '' : 'e' }}
                                </span>
                                <span class="inline-flex items-center bg-purple-100 text-purple-700 text-xs px-2.5 py-1 rounded-full">
                                    {{ $course->participants_count ?? 0 }} Teilnehmer
                                </span>
                            </div>
                            
                            {{-- Rechte Seite: Zähler + ID --}}
                            <div class="flex flex-wrap gap-2 sm:justify-end">
                                
                                @if(!empty($course->room))
                                    <span class="inline-block bg-indigo-100 text-indigo-700 text-[11px] font-medium px-2.5 py-1 rounded-full">
                                        Raum: {{ $course->room }}
                                    </span>
                                @endif
                    </div>
                </div>
            </a>
        @empty
            <p class="text-sm text-gray-500 col-span-full">Aktuell sind dir keine Kurse zugewiesen.</p>
        @endforelse
    </div>
</div>
