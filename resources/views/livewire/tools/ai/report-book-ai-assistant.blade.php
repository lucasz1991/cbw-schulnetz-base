<div x-data="{
        step: @js($optimizedText ? 'result' : 'input')
    }"
>
<x-dialog-modal wire:model="showModal" maxWidth="4xl">
<x-slot name="title">
    <div class="flex items-start justify-between gap-3">
        {{-- Left: Icon + Title --}}
        <div class="flex items-center gap-3 min-w-0">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-emerald-600 text-white shadow-sm">
                <i class="fas fa-robot text-sm"></i>
            </div>

            <div class="min-w-0">
                <h2 class="text-base font-semibold text-slate-900 truncate">
                    Berichtsheft mit KI optimieren
                </h2>
                <p class="text-xs text-slate-500">
                    Ausgangstext prüfen → optional Wünsche → KI-Vorschlag erzeugen → speichern.
                </p>
            </div>
        </div>

        {{-- Right: Close Button --}}
        <button
            type="button"
            wire:click="close"
            class="shrink-0 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500
                   hover:bg-slate-100 hover:text-slate-700 transition focus:outline-none focus:ring-2 focus:ring-blue-200"
            title="Schließen"
        >
            <i class="fal fa-times text-sm"></i>
        </button>
    </div>
</x-slot>

    {{-- ===== CONTENT ===== --}}
    <x-slot name="content">
        @if($showModal)
            <div class="relative p-1">
                {{-- Loading veil --}}
                <div
                    wire:loading.flex
                    wire:target="generateSuggestion,useSuggestionAsBase,saveToEntry"
                    class="absolute inset-0 z-20 items-center justify-center rounded-2xl bg-white/80 backdrop-blur-sm"
                >
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <i class="fal fa-spinner text-sm animate-spin"></i>
                        <span class="text-sm font-semibold text-slate-700">KI arbeitet…</span>
                    </div>
                </div>

                <div class="space-y-5">
                    {{-- Status --}}
                    <div class="space-y-2">
                        @if (session()->has('reportbook_ai_saved'))
                            <div class="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                                <i class="fas fa-check-circle mt-0.5"></i>
                                <span>{{ session('reportbook_ai_saved') }}</span>
                            </div>
                        @endif

                        @if (session()->has('reportbook_ai_info'))
                            <div class="flex items-start gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                                <i class="fas fa-info-circle mt-0.5"></i>
                                <span>{{ session('reportbook_ai_info') }}</span>
                            </div>
                        @endif
                    </div>

{{-- Step Switch (Blue / Green Progress Style) --}}
<div class="flex flex-wrap items-center gap-3">
    {{-- STEP 1 --}}
    <button
        type="button"
        @click="step='input'"
        class="group relative inline-flex items-center gap-3 rounded-2xl px-5 py-2.5 text-xs font-semibold transition-all ring-1"
        :class="step === 'input'
            ? 'bg-gradient-to-r from-blue-600 to-emerald-500 text-white ring-transparent shadow-md'
            : 'bg-white text-slate-700 ring-slate-200 hover:bg-blue-50 hover:text-blue-700'"
    >
        {{-- Step Dot --}}
        <span
            class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold transition"
            :class="step === 'input'
                ? 'bg-white/20 text-white'
                : 'bg-blue-100 text-blue-700 group-hover:bg-blue-200'"
        >
            1
        </span>

        <span class="tracking-wide">Eingabe</span>

        {{-- Active underline glow --}}
        <span
            x-show="step === 'input'"
            x-cloak
            class="absolute inset-x-4 -bottom-1 h-0.5 rounded-full bg-white/60"
        ></span>
    </button>

    {{-- STEP 2 --}}
    <button
        type="button"
        @click="step='result'"
        class="group relative inline-flex items-center gap-3 rounded-2xl px-5 py-2.5 text-xs font-semibold transition-all ring-1"
        :class="step === 'result'
            ? 'bg-gradient-to-r from-blue-600 to-emerald-500 text-white ring-transparent shadow-md'
            : 'bg-white text-slate-700 ring-slate-200 hover:bg-blue-50 hover:text-blue-700'"
    >
        {{-- Step Dot --}}
        <span
            class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold transition"
            :class="step === 'result'
                ? 'bg-white/20 text-white'
                : 'bg-blue-100 text-blue-700 group-hover:bg-blue-200'"
        >
            2
        </span>

        <span class="tracking-wide">Ergebnis</span>

        {{-- Ready badge --}}
        @if($optimizedText)
            <span
                class="ml-1 inline-flex items-center rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-semibold text-white"
                :class="step !== 'result' ? 'bg-emerald-100 text-emerald-700' : ''"
            >
                bereit
            </span>
        @endif

        {{-- Active underline glow --}}
        <span
            x-show="step === 'result'"
            x-cloak
            class="absolute inset-x-4 -bottom-1 h-0.5 rounded-full bg-white/60"
        ></span>
    </button>
</div>


                    {{-- =========================
                        STEP 1: INPUT (Ausgangstext + Wünsche)
                    ========================= --}}
                    <section x-show="step==='input'" x-cloak class="space-y-4">
                        <div class="">
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ausgangstext</p>
                                    <p class="mt-1 text-xs text-slate-400">Ansicht – Änderungen machst du im Berichtsheft selbst.</p>
                                </div>

                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200">
                                    <i class="fal fa-eye"></i> Ansicht
                                </span>
                            </div>

                            <div class="">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 max-h-[320px] overflow-y-auto">
                                    @if (trim($currentText) !== '')
                                        <div class="prose prose-sm max-w-none">
                                            {!! $currentText !!}
                                        </div>
                                    @else
                                        <p class="text-xs italic text-slate-400">Noch kein Text vorhanden.</p>
                                    @endif
                                </div>

                                <div class="mt-4 ">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold text-slate-700">
                                                Wünsche an die KI
                                                <span class="ml-1 text-[11px] font-normal text-slate-400">(optional)</span>
                                            </p>
                                            <p class="mt-1 text-[11px] text-slate-400">
                                                z.B. kürzer, strukturierter, Aufgaben als Liste, professioneller Ton…
                                            </p>
                                        </div>
                                    </div>

                                    <div  class="mt-3">
                                        <textarea
                                            rows="3"
                                            class="block w-full rounded-xl border border-secondary-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            wire:model.defer="feedback"
                                            placeholder="z.B.: Bitte in 3 Absätze gliedern, Aufgaben als Bulletpoints, max. 6 Sätze, keine Wiederholungen…"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- =========================
                        STEP 2: RESULT
                    ========================= --}}
                    <section x-show="step==='result'" x-cloak class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Vorschlag der KI</p>
                                <p class="mt-1 text-xs text-blue-500">Überarbeiteter Text</p>
                            </div>

                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-blue-700 ring-1 ring-blue-200">
                                <i class="fal fa-robot"></i>
                                KI-Entwurf
                            </span>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-xl border border-blue-200 bg-white p-4 shadow-inner max-h-[320px] overflow-y-auto">
                                @if($optimizedText)
                                    <div class="prose prose-sm max-w-none">
                                        {!! $optimizedText !!}
                                    </div>
                                @else
                                    <div class="text-sm text-blue-700/80">
                                        Noch kein Ergebnis. Bitte erst in Schritt 1 einen Vorschlag erzeugen.
                                    </div>
                                @endif
                            </div>

                            @if ($aiComment)
                                <div class="rounded-xl border border-blue-200 bg-blue-100/70 px-4 py-3 text-xs text-blue-800">
                                    <div class="flex items-start gap-2">
                                        <i class="fal fa-comment-dots mt-0.5"></i>
                                        <div>
                                            <div class="font-semibold mb-1">Kommentar der KI</div>
                                            <p class="leading-relaxed">{{ $aiComment }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        @endif
    </x-slot>

    {{-- ===== FOOTER (ALLE BUTTONS HIER) ===== --}}
    <x-slot name="footer">
        <div class="w-full">
            {{-- Right: Context actions --}}
            <div class="flex flex-wrap items-center justify-end gap-2">
                {{-- Step nav (nur wenn sinnvoll) --}}
                <x-buttons.button-basic
                    type="button"
                    class="text-xs"
                    x-show="step !== 'input'"
                    x-cloak
                    x-on:click="step='input'"
                >
                    <i class="fal fa-arrow-left text-[11px]"></i>
                    Eingabe
                </x-buttons.button-basic>
                @if($optimizedText)
                <x-buttons.button-basic
                    type="button"
                    class="text-xs"
                    x-show="step !== 'result'"
                    x-cloak
                    x-on:click="step='result'"
                >
                    <i class="fal fa-arrow-right text-[11px]"></i>
                    Ergebnis
                </x-buttons.button-basic>


                
                <x-buttons.button-basic
                    type="button"
                    class="text-xs"
                    wire:click="generateSuggestion"
                    wire:loading.attr="disabled"
                    wire:target="generateSuggestion"
                    x-on:click="step='result'"
                >
                    <span wire:loading.remove wire:target="generateSuggestion" class="inline-flex items-center gap-2">
                        <i class="fal fa-recycle text-[11px]"></i>
                        Neu
                    </span>
                    <span wire:loading wire:target="generateSuggestion" class="inline-flex items-center gap-2">
                        <i class="fal fa-spinner text-sm animate-spin"></i>
                        KI denkt…
                    </span>
                </x-buttons.button-basic>
                
                <x-buttons.button-basic
                    type="button"
                    :mode="'secondary'"
                    class="text-xs"
                    x-show="step === 'result' && @js((bool) $optimizedText)"
                    x-cloak
                    wire:click="saveToEntry"
                    wire:loading.attr="disabled"
                    wire:target="saveToEntry"
                >
                    <i class="fas fa-check text-[11px]"></i>
                    speichern
                </x-buttons.button-basic>
                @else
                                {{-- STEP 1 actions --}}
                <x-buttons.button-basic
                    type="button"
                    class="text-xs"
                    wire:click="generateSuggestion"
                    wire:loading.attr="disabled"
                    wire:target="generateSuggestion"
                    x-on:click="step='result'"
                >
                    <span wire:loading.remove wire:target="generateSuggestion" class="inline-flex items-center gap-2">
                        <i class="fas fa-wand-magic text-[11px]"></i>
                        KI-Vorschlag erzeugen
                    </span>
                    <span wire:loading wire:target="generateSuggestion" class="inline-flex items-center gap-2">
                        <i class="fal fa-spinner text-sm animate-spin"></i>
                        KI denkt…
                    </span>
                </x-buttons.button-basic>
                @endif
            </div>
        </div>
    </x-slot>
</x-dialog-modal>
</div>
