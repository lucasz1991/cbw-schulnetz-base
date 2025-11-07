<x-dialog-modal wire:model="showModal" maxWidth="2xl">
  <x-slot name="title">Antrag auf Nachprüfung</x-slot>

  <x-slot name="content">
    <form x-data="{ }" class="space-y-6" wire:submit.prevent="save">

      <div class="grid md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <strong>Name:</strong> {{ auth()->user()->name }}
        </div>
        <div class="text-right">
          <label for="klasse" class="block text-sm font-medium">Klasse</label>
          <input id="klasse" type="text" maxlength="12" placeholder="z. B. INF23A"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
                 wire:model.live.debounce.300ms="klasse">
          @error('klasse') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
      </div>

      <div>
        <p class="font-semibold mb-2">Ich beantrage gemäß Qualifizierungsordnung:</p>
        <div class="space-y-2">
          <label class="flex items-center">
            <input type="radio" value="wiederholung_1" class="mr-2"
                   :checked="$wire.wiederholung==='wiederholung_1'"
                   @click="$wire.wiederholung='wiederholung_1'">
            Eine Nach-/Wiederholungsprüfung (20,00 €)
          </label>
          <label class="flex items-center">
            <input type="radio" value="wiederholung_2" class="mr-2"
                   :checked="$wire.wiederholung==='wiederholung_2'"
                   @click="$wire.wiederholung='wiederholung_2'">
            Nachprüfung zur Ergebnisverbesserung (40,00 €)
          </label>
        </div>
        @error('wiederholung') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label for="nachKlTermin" class="block text-sm font-medium">Nachprüfungstermin</label>
          <select id="nachKlTermin" required
                  class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  wire:model.defer="nachKlTermin">
            <option value="">Bitte Termin wählen</option>
            <option value="1749208500">06.06.2025 - 13:15</option>
            <option value="1750411800">20.06.2025 - 11:30</option>
            <option value="1751621400">04.07.2025 - 11:30</option>
            <option value="1752831000">18.07.2025 - 11:30</option>
            <option value="1754040600">01.08.2025 - 11:30</option>
          </select>
          @error('nachKlTermin') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label for="nKlBaust" class="block text-sm font-medium">Baustein</label>
          <input id="nKlBaust" type="text" maxlength="10" required placeholder="Baustein"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm"
                 wire:model.defer="nKlBaust">
          @error('nKlBaust') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label for="nKlDozent" class="block text-sm font-medium">Instruktor / Dozent</label>
          <input id="nKlDozent" type="text" required placeholder="Dozent"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm"
                 wire:model.defer="nKlDozent">
          @error('nKlDozent') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
        <div>
          <label for="nKlOrig" class="block text-sm font-medium">Ursprüngliche Prüfung am</label>
          <input id="nKlOrig" type="date" required
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm"
                 wire:model.defer="nKlOrig">
          @error('nKlOrig') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>
      </div>

      <div>
        <p class="font-semibold mb-2">Begründung</p>
        <div class="space-y-2">
          <label class="flex items-center">
            <input type="radio" value="unter51" class="mr-2"
                   :checked="$wire.grund==='unter51'"
                   @click="$wire.grund='unter51'">
            Ursprüngliche Prüfung unter 51 Punkte
          </label>
          <label class="flex items-center">
            <input type="radio" value="krankMitAtest" class="mr-2"
                   :checked="$wire.grund==='krankMitAtest'"
                   @click="$wire.grund='krankMitAtest'">
            Krankheit am Prüfungstag, <strong>mit Attest</strong>
          </label>
          <label class="flex items-center">
            <input type="radio" value="krankOhneAtest" class="mr-2"
                   :checked="$wire.grund==='krankOhneAtest'"
                   @click="$wire.grund='krankOhneAtest'">
            Krankheit am Prüfungstag, <strong>ohne Attest</strong>
          </label>
        </div>
        @error('grund') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
      </div>

      {{-- Livewire Upload --}}
      <div class="border-t pt-4">
        <label class="block text-sm font-medium mb-2">Anlagen (jpg, png, gif, pdf)</label>
          <x-ui.filepool.drop-zone
                :model="'examRegistrationAttachments'"
                acceptedFiles=".jpg,.jpeg,.png,.gif,.pdf"
                maxFiles="3"
            />
        @error('examRegistrationAttachments.*') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
      </div>

      <div class="prose text-sm text-gray-600">
        <p>(*) Eine Nachprüfung ist kostenfrei, wenn ein Attest für den ursprünglichen Prüfungstag vorliegt.</p>
        <p>(**) Die Gebühr ist mit Abgabe der Anmeldung vor dem Nachprüfungstermin zu entrichten.</p>
      </div>

      {{-- Enter = Submit --}}
      <button type="submit" class="hidden"></button>
    </form>
  </x-slot>

  <x-slot name="footer">
    <x-secondary-button wire:click="close">Schließen</x-secondary-button>
    <x-button class="ml-2" wire:click="save">Antrag senden</x-button>
  </x-slot>
</x-dialog-modal>
