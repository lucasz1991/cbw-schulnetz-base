<x-dialog-modal wire:model="showModal" maxWidth="3xl">
    <x-slot name="title">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-magic text-sm"></i>
            </div>
            <div>
                <h2 class="text-base font-semibold text-gray-900">
                    Berichtsheft mit KI optimieren
                </h2>
                <p class="text-xs text-gray-500">
                    Lass dir deinen Eintrag sprachlich und strukturell verbessern – inklusive HTML-Struktur.
                </p>
            </div>
        </div>
    </x-slot>

    <x-slot name="content">
        @if($showModal)
            <div
                x-data="{ showAdvanced: false }"
                class="space-y-6"
                wire:loading.class="opacity-60 pointer-events-none"
            >
                {{-- Status-Meldungen --}}
                @if (session()->has('reportbook_ai_saved'))
                    <div class="flex items-start gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                        <i class="fas fa-check-circle mt-0.5"></i>
                        <span>{{ session('reportbook_ai_saved') }}</span>
                    </div>
                @endif

                @if (session()->has('reportbook_ai_info'))
                    <div class="flex items-start gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                        <i class="fas fa-info-circle mt-0.5"></i>
                        <span>{{ session('reportbook_ai_info') }}</span>
                    </div>
                @endif

                {{-- ===================== EINGABE-BEREICH ===================== --}}
                @if (!$optimizedText)
                    <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Ausgangstext
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    Dies ist dein aktueller Berichtsheft-Eintrag.
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-slate-400">
                                <i class="fas fa-pen-nib"></i>
                                <span>Nur Ansicht – Änderungen machst du im Berichtsheft selbst.</span>
                            </div>
                        </div>

                        <div class="mt-1 block w-full rounded-lg border border-slate-200 bg-white/70 p-3 text-sm shadow-inner max-h-[420px] overflow-y-auto">
                            @if (trim($currentText) !== '')
                                <div class="prose prose-sm max-w-none">
                                    {!! $currentText !!}
                                </div>
                            @else
                                <p class="text-xs italic text-slate-400">
                                    Noch kein Text vorhanden.
                                </p>
                            @endif
                        </div>

                        {{-- Erweiterte Wünsche (Collapse) --}}
                        <div class="mt-4 border-t border-dashed border-slate-200 pt-3">
                            <button
                                type="button"
                                class="flex items-center gap-2 text-xs font-medium text-slate-600 hover:text-slate-800"
                                x-on:click="showAdvanced = !showAdvanced"
                            >
                                <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-300 text-[9px]">
                                    <i class="fas" :class="showAdvanced ? 'fa-minus' : 'fa-plus'"></i>
                                </span>
                                <span>Erweiterte Wünsche an die KI</span>
                                <span class="text-[10px] text-slate-400">(optional)</span>
                            </button>

                            <div
                                x-show="showAdvanced"
                                x-collapse
                                x-cloak
                                class="mt-3 space-y-1"
                            >
                                <x-ui.forms.label
                                    value="Wünsche an die KI"
                                    class="text-xs text-slate-600"
                                />
                                <textarea
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border border-slate-300 bg-slate-50 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    wire:model.defer="feedback"
                                    placeholder="z.B.: Bitte kürzer, strukturierter, in Absätze gliedern, Aufzählungen für Aufgaben verwenden …"
                                ></textarea>
                                <p class="text-[11px] text-slate-400">
                                    Nutze dieses Feld, wenn du der KI zusätzliche Hinweise geben möchtest (z.B. Ton, Länge, Schwerpunkt).
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Aktionen: KI starten --}}
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-[11px] text-slate-400 flex items-center gap-1">
                            <i class="fas fa-lightbulb"></i>
                            <span>Die KI erstellt einen Vorschlag, der deinen Originaltext nicht überschreibt, bis du ihn speicherst.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-button
                                type="button"
                                class="inline-flex items-center gap-2"
                                wire:click="generateSuggestion"
                                wire:loading.attr="disabled"
                                wire:target="generateSuggestion"
                            >
                                <span wire:loading.remove wire:target="generateSuggestion">
                                    <i class="fas fa-wand-magic-sparkles text-xs"></i>
                                    <span>KI-Vorschlag erzeugen</span>
                                </span>
                                <span wire:loading wire:target="generateSuggestion" class="flex items-center gap-2">
                                    <span class="h-3 w-3 animate-spin rounded-full border border-white/40 border-t-white"></span>
                                    <span>KI denkt …</span>
                                </span>
                            </x-button>
                        </div>
                    </div>
                @endif

                {{-- ===================== KI-ERGEBNIS ===================== --}}
                @if ($optimizedText)
                    <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-blue-700">
                                    Vorschlag der KI
                                </div>
                                <div class="text-[11px] text-blue-500">
                                    Überarbeiteter, für das Berichtsheft geeigneter Text (inklusive HTML-Struktur).
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full bg-white/70 px-2 py-0.5 text-[10px] font-medium text-blue-700 border border-blue-200">
                                <i class="fas fa-robot"></i>
                                <span>KI-Entwurf</span>
                            </span>
                        </div>

                        <div class="mt-1 block w-full rounded-lg border border-blue-200 bg-white p-3 text-sm shadow-inner max-h-[420px] overflow-y-auto">
                            <div class="prose prose-sm max-w-none">
                                {!! $optimizedText !!}
                            </div>
                        </div>

                        {{-- Kommentar --}}
                        @if ($aiComment)
                            <div class="mt-3 flex items-start gap-2 rounded-md bg-blue-100/80 px-3 py-2 text-xs text-blue-800 border border-blue-200">
                                <i class="fas fa-comment-dots mt-0.5"></i>
                                <div>
                                    <div class="font-semibold mb-0.5">Kommentar der KI</div>
                                    <p class="leading-relaxed">
                                        {{ $aiComment }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- Buttons unter dem Kommentar --}}
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="text-[11px] text-blue-500 flex items-center gap-1">
                                <i class="fas fa-arrow-turn-down"></i>
                                <span>Du kannst den Vorschlag übernehmen oder noch einmal einen neuen Vorschlag anfordern.</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-buttons.button-basic
                                    type="button"
                                    wire:click="useSuggestionAsBase"
                                    wire:loading.attr="disabled"
                                    wire:target="useSuggestionAsBase"
                                    class="text-xs"
                                >
                                    <i class="fas fa-pen me-1"></i>
                                    Weiter im Editor bearbeiten
                                </x-buttons.button-basic>

                                <x-button
                                    type="button"
                                    wire:click="generateSuggestion"
                                    wire:loading.attr="disabled"
                                    wire:target="generateSuggestion"
                                    class="text-xs"
                                >
                                    <span wire:loading.remove wire:target="generateSuggestion" class="flex items-center gap-2">
                                        <i class="fas fa-rotate-right text-[11px]"></i>
                                        <span>Neuen Vorschlag erzeugen</span>
                                    </span>
                                    <span wire:loading wire:target="generateSuggestion" class="flex items-center gap-2">
                                        <span class="h-3 w-3 animate-spin rounded-full border border-white/40 border-t-white"></span>
                                        <span>KI denkt …</span>
                                    </span>
                                </x-button>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        @endif
    </x-slot>

    {{-- ===================== FOOTER ===================== --}}
    <x-slot name="footer">
        <div class="flex w-full items-center justify-between gap-3">
            @if(!$optimizedText)
                <p class="text-[11px] text-slate-400">
                    Tipp: Erzeuge zuerst einen KI-Vorschlag. Speichern ist erst danach möglich.
                </p>
            @else
                <p class="text-[11px] text-slate-400 flex items-center gap-1">
                    <i class="fas fa-save"></i>
                    <span>Der optimierte Text wird in deinen Berichtsheft-Eintrag übernommen.</span>
                </p>
            @endif

            <div class="flex items-center gap-2">
                <x-secondary-button wire:click="close">
                    Schließen
                </x-secondary-button>

                {{-- Speichern nur, wenn ein KI-Text vorhanden ist --}}
                @if($optimizedText)
                    <x-button
                        class="ml-1"
                        wire:click="saveToEntry"
                        wire:loading.attr="disabled"
                        wire:target="saveToEntry"
                    >
                        <i class="fas fa-check me-1 text-xs"></i>
                        Optimierten Text speichern
                    </x-button>
                @endif
            </div>
        </div>
    </x-slot>
</x-dialog-modal>
