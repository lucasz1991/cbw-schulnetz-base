<x-dialog-modal wire:model="showModal" maxWidth="3xl">
    <x-slot name="title">
        Berichtsheft mit KI optimieren
    </x-slot>

    <x-slot name="content">
        <div class="space-y-4" wire:loading.class="opacity-60 pointer-events-none">

            @if (session()->has('reportbook_ai_saved'))
                <div class="px-3 py-2 text-xs rounded bg-green-50 text-green-700 border border-green-200">
                    {{ session('reportbook_ai_saved') }}
                </div>
            @endif

            @if (session()->has('reportbook_ai_info'))
                <div class="px-3 py-2 text-xs rounded bg-blue-50 text-blue-700 border border-blue-200">
                    {{ session('reportbook_ai_info') }}
                </div>
            @endif

            @if ($entry)
                <div class="text-xs text-gray-500">
                    Eintrag #{{ $entry->id }}
                    @if($entry->entry_date)
                        · Datum: {{ $entry->entry_date->format('d.m.Y') }}
                    @endif
                </div>
            @endif

            {{-- Aktueller Basistext --}}
            <div>
                <x-ui.forms.label value="Aktueller Berichtsheft-Text (Basis für die KI)" />
                <textarea
                    wire:model.defer="currentText"
                    rows="6"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:ring-primary-500 focus:border-primary-500"
                ></textarea>
                <p class="mt-1 text-[11px] text-gray-500">
                    Dieser Text wird als Grundlage verwendet. Du kannst ihn hier vorab grob anpassen.
                </p>
            </div>

            {{-- Feedback / Wünsche an die KI --}}
            <div>
                <x-ui.forms.label value="Wünsche an die KI (optional)" />
                <textarea
                    wire:model.defer="feedback"
                    rows="3"
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="z.B.: Bitte knapper formulieren, mehr auf Praxis eingehen, Fachbegriffe beibehalten …"
                ></textarea>
            </div>

            {{-- Aktionen zur Generierung --}}
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button.primary
                    type="button"
                    wire:click="generateSuggestion"
                    wire:loading.attr="disabled"
                    wire:target="generateSuggestion"
                >
                    <span wire:loading.remove wire:target="generateSuggestion">
                        KI-Vorschlag erzeugen / verbessern
                    </span>
                    <span wire:loading wire:target="generateSuggestion">
                        KI denkt …
                    </span>
                </x-ui.button.primary>

                @if ($optimizedText)
                    <x-ui.button.secondary
                        type="button"
                        wire:click="useSuggestionAsBase"
                        wire:loading.attr="disabled"
                        wire:target="useSuggestionAsBase"
                    >
                        Vorschlag als neue Basis verwenden
                    </x-ui.button.secondary>
                @endif
            </div>

            {{-- KI-Ergebnis --}}
            @if ($optimizedText)
                <div class="pt-4 border-t border-gray-100 space-y-3">
                    <div>
                        <x-ui.forms.label value="Optimierter Text der KI" />
                        <textarea
                            wire:model="optimizedText"
                            rows="6"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:ring-primary-500 focus:border-primary-500"
                        ></textarea>
                        <p class="mt-1 text-[11px] text-gray-500">
                            Du kannst den Vorschlag noch fein anpassen, bevor du ihn speicherst.
                        </p>
                    </div>

                    @if ($aiComment)
                        <div class="px-3 py-2 rounded-md bg-slate-50 border border-slate-200 text-xs text-slate-700">
                            <div class="font-semibold mb-1">
                                Kommentar der KI
                            </div>
                            <p class="leading-relaxed">
                                {{ $aiComment }}
                            </p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-slot>

    <x-slot name="footer">
        <div class="flex justify-between w-full">
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <span wire:loading wire:target="generateSuggestion,saveToEntry">
                    Verarbeite …
                </span>
            </div>
            <div class="flex gap-2">
                <x-ui.button.secondary wire:click="close">
                    Abbrechen
                </x-ui.button.secondary>

                <x-ui.button.primary
                    wire:click="saveToEntry"
                    wire:loading.attr="disabled"
                    wire:target="saveToEntry"
                    @class([
                        'opacity-50 cursor-not-allowed' => !$optimizedText && !$currentText,
                    ])
                    {{ (!$optimizedText && !$currentText) ? 'disabled' : '' }}
                >
                    Optimierten Text speichern
                </x-ui.button.primary>
            </div>
        </div>
    </x-slot>
</x-dialog-modal>
