<div>
    <x-dialog-modal wire:model="showModal">
        <x-slot name="title">
            Baustein Bewertung
        </x-slot>
    
        <x-slot name="content">
            @if($alreadyRated)
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    Ihre Bewertung zu diesem Baustein wurde bereits gespeichert. Vielen Dank!
                </div>
            @else
                {{-- Stepper State in Alpine, entangled mit Livewire --}}
                <div x-data="{ step: @entangle('currentStep') }" class="">
    
                    {{-- Fortschrittsanzeige / Progressbar + Step-Pills --}}
                    <div>
                        <div class="flex items-center justify-between text-xs font-medium text-gray-600 mb-2">
                            <span>Schritt <span x-text="step"></span> von 5</span>
                            <span x-show="step===1">Kundenbetreuung</span>
                            <span x-show="step===2">Systemadministration</span>
                            <span x-show="step===3">Institutsleitung</span>
                            <span x-show="step===4">Dozent/-in</span>
                            <span x-show="step===5">Nachricht & Absenden</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div class="bg-sky-600 h-2 rounded-full transition-all"
                                 :style="`width: ${Math.round((step/5)*100)}%`">
                            </div>
                        </div>
    
                        <div class="mt-3 flex items-center justify-between">
                            <template x-for="n in 5" :key="n">
                                <button type="button"
                                        class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold border transition-colors"
                                        :class="{
                                            'bg-sky-600 text-white border-sky-600': step===n,
                                            'bg-white text-gray-700 border-gray-300': step!==n
                                        }"
                                        @click="step = n"
                                        x-text="n"></button>
                            </template>
                        </div>
                    </div>
    
                    @if($currentStep === 1)
                    {{-- Anonym-Checkbox (global) --}}
                    <label class="inline-flex items-center space-x-2 mt-6">
                        <input type="checkbox" wire:model="is_anonymous" class="rounded border-gray-300">
                        <span>Ich möchte anonym bewerten (ohne Personen-Daten).</span>
                    </label>
                    @endif
    
                    {{-- Bewertungsblöcke oder Nachricht je nach Step --}}
                    {{-- Definieren der Blöcke und Fragen als Array --}}
                    @php
                        $blocks = [
                            1 => ['title' => 'Kundenbetreuung', 'rows' => [
                                'kb_1' => 'Wie kompetent sind die Mitarbeiter/-innen der Kundenbetreuung?',
                                'kb_2' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                                'kb_3' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
                            ]],
                            2 => ['title' => 'Systemadministration', 'rows' => [
                                'sa_1' => 'Wie kompetent sind die Mitarbeiter/-innen der Systemadministration?',
                                'sa_2' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                                'sa_3' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
                            ]],
                            3 => ['title' => 'Institutsleitung', 'rows' => [
                                'il_1' => 'Wie beurteilen Sie die Organisation im Institut?',
                                'il_2' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                                'il_3' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
                            ]],
                            4 => ['title' => 'Dozent/-in', 'rows' => [
                                'do_1' => 'War der/die Dozent/-in Ihnen gegenüber freundlich und höflich?',
                                'do_2' => 'Wie beurteilen Sie die Fachkompetenz?',
                                'do_3' => 'Wie beurteilen Sie die methodischen und didaktischen Fähigkeiten?',
                            ]],
                        ];
                    @endphp
    
    
                    <div class="my-6">
    
                        {{-- Schritte 1-4: Bewertungsblöcke --}}
                        @foreach($blocks as $idx => $block)
                            <div x-show="step === {{ $idx }}" x-collapse class="rounded-lg border overflow-hidden ">
                                <div class="bg-gray-100 px-4 py-2 font-semibold">{{ $block['title'] }}</div>
                                <div class="divide-y">
                                    @foreach($block['rows'] as $fieldName => $label)
                                        <div class="p-4">
                                            <div class="text-sm mb-2">{{ $label }}</div>
        
                                            {{-- Sterne-Eingabe (Hover via Alpine) --}}
                                            <div class="flex justify-center" x-data="{ hovered: 0 }">
                                                <div class="flex justify-center space-x-1 rating-group">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <label class="cursor-pointer relative"
                                                            @mouseover="hovered = {{ $i }}"
                                                            @mouseleave="hovered = 0">
                                                            <input
                                                                type="radio"
                                                                wire:model.live="{{ $fieldName }}"
                                                                value="{{ $i }}"
                                                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                                            >
                                                            <span class="text-xl transition-colors duration-150 text-gray-300">
                                                                <svg
                                                                    class="w-10 h-10 transition-colors duration-150"
                                                                    :class="{
                                                                        'text-yellow-400': hovered >= {{ $i }} || {{ (int) (data_get($this, $fieldName, 0) ?? 0) }} >= {{ $i }},
                                                                        'text-gray-300': hovered < {{ $i }} && {{ (int) (data_get($this, $fieldName, 0) ?? 0) }} < {{ $i }}
                                                                    }"
                                                                    fill="currentColor" viewBox="0 0 20 20">
                                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.204 3.698a1 1 0 00.95.69h3.894c.969 0 1.371 1.24.588 1.81l-3.15 2.286a1 1 0 00-.364 1.118l1.204 3.698c.3.921-.755 1.688-1.54 1.118l-3.15-2.286a1 1 0 00-1.176 0l-3.15 2.286c-.784.57-1.838-.197-1.539-1.118l1.203-3.698a1 1 0 00-.364-1.118L2.414 9.125c-.783-.57-.38-1.81.588-1.81h3.894a1 1 0 00.951-.69l1.202-3.698z"/>
                                                                </svg>
                                                            </span>
                                                        </label>
                                                    @endfor
                                                </div>
                                            </div>
        
                                            @error($fieldName)
                                                <div class="text-xs text-red-600 mt-2">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
        
                        {{-- Schritt 5: Nachricht & Absenden --}}
                        <div x-show="step === 5" x-collapse>
                            <div class="rounded-lg border overflow-hidden ">
                                <div class="bg-gray-100 px-4 py-2 font-semibold">Ihre Nachricht (optional)</div>
                                <div class="p-4">
                                    <label class="block text-sm font-medium mb-1">Ihre Nachricht an uns</label>
                                    <textarea
                                        wire:model.defer="message"
                                        maxlength="500"
                                        rows="5"
                                        class="mt-1 w-full rounded border-gray-300"
                                        placeholder="Nachricht max. 500 Zeichen"
                                    ></textarea>
                                    @error('message') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
    
                    {{-- Navigation unten (innerhalb content, damit immer unterhalb des sichtbaren Steps) --}}
                    <div class="flex items-center justify-between mt-6">
                        <x-secondary-button
                            x-bind:disabled="step===1"
                            @click="if(step>1) step--"
                        >Zurück</x-secondary-button>
    
                        <div class="flex items-center space-x-2">
                            <x-secondary-button
                                x-show="step < 5"
                                @click="if(step<5) step++"
                            >Weiter</x-secondary-button>
    
                            <x-button
                                x-show="step === 5"
                                wire:click="save"
                                wire:loading.attr="disabled"
                                class="bg-sky-600 hover:bg-sky-700"
                            >
                                <span wire:loading.remove>Bewertung speichern</span>
                                <span wire:loading>Speichern…</span>
                            </x-button>
                        </div>
                    </div>
                </div>
            @endif
        </x-slot>
    
        {{-- Optional: Footer leer lassen oder entfernen, da wir die Step-Navigation im Content haben --}}
        <x-slot name="footer"></x-slot>
    </x-dialog-modal>
</div>
