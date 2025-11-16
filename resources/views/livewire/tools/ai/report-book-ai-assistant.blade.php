<x-dialog-modal wire:model="showModal" maxWidth="3xl">
    <x-slot name="title">
        Berichtsheft optimieren
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


                {{-- -------------------------------------------------- --}}
                {{--   EINGABEBEREICH — nur zeigen, wenn KEIN Vorschlag --}}
                {{-- -------------------------------------------------- --}}
                @if (!$optimizedText)

                    {{-- Basistext (nur Anzeige, mit möglichem HTML) --}}
                    <div>
                        <x-ui.forms.label value="Aktueller Berichtsheft-Text" />
                        <div
                            class="mt-1 block w-full bg-gray-50 border border-gray-300 rounded text-sm shadow-sm p-3 max-h-[500px] overflow-y-auto"
                        >
                            @if(trim($currentText) !== '')
                                {{-- HTML-Struktur zulassen --}}
                                <div class="prose prose-sm max-w-none">
                                    {!! $currentText !!}
                                </div>
                            @else
                                <p class="text-xs text-gray-400 italic">
                                    Noch kein Text vorhanden.
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- Wünsche --}}
                    <div>
                        <x-ui.forms.label value="Wünsche an die KI (optional)" />
                        <textarea
                            rows="1"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 rounded text-sm shadow-sm"
                            wire:model.defer="feedback"
                            placeholder="z.B: Bitte kürzer, sachlicher, strukturierter…"
                        ></textarea>
                    </div>

                    {{-- Button: KI starten --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button
                            type="button"
                            wire:click="generateSuggestion"
                            wire:loading.attr="disabled"
                            wire:target="generateSuggestion"
                        >
                            <span wire:loading.remove>KI-Vorschlag erzeugen</span>
                            <span wire:loading>KI denkt …</span>
                        </x-button>
                    </div>

                @endif


                {{-- -------------------------------------------------- --}}
                {{--   KI-ERGEBNIS — nur zeigen, wenn Vorschlag existiert --}}
                {{-- -------------------------------------------------- --}}
                @if ($optimizedText)
                    <div class="pt-4 border-t border-gray-100 space-y-3">

                        {{-- Text der KI (nur Anzeige, mit möglichem HTML) --}}
                        <div>
                            <x-ui.forms.label value="Optimierter KI-Text" />
                            <div
                                class="mt-1 block w-full bg-gray-50 border border-gray-300 rounded text-sm shadow-sm p-3 max-h-[500px] overflow-y-auto"
                            >
                                <div class="prose prose-sm max-w-none">
                                    {!! $optimizedText !!}
                                </div>
                            </div>
                        </div>

                        {{-- Kommentar --}}
                        @if ($aiComment)
                            <div class="px-3 py-2 rounded bg-blue-100 border border-blue-200 text-xs text-blue-700">
                                <div class="font-semibold mb-1">Kommentar der KI</div>
                                <p class="leading-relaxed">{{ $aiComment }}</p>
                            </div>
                        @endif

                        {{-- Button UNTER dem Kommentar --}}
                        <x-buttons.button-basic
                            type="button"
                            wire:click="useSuggestionAsBase"
                            wire:loading.attr="disabled"
                            wire:target="useSuggestionAsBase"
                        >
                            Weiter Bearbeiten
                        </x-buttons.button-basic>

                    </div>
                @endif

            </div>
        @endif
    </x-slot>


    {{-- FOOTER --}}
    <x-slot name="footer">
        <x-secondary-button wire:click="close">Schließen</x-secondary-button>

        {{-- Speichern nur wenn irgendein Text existiert --}}
        @php
            $canSave = trim($optimizedText) !== '' || trim($currentText) !== '';
        @endphp

        @if(!$canSave)
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
