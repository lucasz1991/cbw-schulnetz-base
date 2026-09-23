<div class="">
    @php
        $coachingUnits = static fn ($value) => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    @endphp
    @if(count($coachingDashboard['contracts']))
        <section class="mt-6 space-y-4" aria-labelledby="tutor-coaching-title">
            <div class="flex flex-wrap items-end justify-between gap-3 px-1">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-secondary-600">Dein Arbeitsbereich</p>
                    <h1 id="tutor-coaching-title" class="mt-1 text-xl font-semibold text-gray-800">Einzelcoaching</h1>
                    <p class="mt-1 text-sm text-gray-500">Terminpläne abstimmen und freigegebene Bausteine öffnen.</p>
                </div>
                <a href="{{ route('coaching.planning') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-secondary-700 hover:bg-secondary-50 focus:outline-none focus:ring-2 focus:ring-secondary-300">Alle Abstimmungen <i class="fal fa-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                @foreach([
                    ['label' => 'Zugeordnete Coachings', 'mobile' => 'Coachings', 'value' => count($coachingDashboard['contracts']), 'icon' => 'fa-layer-group', 'tone' => 'text-secondary-700 bg-secondary-50'],
                    ['label' => 'Deine Bestätigung offen', 'mobile' => 'Offen', 'value' => $coachingDashboard['open_count'], 'icon' => 'fa-calendar-edit', 'tone' => 'text-amber-700 bg-amber-50'],
                    ['label' => 'Freigegebene Bausteine', 'mobile' => 'Freigegeben', 'value' => $coachingDashboard['ready_count'], 'icon' => 'fa-check-circle', 'tone' => 'text-primary-700 bg-primary-50'],
                ] as $stat)
                    <div class="min-w-0 rounded-xl border border-gray-200 bg-white p-3 sm:p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-1"><p class="text-[11px] sm:text-sm text-gray-500"><span class="sm:hidden">{{ $stat['mobile'] }}</span><span class="hidden sm:inline">{{ $stat['label'] }}</span></p><span class="hidden sm:inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $stat['tone'] }}"><i class="fal {{ $stat['icon'] }}" aria-hidden="true"></i></span></div>
                        <p class="mt-2 text-xl sm:text-2xl font-semibold text-gray-800">{{ $stat['value'] }}</p>
                    </div>
                @endforeach
            </div>
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.65fr)_minmax(280px,1fr)]">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4"><h2 class="font-semibold text-gray-800">Meine Coachings</h2><span class="text-xs text-gray-500">{{ count($coachingDashboard['contracts']) }} Bausteine</span></div>
                    <div class="divide-y divide-gray-100">
                        @foreach(array_slice($coachingDashboard['contracts'], 0, 3) as $entry)
                            <article class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div class="min-w-0"><h3 class="font-semibold text-gray-800 break-words">{{ $entry['title'] }}</h3><p class="mt-1 text-sm text-gray-500">{{ $entry['participant'] }} · {{ $coachingUnits($entry['units']) }} UE</p><p class="mt-1 text-xs {{ $entry['open'] ? 'text-amber-700' : ($entry['ready'] ? 'text-primary-700' : 'text-secondary-700') }}">{{ $entry['label'] }}</p></div>
                                <a href="{{ $entry['course_url'] ?: $entry['url'] }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-secondary-700 hover:bg-secondary-50 focus:outline-none focus:ring-2 focus:ring-secondary-300">{{ $entry['ready'] ? 'Baustein öffnen' : 'Plan öffnen' }} <i class="fal fa-arrow-right" aria-hidden="true"></i></a>
                            </article>
                        @endforeach
                    </div>
                </div>
                <aside class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-label="Nächster Coaching-Schritt">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-secondary-50 text-secondary-700"><i class="fal fa-calendar-check" aria-hidden="true"></i></span>
                    @if($coachingDashboard['open_count'])
                        <h2 class="mt-4 font-semibold text-gray-800">Terminplan braucht deine Rückmeldung</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500">Stimme den vollständigen Plan mit dem Teilnehmer ab. Vor der Freigabe müssen alle Termine feststehen.</p>
                        <a href="{{ collect($coachingDashboard['contracts'])->firstWhere('open', true)['url'] }}" class="mt-4 inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-secondary-700 hover:underline">Zur Abstimmung <i class="fal fa-arrow-right" aria-hidden="true"></i></a>
                    @elseif($coachingDashboard['next_session'])
                        <h2 class="mt-4 font-semibold text-gray-800">Dein nächster Coaching-Termin</h2>
                        <p class="mt-2 text-sm text-gray-500">{{ $coachingDashboard['next_session']['date_label'] }} · {{ $coachingDashboard['next_session']['start'] }}–{{ $coachingDashboard['next_session']['end'] }} Uhr</p>
                        <p class="mt-1 font-semibold text-gray-800">{{ $coachingDashboard['next_session']['topic'] }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $coachingDashboard['next_session']['participant'] }}</p>
                        <a href="{{ $coachingDashboard['next_session']['url'] }}" class="mt-4 inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-secondary-700 hover:underline">Baustein öffnen <i class="fal fa-arrow-right" aria-hidden="true"></i></a>
                    @else
                        <h2 class="mt-4 font-semibold text-gray-800">Alles im Blick</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500">Aktuell ist keine Bestätigung durch dich offen. Den Stand jedes Coachings findest du in der Übersicht.</p>
                    @endif
                </aside>
            </div>
        </section>
    @endif
    {{-- Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6">

        {{-- Left: Kurse --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Card: Aktuelle Kurse --}}
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="h-1 w-full bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600"></div>

                <div class="p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg sm:text-xl font-extrabold text-gray-700 flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 border border-blue-100 text-blue-700">
                                    <i class="fas fa-graduation-cap"></i>
                                </span>
                                <span class="truncate">Aktuelle Kurse</span>
                            </h2>
                            <p class="mt-1 text-sm text-gray-500">
                                Schnellzugriff auf deine aktiven Kurse und Details.
                            </p>
                        </div>

                        <a
                            href="{{ route('tutor.courses') }}"
                            wire:navigate
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            <i class="fas fa-list"></i>
                            <span class="hidden sm:inline">Alle Kurse</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <livewire:tutor.courses.courses-list-preview />
                    </div>

                </div>
            </div>

        </div>

        {{-- Right: Sidebar --}}
        <div class="lg:col-span-4 space-y-6" x-data="{ modalOpen:false, open:false, selectedMessage:null }">

            {{-- Card: Nachrichten --}}
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-base sm:text-lg font-extrabold text-gray-700 flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700">
                                    <i class="fas fa-envelope-open-text"></i>
                                </span>
                                <span class="truncate">Nachrichten</span>
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">Neueste Mitteilungen und Hinweise.</p>
                        </div>

                        <a
                            href="{{ route('messages') }}"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            <i class="fas fa-inbox"></i>
                            <span class="hidden sm:inline">Alle</span>
                        </a>
                    </div>

                    @php
                        $receivedMessages = $receivedMessages ?? [];
                    @endphp

                    <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white">
                        @forelse($receivedMessages as $message)
                            <button
                                type="button"
                                @click="
                                    modalOpen = true;
                                    open = false;
                                    selectedMessage = {
                                        subject: @js($message->subject),
                                        body: @js($message->message),
                                        createdAt: @js($message->created_at->diffForHumans())
                                    };
                                    $wire.setMessageStatus({{ $message->id }});
                                "
                                class="w-full text-left flex items-start gap-3 p-4 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            >
                                <div class="shrink-0">
                                    <div class="h-10 w-10 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center">
                                        <i class="fas fa-comment-dots text-gray-500"></i>
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="font-semibold text-gray-700 truncate">
                                            {{ $message->subject }}
                                        </div>

                                        @if(($message->status ?? null) == 1)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-200 px-2 py-0.5 text-[11px] font-semibold">
                                                <i class="fas fa-circle text-[8px]"></i>
                                                Neu
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-1 text-sm text-gray-600">
                                        {{ Str::limit(strip_tags($message->message), 70) }}
                                    </div>

                                    <div class="mt-2 text-[11px] text-gray-500 flex items-center gap-2">
                                        <i class="far fa-clock"></i>
                                        {{ $message->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            </button>

                            @if(! $loop->last)
                                <div class="h-px bg-gray-200"></div>
                            @endif
                        @empty
                            <div class="p-6 text-center">
                                <div class="mx-auto h-12 w-12 rounded-2xl border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-500">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-gray-700">Keine Nachrichten</p>
                                <p class="mt-1 text-sm text-gray-500">Aktuell liegen keine neuen Mitteilungen vor.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('messages') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:underline">
                            Alle Nachrichten ansehen
                            <i class="fas fa-arrow-right text-[12px]"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Card: Nächste Termine --}}
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-base sm:text-lg font-extrabold text-gray-700 flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700">
                                    <i class="fas fa-calendar-check"></i>
                                </span>
                                <span class="truncate">Nächste Termine</span>
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">{{ count($coachingDashboard['contracts']) ? 'Dein nächster bestätigter Coaching-Termin.' : 'Anstehende Termine und Deadlines.' }}</p>
                        </div>
                    </div>

                    <ul class="mt-4 space-y-2">
                        @if($coachingDashboard['next_session'])
                            <li class="rounded-xl border border-secondary-100 bg-secondary-50/60 p-4">
                                <p class="text-xs font-semibold text-secondary-700">{{ $coachingDashboard['next_session']['date_label'] }} · {{ $coachingDashboard['next_session']['start'] }}–{{ $coachingDashboard['next_session']['end'] }} Uhr</p>
                                <p class="mt-2 text-sm font-semibold text-gray-800">{{ $coachingDashboard['next_session']['topic'] }}</p>
                                <p class="mt-1 text-xs text-gray-600">{{ $coachingDashboard['next_session']['participant'] }}</p>
                                <a href="{{ $coachingDashboard['next_session']['url'] }}" class="mt-3 inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-secondary-700 hover:underline">Baustein öffnen <i class="fal fa-arrow-right" aria-hidden="true"></i></a>
                            </li>
                        @else
                        <li class="rounded-xl border border-gray-200 bg-gray-50/60 p-3 flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-600">
                                    <i class="fas fa-calendar-day"></i>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-700 truncate">Keine Termine</div>
                                    <div class="text-sm text-gray-500 truncate">Aktuell ist nichts geplant.</div>
                                </div>
                            </div>
                            <span class="text-[11px] font-semibold text-gray-500 whitespace-nowrap">
                                —
                            </span>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>
