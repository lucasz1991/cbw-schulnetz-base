<x-dialog-modal wire:model="showModal" maxWidth="3xl">
    <x-slot name="title">
        Berichtsheft mit KI optimieren
    </x-slot>

    <x-slot name="content">
        @if($showModal)
            <div class="space-y-6" wire:loading.class="opacity-60 pointer-events-none">

                {{-- Status-Meldungen --}}
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

                {{-- Basistext --}}
                <div>
                    <x-ui.forms.label value="Aktueller Berichtsheft-Text" />
                    <textarea
                        rows="6"
                        class="mt-1 block w-full border-gray-300 rounded text-sm shadow-sm focus:ring-primary-500 focus:border-primary-500"
                        wire:model.defer="currentText"
                    ></textarea>
                    <p class="mt-1 text-[11px] text-gray-500">
                        Dieser Text dient als Grundlage für die KI. Du kannst ihn vorab grob anpassen.
                    </p>
                </div>

                {{-- Wünsche / Feedback --}}
                <div>
                    <x-ui.forms.label value="Wünsche an die KI (optional)" />
                    <textarea
                        rows="3"
                        class="mt-1 block w-full border-gray-300 rounded text-sm shadow-sm focus:ring-primary-500 focus:border-primary-500"
                        wire:model.defer="feedback"
                        placeholder="z. B.: Bitte etwas kürzer formulieren, Fachbegriffe beibehalten, mehr Praxisbezug …"
                    ></textarea>
                </div>

                {{-- Aktionen: KI starten / Vorschlag als Basis --}}
                <div class="flex flex-wrap items-center gap-2">
                    <x-button
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
                    </x-button>

                    @if ($optimizedText)
                        <x-buttons.button-basic
                            type="button"
                            wire:click="useSuggestionAsBase"
                            wire:loading.attr="disabled"
                            wire:target="useSuggestionAsBase"
                        >
                            Vorschlag als neue Basis verwenden
                        </x-buttons.button-basic>
                    @endif
                </div>

                {{-- KI Ergebnis --}}
                @if ($optimizedText)
                    <div class="pt-4 border-t border-gray-100 space-y-3">
                        <div>
                            <x-ui.forms.label value="Optimierter Text der KI" />
                            <textarea
                                rows="6"
                                class="mt-1 block w-full border-gray-300 rounded text-sm shadow-sm focus:ring-primary-500 focus:border-primary-500"
                                wire:model="optimizedText"
                            ></textarea>
                            <p class="mt-1 text-[11px] text-gray-500">
                                Du kannst den Vorschlag noch anpassen, bevor du ihn speicherst.
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
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-secondary-button wire:click="close">
            Schließen
        </x-secondary-button>

        @if(!$optimizedText && !$currentText)
            {{-- deaktivierter Button, wenn noch gar kein Text da ist --}}
            <x-button class="ml-2 opacity-50 cursor-not-allowed" disabled>
                Optimierten Text speichern
            </x-button>
        @else
            <x-button
                class="ml-2"
                wire:click="saveToEntry"
                wire:loading.attr="disabled"
                wire:target="saveToEntry"
            >
                Optimierten Text speichern
            </x-button>
        @endif
    </x-slot>
</x-dialog-modal>
