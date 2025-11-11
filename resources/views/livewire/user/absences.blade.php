<x-dialog-modal wire:model="showModal" maxWidth="2xl">
  <x-slot name="title">Fehlzeit entschuldigen</x-slot>

  <x-slot name="content">
    @if($showModal)
    <form
      x-data="{
        fehltag: @entangle('fehltag').live,
        abw_grund: @entangle('abw_grund').live,
        get showGrundBox(){ return this.abw_grund === 'abw_wichtig' }
      }"
        x-effect="if (fehltag) { $wire.set('fehlUhrGek', null); $wire.set('fehlUhrGeg', null); }"

      class="space-y-6"
      wire:submit.prevent="save"
    >
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <x-ui.forms.label value="Person"/>
          <p class="text-gray-700 font-semibold">{{ auth()->user()?->person ? ( auth()->user()?->person?->vorname.' '.auth()->user()?->person?->nachname ) :  auth()->user()->name }}</p>
          <p class="text-gray-500 text-xs">{{ auth()->user()?->person?->person_id ?  auth()->user()?->person?->person_id :  'keine person Id vorhanden' }}</p>
        </div>
        <div>
          <x-ui.forms.label for="klasse" value="Klasse"/>
          <x-ui.forms.input id="klasse" type="text" maxlength="12" placeholder="z. B. INF23A"
                   wire:model.live.debounce.300ms="klasse"/>
          <x-ui.forms.input-error for="klasse"/>
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-8 ">
          <div>
            
              <x-ui.forms.date-input
                  id="fehlDatum"
                  model="fehlDatum"
                  label="Datum"
                  :inline="true"
                  required
              />
            
            <x-ui.forms.input-error for="fehlDatum"/>
          </div>
          <div class="h-full">
            <div class="h-max">
              <x-ui.forms.label value="Zeiten"/>
            </div>
            <div class="grid justify-stretch self-stretch h-full">
                <div>
                  <x-ui.forms.checkbox
                      id="fehltag"
                      label="Ganztägig gefehlt"
                      wire:model="fehltag"
                          :toggle="true"
                  />
  
                </div>
                
                {{-- Zeiten-Bereich nur zeigen, wenn NICHT ganztägig --}}
                <div
                    x-show="!fehltag"
                    x-cloak
                    aria-hidden="false">
                    <div class="space-y-4">
                      <div>
                         <x-ui.forms.label for="fehlUhrGek" value="Später gekommen (Uhrzeit)"/>
                          <x-ui.forms.time-input
                            id="fehlUhrGek"
                            name="fehlUhrGek"
                            min="08:00"
                            max="23:00"
                            x-bind:disabled="fehltag"
                            class="disabled:bg-gray-100"
                            wire:model="fehlUhrGek"
                            :inline="true"
                        />
                      </div>
                      <div>
                        <x-ui.forms.label for="fehlUhrGeg" value="Früher gegangen (Uhrzeit)"/>
                        <x-ui.forms.time-input
                            id="fehlUhrGeg"
                            name="fehlUhrGeg"
                            min="08:00"
                            max="23:00"
                            x-bind:disabled="fehltag"
                            class="disabled:bg-gray-100"
                            wire:model="fehlUhrGeg"
                            :inline="true"
                        />
    
                      </div>
                    </div>
                </div>
            </div>
          </div>
      </div>


        {{-- Grund der Fehlzeit --}}
        <div class="space-y-2 pt-8">
        <div class="flex justify-center">
          <x-ui.forms.radio-btn-group label="Grund der Fehlzeit">
              <x-ui.forms.radio-btn-group-item
              name="abw_grund"
                  label="Mit wichtigem Grund"
                  value="abw_wichtig"
                  wire:model="abw_grund"
                  icon="fa-check-circle"
                  iconStyle="fal"
              />
              <x-ui.forms.radio-btn-group-item
              name="abw_grund"
                  label="Ohne wichtigen Grund"
                  value="abw_unwichtig"
                  wire:model="abw_grund"
                  icon="fa-ban"
                  iconStyle="fal"
              />
          </x-ui.forms.radio-btn-group>
          </div>
            <div x-show="showGrundBox" x-cloak x-collapse>
                <x-ui.forms.label for="grund_item" value="Grund auswählen" class="pt-4"/>
                <select id="grund_item" class="mt-1 block w-full border-gray-300 rounded"
                        wire:model="grund_item">
                <option value="">Bitte wählen …</option>
                @foreach(($reasons ?? []) as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
                </select>
                <x-ui.forms.input-error for="grund_item"/>
            </div>
        </div>

      <div>
        <x-ui.forms.label for="begruendung" value="Sonstige Begründung"/>
        <textarea id="begruendung" maxlength="400"
                  class="mt-1 block w-full border-gray-300 rounded"
                  wire:model.lazy="begruendung"
                  placeholder="max. 400 Zeichen"></textarea>
        <div class="text-xs text-gray-500 text-right">
          {{ strlen($begruendung ?? '') }}/400
        </div>
        <x-ui.forms.input-error for="begruendung"/>
      </div>
        @if($showModal)
      {{-- Livewire Upload --}}
      <div class="pt-4"   x-data="{ dropzone: null }"
           x-on:open-absence-dropzone.window="$dispatch('dropzone:mount')">
        <x-ui.forms.label value="Anlagen (jpg, png, gif, pdf)"/>
        <x-ui.filepool.drop-zone
            :model="'absence_attachments'"
            :maxFiles="10"
            acceptedFiles=".jpg,.jpeg,.png,.gif,.pdf"
        />

      </div>
        @endif


      {{-- Submit im Formular (Enter) --}}
      <button type="submit" class="hidden"></button>
    </form>
    @endif
  </x-slot>

  <x-slot name="footer">
    <x-secondary-button wire:click="close">Schließen</x-secondary-button>
    <x-button class="ml-2" wire:click="save">Speichern</x-button>
  </x-slot>
</x-dialog-modal>
