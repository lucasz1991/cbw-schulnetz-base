<x-modal id="coaching-plan-modal" wire:model.live="editing" maxWidth="4xl">
    <form id="coaching-editor-form" wire:submit="submitEditor" role="dialog" aria-modal="true" aria-labelledby="coaching-editor-title" class="flex flex-col" style="max-height:calc(100dvh - 3rem)" data-coaching-editor data-agreed-minutes="{{ $contract->agreed_minutes }}" data-unit-minutes="{{ $contract->unit_minutes }}" data-valid-from="{{ $contract->valid_from?->toDateString() }}" data-valid-until="{{ $contract->valid_until?->toDateString() }}">
        <div class="px-4 md:px-6 py-3 border-b shrink-0">
            <div class="flex items-start justify-between gap-3">
            <div class="min-w-0"><h2 id="coaching-editor-title" class="text-lg font-semibold text-gray-900">Terminplan erstellen</h2>
                <p class="mt-1 text-xs text-gray-500 truncate" title="{{ $contract->title }}">{{ $contract->title }}</p><p class="text-xs text-gray-600 mt-1">{{ $contract->agreed_minutes / $contract->unit_minutes }} UE à {{ $contract->unit_minutes }} Min. · {{ $contract->agreed_minutes }} Minuten</p></div>
            <button type="button" wire:click="cancelEditing" aria-label="Terminplanung schließen" class="text-gray-500 hover:text-gray-900 p-2 rounded-lg focus:ring-2 focus:ring-blue-200"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <ol aria-label="Schritte der Terminplanung" class="grid grid-cols-2 gap-2 mt-3 text-xs">
                @foreach([1 => 'Verteilung festlegen', 2 => 'Termine prüfen'] as $step => $label)
                <li data-step-indicator="{{ $step }}" aria-current="{{ $editorStep === $step ? 'step' : 'false' }}" @class(['flex items-center gap-2 rounded-md border px-3 py-2', 'bg-blue-50 border-blue-300 text-blue-800 font-semibold' => $editorStep === $step, 'bg-gray-50 border-gray-200 text-gray-500' => $editorStep !== $step])><span class="inline-flex items-center justify-center w-6 h-6 rounded-full border shrink-0" aria-hidden="true">{{ $step }}</span><span>{{ $label }}</span></li>
                @endforeach
            </ol>
        </div>
        <div class="px-4 md:px-6 py-3 space-y-3 overflow-y-auto max-h-[65vh] min-h-0" data-editor-scroll>
            @if($errors->any())<div role="alert" class="p-3 bg-red-50 text-red-800 rounded-lg"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div data-generation-status role="status" hidden></div>
            <fieldset data-editor-step="1" @disabled($editorStep !== 1) @class(['border border-gray-200 rounded-lg p-4 space-y-4', 'hidden' => $editorStep !== 1])>
                <legend class="px-2 font-semibold text-gray-800">Automatisch verteilen</legend>
                <p class="text-sm text-gray-600">Start und Wochentage wählen. Der gesamte Umfang wird verteilt; der letzte Termin wird bei Bedarf kürzer.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-ui.forms.date-input class="sm:col-span-2" id="coaching-start" model="schedule.start_date" timeModel="schedule.start_time" label="Start & Uhrzeit" :enableTime="true" :min="$contract->valid_from?->toDateString()" :max="$contract->valid_until?->toDateString()" />
                    <label class="text-sm">UE je Termin<input type="number" min="1" max="{{ max(1, intdiv(720, $contract->unit_minutes)) }}" step="1" wire:model="schedule.units_per_appointment" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"></label>
                    <label class="text-sm">Wochenrhythmus<select wire:model="schedule.every_weeks" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"><option value="1">Jede Woche</option><option value="2">Alle 2 Wochen</option><option value="3">Alle 3 Wochen</option><option value="4">Alle 4 Wochen</option></select></label>
                </div>
                <fieldset><legend class="text-sm mb-2">Wochentage</legend><div class="flex flex-wrap gap-2">
                    @foreach([1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'] as $day => $label)
                    <label class="inline-flex items-center gap-2 border border-gray-300 rounded-md px-3 py-2 text-sm cursor-pointer"><input type="checkbox" wire:model="schedule.weekdays" value="{{ $day }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-200">{{ $label }}</label>
                    @endforeach
                </div><p class="mt-2 text-xs text-gray-500">Ab dem Startdatum, im gewählten Rhythmus ab dessen Kalenderwoche.</p></fieldset>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="text-sm">Thema für die Vorschläge<input wire:model="schedule.topic" maxlength="255" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"></label>
                    <label class="text-sm">Durchführung<select wire:model="schedule.format" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"><option value="online">Online</option><option value="presence">Präsenz</option></select></label>
                    <label class="text-sm">Ort / Besprechungslink<input wire:model="schedule.location" maxlength="255" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"></label>
                </div>
                <p class="text-xs text-gray-500">„Vorschläge erstellen & weiter“ ersetzt die Termine im Entwurf. Im zweiten Schritt prüfen und bearbeiten Sie die Vorschläge, bevor Sie den gesamten Plan veröffentlichen.</p>
                <x-buttons.button-basic type="button" mode="link" size="sm" wire:click="reviewSchedule">Termine ohne Neuverteilung bearbeiten</x-buttons.button-basic>
            </fieldset>
            <fieldset data-editor-step="2" @disabled($editorStep !== 2) @class(['space-y-3 min-w-0', 'hidden' => $editorStep !== 2])>
            @if($generationStatus)<p role="status" class="text-xs text-green-700">{{ $generationStatus }}</p>@endif
            @include('livewire.coaching.plan-review')
            <x-buttons.button-basic type="button" mode="link" size="sm" wire:click="add">+ Termin hinzufügen</x-buttons.button-basic>
            </fieldset>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3 px-4 md:px-6 py-3 bg-gray-100 rounded-b-lg border-t shrink-0">
            <x-buttons.button-basic type="button" wire:click="cancelEditing">Abbrechen</x-buttons.button-basic>
            <div data-editor-step="1" @class(['flex flex-wrap justify-end gap-3', 'hidden' => $editorStep !== 1])>
                <x-buttons.button-basic type="submit" mode="secondary" wire:loading.attr="disabled" data-editor-next>Vorschläge erstellen &amp; weiter</x-buttons.button-basic>
            </div>
            <div data-editor-step="2" @class(['flex flex-wrap justify-end gap-3', 'hidden' => $editorStep !== 2])>
                <x-buttons.button-basic type="button" wire:click="scheduleSettings">Zurück</x-buttons.button-basic>
                <x-buttons.button-basic type="submit" mode="secondary" wire:loading.attr="disabled">Gesamten Plan vorschlagen</x-buttons.button-basic>
            </div>
        </div>
        <div data-date-picker-portal wire:ignore wire:key="coaching-date-picker-portal"></div>
    </form>
</x-modal>
