<div class="max-w-7xl mx-auto p-4 space-y-6">
    <h1 class="text-2xl font-bold text-gray-700">Einzelcoaching</h1>
    <p class="text-sm text-gray-600">Vereinbaren Sie den vollständigen Terminplan gemeinsam vor Beginn. Nach der Freigabe finden Durchführung, Dokumentation und Berichtsheft im gewohnten Baustein statt.</p>
    @if(session('coaching_status'))<div role="status" class="p-4 bg-green-50 text-green-800 rounded-lg">{{ session('coaching_status') }}</div>@endif
    @if($errors->any())<div role="alert" class="p-4 bg-red-50 text-red-800 rounded-lg"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm text-left"><thead class="bg-gray-50 text-gray-600"><tr><th class="p-3">Baustein / Vertrag</th><th class="p-3">Umfang</th><th class="p-3">Stand</th><th class="p-3">Aktion</th></tr></thead>
        <tbody>@forelse($contracts as $entry)<tr class="border-t" wire:key="contract-{{ $entry->id }}"><td class="p-3">{{ $entry->title }}<span class="block text-xs text-gray-500">UVS {{ $entry->uvs_contract_id }}</span></td><td class="p-3">{{ $entry->agreed_minutes / $entry->unit_minutes }} UE à {{ $entry->unit_minutes }} Min.</td><td class="p-3">{{ $entry->planning_label }}</td><td class="p-3"><x-buttons.button-basic size="sm" wire:click="select({{ $entry->id }})">Öffnen</x-buttons.button-basic></td></tr>@empty<tr><td class="p-4" colspan="4">Aktuell ist Ihnen kein Einzelcoaching über das neue System zugeordnet.</td></tr>@endforelse</tbody></table>
    </div>
    @if($contract)
    <section class="bg-white border border-gray-200 rounded-lg p-4 space-y-4" aria-label="Gesamtplan">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-xl font-semibold text-gray-700">{{ $contract->title }} · Gesamtplan</h2><p class="text-sm text-gray-500">Dozent: {{ $contract->tutor ? trim($contract->tutor->vorname.' '.$contract->tutor->nachname) : 'Noch nicht zugeordnet' }} · Version {{ $revision }}</p></div><x-buttons.button-basic size="sm" mode="secondary" wire:click="reload">Neu laden</x-buttons.button-basic></div>
        <p class="text-sm">Vereinbart: <strong>{{ $contract->agreed_minutes }} Minuten</strong>. Dozent: {{ $plan?->tutor_confirmed_at ? 'bestätigt' : 'offen' }} · Teilnehmer: {{ $plan?->participant_confirmed_at ? 'bestätigt' : 'offen' }}.</p>
        @if($contract->course_id)
            <div class="flex flex-wrap gap-3"><x-buttons.button-basic href="{{ $actor === 'tutor' ? route('tutor.courses.show', ['courseId' => $contract->course_id]) : route('user.program.course.show', ['klassenId' => $contract->course->klassen_id]) }}">Baustein öffnen</x-buttons.button-basic>
            @if($actor === 'participant')<x-buttons.button-basic mode="secondary" href="{{ route('reportbook', ['course' => $contract->course_id]) }}">Berichtsheft</x-buttons.button-basic>@endif
            <x-buttons.button-basic mode="secondary" href="{{ route('coaching.calendar', $contract->id) }}">Kalender herunterladen</x-buttons.button-basic></div>
        @elseif($contract->confirmed_plan_id)<p class="p-3 bg-blue-50 text-blue-800 rounded-lg" role="status">Alle Termine wurden beidseitig bestätigt. Die UVS-Rückmeldung und Bausteinfreigabe stehen noch aus.</p>@endif
        @if(!$contract->planningAllowed())<p class="text-red-700">Dieser Vertrag ist nicht zur Terminabstimmung freigegeben.</p>@endif

            <div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead class="bg-gray-50"><tr><th class="p-2">Datum</th><th class="p-2">Uhrzeit</th><th class="p-2">Min.</th><th class="p-2">Inhalt</th><th class="p-2">Ort</th></tr></thead><tbody>@forelse($plan?->items ?? [] as $item)<tr class="border-t"><td class="p-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($item['date'])->format('d.m.Y') }}</td><td class="p-2 whitespace-nowrap">{{ $item['start'] }} – {{ $item['end'] }}</td><td class="p-2">{{ $item['minutes'] }}</td><td class="p-2">{{ $item['topic'] }}</td><td class="p-2 break-all">{{ $item['location'] }}</td></tr>@empty<tr><td colspan="5" class="p-3">Noch kein Gesamtplan vorgeschlagen.</td></tr>@endforelse</tbody></table></div>
            @if(!$contract->confirmed_plan_id && $contract->tutor_person_id && $contract->planningAllowed())
            <div class="flex flex-wrap gap-3"><x-buttons.button-basic mode="secondary" wire:click="edit">{{ $plan ? 'Gesamtplan ändern' : 'Gesamtplan erstellen' }}</x-buttons.button-basic>
            @if($plan && $plan->status === 'proposed' && $plan->revision === $revision && !$plan->{$actor.'_confirmed_at'})<x-buttons.button-basic wire:click="confirm" wire:confirm="Ich bestätige alle Termine dieses Gesamtplans verbindlich." wire:loading.attr="disabled">Alle Termine bestätigen</x-buttons.button-basic>@endif</div>
            @endif
    </section>
    <section class="bg-white border border-gray-200 rounded-lg p-4 space-y-4" aria-label="Terminabstimmung">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold text-gray-700">Nachrichten zur Terminabstimmung</h2>@if($contract->planningAllowed())<x-buttons.button-basic type="button" mode="secondary" wire:click="composeMessage">Nachricht schreiben</x-buttons.button-basic>@endif</div>
        @foreach($messages as $entry)<div wire:key="message-{{ $entry->id }}" class="border-b pb-3"><p class="text-xs text-gray-500">{{ $entry->author?->name }} · {{ $entry->created_at->format('d.m.Y H:i') }} · Plan {{ $entry->plan_revision }}</p><p class="whitespace-pre-wrap text-sm">{{ $entry->body }}</p></div>@endforeach
        @if($messages->isEmpty())<p class="text-sm text-gray-500">Noch keine Nachrichten zur Terminabstimmung.</p>@endif
    </section>
    @if($editing) @include('livewire.coaching.plan-editor') @endif
    @if($composing ?? false)
    <x-modal id="coaching-message-modal" wire:model.live="composing" maxWidth="2xl">
        <form wire:submit="sendMessage" role="dialog" aria-modal="true" aria-labelledby="coaching-message-title">
            <div class="px-4 md:px-6 py-4 border-b flex items-center justify-between gap-3"><h2 id="coaching-message-title" class="text-lg font-semibold text-gray-900">Nachricht zur Terminabstimmung</h2><button type="button" wire:click="cancelMessage" aria-label="Nachricht schließen" class="text-gray-500 hover:text-gray-900 p-2 rounded-lg focus:ring-2 focus:ring-blue-200"><i class="fas fa-times" aria-hidden="true"></i></button></div>
            <div class="px-4 md:px-6 py-4 space-y-3">
                <p class="text-sm text-gray-600">{{ $contract->title }} · Plan {{ $revision }}</p>
                @if($errors->any())<div role="alert" class="p-3 bg-red-50 text-red-800 rounded-lg"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <label class="block text-sm" for="coaching-message">Nachricht<textarea id="coaching-message" wire:model="message" maxlength="4000" required rows="5" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"></textarea></label>
            </div>
            <div class="flex flex-wrap justify-end gap-3 px-4 md:px-6 py-3 bg-gray-100 rounded-b-lg border-t"><x-buttons.button-basic type="button" wire:click="cancelMessage">Abbrechen</x-buttons.button-basic><x-buttons.button-basic type="submit" mode="secondary" wire:loading.attr="disabled">Nachricht senden</x-buttons.button-basic></div>
        </form>
    </x-modal>
    @endif
    @endif
</div>
