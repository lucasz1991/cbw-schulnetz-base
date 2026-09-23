@php
    $next = $dashboard['next_session'];
    $contracts = collect($dashboard['contracts']);
    $pending = $contracts->firstWhere('status', 'planning');
    $units = static fn ($value) => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
@endphp
<section class="py-5 space-y-5 md:space-y-6" aria-label="Mein Einzelcoaching">
    <dl class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 md:gap-5" aria-label="Einzelcoaching auf einen Blick">
        @foreach([
            ['label' => 'Coaching-Bausteine', 'value' => $contracts->count(), 'detail' => $dashboard['ready_count'].' von '.$contracts->count().' freigegeben', 'icon' => 'fa-layer-group', 'tone' => 'text-secondary-600 bg-secondary-50'],
            ['label' => 'Vereinbarter Umfang', 'value' => $units($dashboard['total_units']).' UE', 'detail' => 'aus deinen Coaching-Verträgen', 'icon' => 'fa-clock', 'tone' => 'text-secondary-600 bg-secondary-50'],
            ['label' => 'Fest vereinbarte Termine', 'value' => $dashboard['confirmed_count'], 'detail' => 'im bestätigten Gesamtplan', 'icon' => 'fa-calendar-check', 'tone' => 'text-primary-700 bg-primary-50'],
            ['label' => 'Offene Abstimmungen', 'value' => $dashboard['planning_count'], 'detail' => $dashboard['planning_count'] === 1 ? '1 Baustein wartet auf dich' : ($dashboard['planning_count'] ? $dashboard['planning_count'].' Bausteine warten auf dich' : 'Aktuell nichts zu bestätigen'), 'icon' => 'fa-comments', 'tone' => $dashboard['planning_count'] ? 'text-amber-700 bg-amber-50' : 'text-primary-700 bg-primary-50'],
        ] as $stat)
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-3 sm:p-4 md:p-5 min-w-0">
                <dt class="text-[11px] sm:text-xs md:text-sm text-gray-500 flex items-start justify-between gap-1 sm:gap-2">
                    <span>{{ $stat['label'] }}</span>
                    <span class="hidden sm:inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $stat['tone'] }}" aria-hidden="true"><i class="fal {{ $stat['icon'] }}"></i></span>
                </dt>
                <dd class="text-xl sm:text-2xl md:text-3xl font-semibold text-gray-800 mt-2 leading-tight">{{ $stat['value'] }}</dd>
                <dd class="text-[11px] sm:text-xs text-gray-500 mt-1 sm:mt-2 leading-4 sm:leading-5">{{ $stat['detail'] }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <section class="lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" aria-labelledby="coaching-next-title">
            <div class="px-5 md:px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <h2 id="coaching-next-title" class="font-semibold text-gray-800">Dein nächster Termin</h2>
                @if($next)
                    <span class="rounded-full bg-primary-50 text-primary-700 px-3 py-1 text-xs font-semibold">Bestätigt</span>
                @endif
            </div>
            <div class="p-5 md:p-6">
                @if($next)
                    <div class="flex items-start gap-4 md:gap-6">
                        <div class="shrink-0 w-16 md:w-20 rounded-xl bg-secondary-50 text-center py-3 text-secondary-700" aria-hidden="true">
                            <span class="block text-xs font-semibold uppercase">{{ \Illuminate\Support\Carbon::parse($next['date'])->locale('de')->isoFormat('MMM') }}</span>
                            <span class="block text-3xl font-semibold mt-1">{{ \Illuminate\Support\Carbon::parse($next['date'])->format('d') }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm text-gray-500">{{ $next['weekday'] }}, {{ $next['date_label'] }}</p>
                            <p class="text-2xl font-semibold text-gray-800 mt-1">{{ $next['start'] }} – {{ $next['end'] }} <span class="text-sm font-normal text-gray-500">Uhr</span></p>
                            <h3 class="font-semibold text-gray-800 mt-3 break-words">{{ $next['title'] }}</h3>
                            @if($next['topic'])<p class="text-sm text-gray-500 mt-1 break-words">{{ $next['topic'] }}</p>@endif
                            <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-600 mt-4">
                                <span><i class="fal fa-user mr-1 text-secondary-500" aria-hidden="true"></i> {{ $next['tutor_name'] }}</span>
                                <span class="break-words"><i class="fal fa-map-marker-alt mr-1 text-secondary-500" aria-hidden="true"></i> {{ $next['mode'] === 'online' ? 'Online' : 'Vor Ort' }}{{ $next['location'] && !in_array(mb_strtolower(trim($next['location'])), ['online', 'vor ort', 'präsenz']) ? ' · '.$next['location'] : '' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3 mt-6">
                        <x-buttons.button-basic mode="primary" size="sm" :href="$next['course_url']">Zum Baustein <i class="fal fa-arrow-right" aria-hidden="true"></i></x-buttons.button-basic>
                        <x-buttons.button-basic size="sm" :href="$next['planning_url']">Gesamtplan ansehen</x-buttons.button-basic>
                    </div>
                @else
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-secondary-50 text-secondary-600 mb-4" aria-hidden="true"><i class="fal fa-calendar-alt text-xl"></i></span>
                    <h3 class="text-lg font-semibold text-gray-800">{{ $pending ? 'Plane deinen Coaching-Start' : 'Aktuell kein anstehender Termin' }}</h3>
                    <p class="text-sm leading-6 text-gray-500 mt-2 max-w-xl">{{ $pending ? 'Stimme alle Termine gemeinsam mit deinem Dozenten ab. Nach der Bestätigung des Gesamtplans und der Vertragsfreigabe findest du hier deinen nächsten Termin.' : 'Sobald dein Gesamtplan bestätigt und dein Coaching freigegeben ist, erscheinen hier die nächsten Termine. Deine bisherigen Bausteine bleiben unten erreichbar.' }}</p>
                    @if($pending)<x-buttons.button-basic mode="primary" size="sm" class="mt-5" :href="$pending['planning_url']">Termine abstimmen <i class="fal fa-arrow-right" aria-hidden="true"></i></x-buttons.button-basic>@endif
                @endif
            </div>
        </section>

        <aside class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 md:p-6 flex flex-col" aria-labelledby="coaching-planning-title">
            <div class="flex items-center gap-3 mb-4">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-secondary-50 text-secondary-600" aria-hidden="true"><i class="fal fa-calendar-check text-lg"></i></span>
                <h2 id="coaching-planning-title" class="font-semibold text-gray-800">Deine Terminplanung</h2>
            </div>
            @if($pending)
                <p class="text-lg font-semibold text-gray-800">Noch Termine abstimmen</p>
                <p class="text-sm leading-6 text-gray-500 mt-2">Für {{ $dashboard['planning_count'] === 1 ? 'einen Baustein ist der Gesamtplan' : $dashboard['planning_count'].' Bausteine sind die Gesamtpläne' }} noch offen. Prüfe die Vorschläge oder schicke deinem Dozenten eine Nachricht.</p>
                <p class="text-sm font-medium text-gray-700 mt-4 break-words">{{ $pending['title'] }}</p>
                <div class="mt-auto pt-5"><x-buttons.button-basic size="sm" :href="$pending['planning_url']">Zur Abstimmung <i class="fal fa-arrow-right" aria-hidden="true"></i></x-buttons.button-basic></div>
            @elseif($dashboard['waiting_count'])
                <p class="text-lg font-semibold text-gray-800">Nächster Schritt in Vorbereitung</p>
                <p class="text-sm leading-6 text-gray-500 mt-2">Die weitere Bearbeitung liegt bei deinem Dozenten oder der Verwaltung. Den aktuellen Stand findest du bei deinen Bausteinen.</p>
            @else
                <p class="text-lg font-semibold text-gray-800">Alles im Blick</p>
                <p class="text-sm leading-6 text-gray-500 mt-2">Bei Fragen zu einem Termin erreichst du deinen Dozenten direkt in der Abstimmung des jeweiligen Bausteins.</p>
            @endif
        </aside>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 md:gap-6">
        <section class="lg:col-span-2 min-w-0" aria-labelledby="coaching-modules-title">
            <div class="flex flex-wrap items-end justify-between gap-2 mb-3 px-1">
                <div>
                    <h2 id="coaching-modules-title" class="text-lg font-semibold text-gray-800">Meine Coaching-Bausteine</h2>
                    <p class="text-sm text-gray-500 mt-1">Alle Inhalte und Termine an einem Ort.</p>
                </div>
                <span class="text-xs text-gray-500">{{ $contracts->count() }} {{ $contracts->count() === 1 ? 'Baustein' : 'Bausteine' }}</span>
            </div>
            <div class="space-y-3">
                @forelse($contracts as $contract)
                    <article @class(['relative bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden', 'border-l-4 border-l-primary-500' => $contract['status'] === 'ready', 'border-l-4 border-l-secondary-500' => $contract['status'] === 'planning', 'border-l-4 border-l-amber-400' => in_array($contract['status'], ['waiting', 'review'])]) wire:key="coaching-dashboard-{{ $contract['id'] }}">
                        <div class="p-5 md:px-6 md:py-5">
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                                <div class="min-w-0">
                                    <h3 class="text-base md:text-lg font-semibold text-gray-800 break-words">{{ $contract['title'] }}</h3>
                                    <p class="text-sm text-gray-500 mt-1">{{ $contract['planning_label'] }}</p>
                                </div>
                                <span @class(['rounded-full px-3 py-1 text-xs font-semibold shrink-0', 'bg-primary-50 text-primary-700' => $contract['status'] === 'ready', 'bg-amber-50 text-amber-700' => in_array($contract['status'], ['waiting','review']), 'bg-secondary-50 text-secondary-700' => $contract['status'] === 'planning', 'bg-gray-100 text-gray-600' => $contract['status'] === 'ended'])>{{ match($contract['status']) { 'ready' => 'Freigegeben', 'waiting' => 'In Vorbereitung', 'review' => 'In Prüfung', 'ended' => 'Beendet', default => 'In Abstimmung' } }}</span>
                            </div>
                            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-3 mt-5 pt-4 border-t border-gray-100 text-sm">
                                <div class="min-w-0 col-span-2 sm:col-span-1"><dt class="text-xs text-gray-500">Dozent</dt><dd class="font-medium text-gray-700 mt-1 break-words">{{ $contract['tutor_name'] }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Umfang</dt><dd class="font-medium text-gray-700 mt-1">{{ $units($contract['units']) }} UE <span class="font-normal text-gray-500">à {{ $contract['unit_minutes'] }} Min.</span></dd></div>
                                <div><dt class="text-xs text-gray-500">Terminplan</dt><dd class="font-medium text-gray-700 mt-1">{{ $contract['confirmed_count'] ? $contract['confirmed_count'].' bestätigt' : 'Noch offen' }}</dd></div>
                            </dl>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 md:px-6 py-3 bg-gray-50 border-t border-gray-100">
                            <x-buttons.button-basic :mode="$contract['status'] === 'planning' ? 'primary' : 'basic'" size="sm" :href="$contract['action_url']">{{ $contract['action_label'] }} <i class="fal fa-arrow-right" aria-hidden="true"></i></x-buttons.button-basic>
                            @if($contract['course_url'])<a href="{{ $contract['planning_url'] }}" class="text-sm font-medium text-secondary-700 hover:underline focus-visible:outline-secondary-500">Terminplan ansehen</a>@endif
                        </div>
                    </article>
                @empty
                    <p class="bg-white border border-gray-200 rounded-xl p-6 text-sm text-gray-500">Deine Coaching-Daten sind aktuell nicht verfügbar.</p>
                @endforelse
            </div>
        </section>

        <section class="bg-white border border-gray-200 rounded-xl shadow-sm self-start overflow-hidden" aria-labelledby="coaching-upcoming-title">
            <div class="px-5 md:px-6 py-4 border-b border-gray-100">
                <h2 id="coaching-upcoming-title" class="font-semibold text-gray-800">Demnächst</h2>
                <p class="text-xs text-gray-500 mt-1">Deine nächsten bestätigten Termine</p>
            </div>
            <ol class="divide-y divide-gray-100 px-5 md:px-6">
                @forelse($dashboard['upcoming_sessions'] as $session)
                    <li class="flex gap-3 py-4 min-w-0">
                        <span class="shrink-0 w-12 h-12 rounded-lg bg-secondary-50 text-secondary-700 flex flex-col items-center justify-center leading-tight" aria-hidden="true"><span class="text-xs uppercase">{{ \Illuminate\Support\Carbon::parse($session['date'])->locale('de')->isoFormat('MMM') }}</span><span class="text-lg font-semibold">{{ \Illuminate\Support\Carbon::parse($session['date'])->format('d') }}</span></span>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500">{{ $session['weekday'] }} · {{ $session['start'] }}–{{ $session['end'] }} Uhr</p>
                            <a href="{{ $session['course_url'] }}" class="block text-sm font-semibold text-gray-800 mt-1 hover:text-secondary-700 hover:underline focus-visible:outline-secondary-500 break-words">{{ $session['topic'] ?: $session['title'] }}</a>
                            <p class="text-xs text-gray-500 mt-1">{{ $session['mode'] === 'online' ? 'Online' : 'Vor Ort' }} · {{ $session['tutor_name'] }}</p>
                        </div>
                    </li>
                @empty
                    <li class="py-5 text-sm leading-6 text-gray-500">Hier erscheinen die nächsten Termine deiner freigegebenen Bausteine.</li>
                @endforelse
            </ol>
        </section>
    </div>
</section>
