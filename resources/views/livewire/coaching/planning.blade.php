<div class="max-w-7xl mx-auto px-4 py-5 md:py-7 space-y-5 md:space-y-6">
    <header class="px-1">
        <p class="text-xs font-semibold uppercase tracking-wider text-secondary-600">Gemeinsame Planung</p>
        <h1 class="mt-1 text-2xl font-semibold text-gray-800">Einzelcoaching</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">Vereinbaren Sie den vollständigen Terminplan gemeinsam vor Beginn. Nach der Freigabe finden Durchführung und Dokumentation im gewohnten Baustein statt.</p>
    </header>
    @if(session('coaching_status'))<div role="status" class="rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm text-primary-800"><i class="fal fa-check-circle mr-2" aria-hidden="true"></i>{{ session('coaching_status') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section aria-labelledby="coaching-contracts-title" class="space-y-3">
        <div class="flex items-center justify-between gap-3 px-1"><h2 id="coaching-contracts-title" class="text-lg font-semibold text-gray-800">Meine Coachings</h2><span class="text-xs text-gray-500">{{ $contracts->count() }} {{ $contracts->count() === 1 ? 'Baustein' : 'Bausteine' }}</span></div>
        <div class="grid gap-3 md:grid-cols-2">
            @forelse($contracts as $entry)
                <article wire:key="contract-{{ $entry->id }}" @class(['rounded-xl border bg-white p-4 md:p-5 shadow-sm', 'border-secondary-300 ring-1 ring-secondary-100' => $contract?->id === $entry->id, 'border-gray-200' => $contract?->id !== $entry->id])>
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs text-gray-500">UVS {{ $entry->uvs_contract_id }}</p><h3 class="mt-1 font-semibold text-gray-800 break-words">{{ $entry->title }}</h3></div><span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-secondary-50 text-secondary-700"><i class="fal fa-layer-group" aria-hidden="true"></i></span></div>
                    <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-600"><span><i class="fal fa-clock mr-1 text-secondary-500" aria-hidden="true"></i>{{ $entry->agreed_minutes / $entry->unit_minutes }} UE à {{ $entry->unit_minutes }} Min.</span><span class="text-secondary-700">{{ $entry->planning_label }}</span></div>
                    <button type="button" wire:click="select({{ $entry->id }})" @class(['mt-4 inline-flex min-h-10 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-secondary-300', 'border-secondary-600 bg-secondary-600 text-white hover:bg-secondary-700' => $contract?->id !== $entry->id, 'border-gray-200 bg-gray-50 text-gray-700' => $contract?->id === $entry->id])>{{ $contract?->id === $entry->id ? 'Ausgewählt' : 'Plan öffnen' }} <i class="fal fa-arrow-right" aria-hidden="true"></i></button>
                </article>
            @empty
                <p class="md:col-span-2 rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500">Aktuell ist Ihnen kein Einzelcoaching über das neue System zugeordnet.</p>
            @endforelse
        </div>
    </section>
    @if($contract)
    <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" aria-label="Gesamtplan">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 md:px-6 py-5"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wider text-secondary-600">Gesamtplan · Version {{ $revision }}</p><h2 class="mt-1 text-xl font-semibold text-gray-800 break-words">{{ $contract->title }}</h2><p class="mt-1 text-sm text-gray-500">Dozent: {{ $contract->tutor ? trim($contract->tutor->vorname.' '.$contract->tutor->nachname) : 'Noch nicht zugeordnet' }}</p></div><x-buttons.button-basic size="sm" wire:click="reload"><i class="fal fa-sync-alt" aria-hidden="true"></i>Neu laden</x-buttons.button-basic></div>
        <div class="p-5 md:p-6 space-y-5">
        <div class="grid grid-cols-2 gap-2 sm:gap-3 sm:grid-cols-3" aria-label="Stand des Gesamtplans">
            <div class="col-span-2 sm:col-span-1 rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Vereinbarter Umfang</p><p class="mt-1 font-semibold text-gray-800">{{ $contract->agreed_minutes }} Minuten</p></div>
            <div class="rounded-lg {{ $plan?->tutor_confirmed_at ? 'bg-primary-50' : 'bg-amber-50' }} p-3"><p class="text-xs text-gray-500">Dozent</p><p class="mt-1 font-semibold {{ $plan?->tutor_confirmed_at ? 'text-primary-700' : 'text-amber-700' }}">{{ !$tutorStarted ? 'Erstellt den Gesamtplan' : ($plan?->tutor_confirmed_at ? 'Bestätigt' : 'Bestätigung offen') }}</p></div>
            <div class="rounded-lg {{ $plan?->participant_confirmed_at ? 'bg-primary-50' : 'bg-amber-50' }} p-3"><p class="text-xs text-gray-500">Teilnehmer</p><p class="mt-1 font-semibold {{ $plan?->participant_confirmed_at ? 'text-primary-700' : 'text-amber-700' }}">{{ !$tutorStarted ? 'Wartet auf Vorschlag' : ($plan?->participant_confirmed_at ? 'Bestätigt' : 'Bestätigung offen') }}</p></div>
        </div>
        @if($contract->course_id)
            <div class="flex flex-wrap gap-3"><x-buttons.button-basic href="{{ $actor === 'tutor' ? route('tutor.courses.show', ['courseId' => $contract->course_id]) : route('user.program.course.show', ['klassenId' => $contract->course->klassen_id]) }}">Baustein öffnen</x-buttons.button-basic>
            <x-buttons.button-basic mode="secondary" href="{{ route('coaching.calendar', $contract->id) }}">Kalender herunterladen</x-buttons.button-basic></div>
        @elseif($contract->confirmed_plan_id)<p class="p-3 bg-blue-50 text-blue-800 rounded-lg" role="status">Alle Termine wurden beidseitig bestätigt. Die UVS-Rückmeldung und Bausteinfreigabe stehen noch aus.</p>@endif
        @if(!$contract->planningAllowed())<p class="text-red-700">Dieser Vertrag ist nicht zur Terminabstimmung freigegeben.</p>@endif
        @if($waitingForTutor && $contract->planningAllowed())<p class="p-3 bg-blue-50 text-blue-800 rounded-lg" role="status">Ihr Dozent erstellt zuerst den Gesamtplan. Sobald ein Vorschlag vorliegt, werden Sie benachrichtigt und können alle Termine prüfen, bestätigen oder Änderungen vorschlagen.</p>@endif

            <div class="space-y-2"><div class="flex items-center justify-between gap-2"><h3 class="font-semibold text-gray-800">Alle Termine</h3><span class="text-xs text-gray-500">{{ count($plan?->items ?? []) }} vorgeschlagen</span></div>
                @forelse($plan?->items ?? [] as $item)
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-lg border border-gray-200 p-3 md:p-4">
                        <div class="shrink-0 w-12 rounded-lg bg-secondary-50 px-2 py-2 text-center text-secondary-700"><span class="block text-lg font-semibold leading-tight">{{ \Carbon\Carbon::parse($item['date'])->format('d') }}</span><span class="block text-xs uppercase">{{ \Carbon\Carbon::parse($item['date'])->locale('de')->isoFormat('MMM') }}</span></div>
                        <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-gray-800">{{ $item['start'] }} – {{ $item['end'] }} Uhr</p><p class="text-xs text-gray-500">{{ $item['minutes'] }} Min. · {{ $item['location'] ?: 'Ort noch offen' }}</p><p class="mt-1 text-sm text-gray-600 break-words">{{ $item['topic'] ?: 'Inhalt noch offen' }}</p></div>
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-500">{{ $actor === 'tutor' ? 'Erstellen Sie zuerst einen Gesamtplan mit allen Terminen. Anschließend kann Ihr Teilnehmer darauf reagieren.' : 'Die Termine erscheinen hier, sobald Ihr Dozent den Gesamtplan vorgeschlagen hat.' }}</p>
                @endforelse
            </div>
            @if(!$waitingForTutor && !$contract->confirmed_plan_id && $contract->tutor_person_id && $contract->planningAllowed())
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 border-t border-gray-100 pt-4"><x-buttons.button-basic class="w-full sm:w-auto min-h-10" mode="secondary" wire:click="edit">{{ $plan ? 'Gesamtplan ändern' : 'Gesamtplan erstellen' }}</x-buttons.button-basic>
            @if($plan && $plan->status === 'proposed' && $plan->revision === $revision && !$plan->{$actor.'_confirmed_at'})<x-buttons.button-basic class="w-full sm:w-auto min-h-10" mode="primary" wire:click="confirm" wire:confirm="Ich bestätige alle Termine dieses Gesamtplans verbindlich." wire:loading.attr="disabled">Alle Termine bestätigen</x-buttons.button-basic>@endif</div>
            @endif
        </div>
    </section>
    <section class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden" aria-label="Terminabstimmung">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 md:px-6 py-4"><div><h2 class="text-lg font-semibold text-gray-800">Nachrichten zur Terminabstimmung</h2><p class="mt-1 text-xs text-gray-500">Fragen und Änderungen zum gemeinsamen Gesamtplan</p></div>@if($contract->planningAllowed())<x-buttons.button-basic type="button" mode="secondary" wire:click="composeMessage">Nachricht schreiben</x-buttons.button-basic>@endif</div>
        <div class="p-5 md:p-6 space-y-3">
            @foreach($messages as $entry)<article wire:key="message-{{ $entry->id }}" class="max-w-3xl rounded-xl border border-gray-200 bg-gray-50 p-4"><p class="text-xs font-medium text-secondary-700">{{ $entry->author?->name }} <span class="font-normal text-gray-500">· {{ $entry->created_at->format('d.m.Y H:i') }} · Plan {{ $entry->plan_revision }}</span></p><p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-gray-700">{{ $entry->body }}</p></article>@endforeach
            @if($messages->isEmpty())<p class="rounded-lg border border-dashed border-gray-300 p-5 text-sm text-gray-500">Noch keine Nachrichten zur Terminabstimmung.</p>@endif
        </div>
    </section>
    @if($editing) @include('livewire.coaching.plan-editor') @endif
    @if($composing ?? false)
    <x-modal id="coaching-message-modal" wire:model.live="composing" maxWidth="2xl">
        <form wire:submit="sendMessage" role="dialog" aria-modal="true" aria-labelledby="coaching-message-title">
            <div class="px-5 md:px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wider text-secondary-600">Direkte Abstimmung</p><h2 id="coaching-message-title" class="mt-1 text-lg font-semibold text-gray-800">Nachricht zur Terminabstimmung</h2><p class="mt-1 text-sm text-gray-500">{{ $contract->title }} · Plan {{ $revision }}</p></div><button type="button" wire:click="cancelMessage" aria-label="Nachricht schließen" class="text-gray-500 hover:text-gray-900 p-2 rounded-lg focus:ring-2 focus:ring-blue-200"><i class="fas fa-times" aria-hidden="true"></i></button></div>
            <div class="px-5 md:px-6 py-5 space-y-3">
                @if($errors->any())<div role="alert" class="p-3 bg-red-50 text-red-800 rounded-lg"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <label class="block text-sm font-semibold text-gray-700" for="coaching-message">Ihre Nachricht<textarea id="coaching-message" wire:model="message" maxlength="4000" required rows="5" placeholder="Frage zum Terminplan oder alternativen Vorschlag schreiben …" class="mt-2 block w-full rounded-lg border border-gray-300 px-4 py-3 text-sm font-normal text-gray-800 placeholder:text-gray-400 focus:border-secondary-400 focus:ring-2 focus:ring-secondary-200"></textarea></label>
                <p class="text-xs leading-5 text-gray-500">Die Nachricht wird in dieser Terminabstimmung für beide Seiten sichtbar.</p>
            </div>
            <div class="flex flex-wrap justify-end gap-3 px-5 md:px-6 py-4 bg-gray-50 rounded-b-lg border-t border-gray-100"><x-buttons.button-basic type="button" wire:click="cancelMessage">Abbrechen</x-buttons.button-basic><x-buttons.button-basic type="submit" mode="secondary" wire:loading.attr="disabled">Nachricht senden</x-buttons.button-basic></div>
        </form>
    </x-modal>
    @endif
    @endif
</div>
