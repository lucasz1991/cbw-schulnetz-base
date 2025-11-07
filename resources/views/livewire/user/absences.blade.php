<x-dialog-modal wire:model="showModal" maxWidth="2xl">
  <x-slot name="title">Fehlzeit entschuldigen</x-slot>

  <x-slot name="content">
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
          <x-ui.forms.label value="Name"/>
          <p class="text-gray-700 font-semibold">{{ auth()->user()->name }}</p>
        </div>
        <div>
          <x-ui.forms.label for="klasse" value="Klasse"/>
          <x-ui.forms.input id="klasse" type="text" maxlength="12" placeholder="z. B. INF23A"
                   wire:model.live.debounce.300ms="klasse"/>
          <x-ui.forms.input-error for="klasse"/>
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-4 items-end">
          <div>
            <x-ui.forms.label for="fehlDatum" value="Datum"/>
            <x-ui.forms.input id="fehlDatum" type="date" wire:model="fehlDatum"/>
            <x-ui.forms.input-error for="fehlDatum"/>
          </div>
        <label class="inline-flex items-center gap-2">
          <x-ui.forms.checkbox x-model="fehltag"/>
          <span>Ganztägig gefehlt</span>
        </label>

      </div>

    {{-- Zeiten-Bereich nur zeigen, wenn NICHT ganztägig --}}
    <div class="grid md:grid-cols-2 gap-4"
        x-show="!fehltag"
        x-transition.opacity.duration.150ms
        x-cloak
        aria-hidden="false">
        <div>
        <x-ui.forms.label for="fehlUhrGek" value="Später gekommen (Uhrzeit)"/>
        <x-ui.forms.input id="fehlUhrGek" type="time" min="08:00" max="23:00"
                            x-bind:disabled="fehltag" class="disabled:bg-gray-100"
                            wire:model="fehlUhrGek"/>
        <x-ui.forms.input-error for="fehlUhrGek"/>
        </div>
        <div>
        <x-ui.forms.label for="fehlUhrGeg" value="Früher gegangen (Uhrzeit)"/>
        <x-ui.forms.input id="fehlUhrGeg" type="time" min="08:00" max="23:00"
                            x-bind:disabled="fehltag" class="disabled:bg-gray-100"
                            wire:model="fehlUhrGeg"/>
        <x-ui.forms.input-error for="fehlUhrGeg"/>
        </div>
    </div>

        {{-- Grund der Fehlzeit --}}
        <div class="space-y-2">
            <x-ui.forms.label value="Grund der Fehlzeit"/>
            <div class="flex flex-wrap gap-6">
                <label class="inline-flex items-center gap-2">
                <input type="radio" class="text-blue-600" value="abw_wichtig" x-model="abw_grund">
                <span>Mit wichtigem Grund</span>
                </label>
                <label class="inline-flex items-center gap-2">
                <input type="radio" class="text-blue-600" value="abw_unwichtig" x-model="abw_grund">
                <span>Ohne wichtigen Grund</span>
                </label>
            </div>

            <div x-show="showGrundBox" x-cloak class="py-4">
                <x-ui.forms.label for="grund_item" value="Grund auswählen"/>
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

      {{-- Livewire Upload --}}
      <div class="border-t pt-4"   x-data="{ dropzone: null }"
           x-on:open-absence-dropzone.window="$dispatch('dropzone:mount')">
        <x-ui.forms.label value="Anlagen (jpg, png, gif, pdf)"/>
  <x-ui.filepool.drop-zone
      :model="'attachments'"
      :maxFiles="10"
  />
      </div>

      {{-- Submit im Formular (Enter) --}}
      <button type="submit" class="hidden"></button>
    </form>
  </x-slot>

  <x-slot name="footer">
    <x-secondary-button wire:click="close">Schließen</x-secondary-button>
    <x-button class="ml-2" wire:click="save">Speichern</x-button>
  </x-slot>
</x-dialog-modal>
