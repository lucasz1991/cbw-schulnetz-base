<x-modal id="coaching-plan-modal" wire:model.live="editing" maxWidth="4xl">
    <form id="coaching-editor-form" wire:submit="submitEditor" role="dialog" aria-modal="true" aria-labelledby="coaching-editor-title" class="flex flex-col" style="max-height:calc(100dvh - 5rem)" data-coaching-editor data-agreed-minutes="{{ $contract->agreed_minutes }}" data-unit-minutes="{{ $contract->unit_minutes }}" data-valid-from="{{ $contract->valid_from?->toDateString() }}" data-valid-until="{{ $contract->valid_until?->toDateString() }}">
        <div class="px-5 md:px-6 py-4 border-b border-gray-100 shrink-0 bg-white">
            <div class="flex items-start justify-between gap-3">
            <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wider text-secondary-600">Gesamtplan · {{ $editorStep }} von 2</p><h2 id="coaching-editor-title" class="mt-1 text-lg font-semibold text-gray-800">Terminplan erstellen</h2>
                <p class="mt-1 text-sm text-gray-500 truncate" title="{{ $contract->title }}">{{ $contract->title }}</p></div>
            <button type="button" wire:click="cancelEditing" aria-label="Terminplanung schließen" class="text-gray-500 hover:text-gray-900 p-2 rounded-lg focus:ring-2 focus:ring-blue-200"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <ol aria-label="Schritte der Terminplanung" class="grid grid-cols-2 gap-2 mt-4 text-xs">
                @foreach([1 => 'Verteilung festlegen', 2 => 'Termine prüfen'] as $step => $label)
                <li data-step-indicator="{{ $step }}" aria-current="{{ $editorStep === $step ? 'step' : 'false' }}" @class(['flex items-center gap-2 rounded-lg border px-3 py-2.5', 'bg-secondary-50 border-secondary-300 text-secondary-800 font-semibold' => $editorStep === $step, 'bg-gray-50 border-gray-200 text-gray-500' => $editorStep !== $step])><span @class(['inline-flex items-center justify-center w-6 h-6 rounded-full border shrink-0', 'bg-secondary-600 border-secondary-600 text-white' => $editorStep === $step]) aria-hidden="true">{{ $step }}</span><span>{{ $label }}</span></li>
                @endforeach
            </ol>
        </div>
        <div class="px-5 md:px-6 py-5 space-y-4 overflow-y-auto max-h-[65vh] min-h-0 bg-gray-50/60" data-editor-scroll>
            @if($errors->any())<div role="alert" class="p-3 bg-red-50 text-red-800 rounded-lg"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <div data-generation-status role="status" hidden></div>
            <fieldset data-editor-step="1" @disabled($editorStep !== 1) @class(['border border-gray-200 bg-white rounded-xl p-4 md:p-5 space-y-5 shadow-sm', 'hidden' => $editorStep !== 1])>
                <legend class="px-2 font-semibold text-gray-800">Automatisch verteilen</legend>
                <div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm text-gray-600">Start und Wochentage wählen. Der gesamte Umfang wird verteilt; der letzte Termin wird bei Bedarf kürzer.</p><span class="text-xs font-semibold text-secondary-700 bg-secondary-50 rounded-lg px-3 py-1.5">{{ $contract->agreed_minutes / $contract->unit_minutes }} UE à {{ $contract->unit_minutes }} Min.</span></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-ui.forms.date-input class="sm:col-span-2" id="coaching-start" model="schedule.start_date" timeModel="schedule.start_time" label="Start & Uhrzeit" :enableTime="true" :min="$contract->valid_from?->toDateString()" :max="$contract->valid_until?->toDateString()" />
                    <label class="text-sm">UE je Termin<input type="number" min="1" max="{{ max(1, intdiv(720, $contract->unit_minutes)) }}" step="1" wire:model="schedule.units_per_appointment" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"></label>
                    <label class="text-sm">Wochenrhythmus<select wire:model="schedule.every_weeks" class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"><option value="1">Jede Woche</option><option value="2">Alle 2 Wochen</option><option value="3">Alle 3 Wochen</option><option value="4">Alle 4 Wochen</option></select></label>
                </div>
                <fieldset><legend class="text-sm mb-2">Wochentage</legend><div class="flex flex-wrap gap-2">
                    @foreach([1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'] as $day => $label)
                    <label class="inline-flex min-h-10 items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 text-sm cursor-pointer hover:border-secondary-300 hover:bg-secondary-50"><input type="checkbox" wire:model="schedule.weekdays" value="{{ $day }}" class="rounded border-gray-300 text-secondary-600 focus:ring-secondary-200">{{ $label }}</label>
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
            <fieldset data-editor-step="2" @disabled($editorStep !== 2) @class(['space-y-4 min-w-0 bg-white rounded-xl border border-gray-200 p-4 md:p-5 shadow-sm', 'hidden' => $editorStep !== 2])>
            @if($generationStatus)<p role="status" class="text-xs text-green-700">{{ $generationStatus }}</p>@endif
            @include('livewire.coaching.plan-review')
            <x-buttons.button-basic type="button" mode="link" size="sm" wire:click="add">+ Termin hinzufügen</x-buttons.button-basic>
            </fieldset>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3 px-4 md:px-6 py-3 sm:py-4 bg-white rounded-b-lg border-t border-gray-100 shrink-0">
            <x-buttons.button-basic type="button" mode="link" size="sm" wire:click="cancelEditing">Abbrechen</x-buttons.button-basic>
            <div data-editor-step="1" @class(['flex flex-wrap justify-end gap-2 sm:gap-3', 'hidden' => $editorStep !== 1])>
                <x-buttons.button-basic type="submit" mode="secondary" size="sm" wire:loading.attr="disabled" data-editor-next><span class="sm:hidden">Vorschläge erstellen</span><span class="hidden sm:inline">Vorschläge erstellen &amp; weiter</span></x-buttons.button-basic>
            </div>
            <div data-editor-step="2" @class(['flex flex-wrap justify-end gap-2 sm:gap-3', 'hidden' => $editorStep !== 2])>
                <x-buttons.button-basic type="button" size="sm" wire:click="scheduleSettings">Zurück</x-buttons.button-basic>
                <x-buttons.button-basic type="submit" mode="secondary" size="sm" wire:loading.attr="disabled"><span class="sm:hidden">Plan vorschlagen</span><span class="hidden sm:inline">Gesamten Plan vorschlagen</span></x-buttons.button-basic>
            </div>
        </div>
        {{-- Keep teleported panels out of the form's morph children, but inside its focus trap. --}}
        <div id="coaching-slot-editor-portal" wire:ignore wire:key="coaching-slot-editor-portal"></div>
        <div data-date-picker-portal wire:ignore wire:key="coaching-date-picker-portal"></div>
    </form>
</x-modal>
