<x-dialog-modal wire:model="showModal" maxWidth="2xl">
  <x-slot name="title">Antrag auf Nachprüfung</x-slot>

  <x-slot name="content">
    <form
      x-data="{ showDeleteUpload:false }"
      class="space-y-6"
      {{-- wire:submit.prevent="save" --}}
    >
      {{-- Versteckte Felder – später aus Backend/User ziehen --}}
      <input type="hidden" name="tn_name" value="Müstermann, Mäx">
      <input type="hidden" name="tn_nummer" value="0000007">
      <input type="hidden" name="institut" value="Köln">
      <input type="hidden" name="email" value="mm@muster.com">
      <input type="hidden" name="send_date" value="28.05.2025 - 06:03">

      <div class="grid md:grid-cols-3 gap-4">
        <div><strong>Name:</strong> Müstermann, Mäx</div>
        <div><strong>Nummer:</strong> 0000007</div>
        <div class="text-right">
          <label for="klasse" class="block text-sm font-medium">Klasse</label>
          <input id="klasse" type="text" required maxlength="8"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
                 wire:model.defer="klasse">
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
        </div>
        <div>
          <label for="nKlBaust" class="block text-sm font-medium">Baustein</label>
          <input id="nKlBaust" type="text" maxlength="6" required placeholder="Baustein"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm"
                 wire:model.defer="nKlBaust">
        </div>
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label for="nKlDozent" class="block text-sm font-medium">Instruktor / Dozent</label>
          <input id="nKlDozent" type="text" required placeholder="Dozent"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm"
                 wire:model.defer="nKlDozent">
        </div>
        <div>
          <label for="nKlOrig" class="block text-sm font-medium">Ursprüngliche Prüfung am</label>
          <input id="nKlOrig" type="date" required
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm"
                 wire:model.defer="nKlOrig">
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
      </div>

      <div class="border-t pt-4">
        <label class="block text-sm font-medium mb-2">Anlage (jpg, png, gif, pdf)</label>
        <div class="flex items-center space-x-4">
          <button type="button" class="upload-btn bg-gray-100 border px-4 py-2 rounded text-sm">Anlage hinzufügen</button>
          <span class="text-gray-500">Keine Anlage hinzugefügt</span>
          <span x-show="showDeleteUpload">
            <a href="#" class="text-red-600 text-sm">[ löschen ]</a>
          </span>
        </div>
      </div>

      <div class="prose text-sm text-gray-600">
        <p>(*) Eine Nachprüfung ist kostenfrei, wenn ein Attest für den ursprünglichen Prüfungstag vorliegt.</p>
        <p>(**) Die Gebühr ist mit Abgabe der Anmeldung vor dem Nachprüfungstermin zu entrichten.</p>
      </div>
    </form>
  </x-slot>

  <x-slot name="footer">
    <x-secondary-button wire:click="close">Schließen</x-secondary-button>
    {{-- <x-button class="ml-2" wire:click="save">Antrag senden</x-button> --}}
  </x-slot>
</x-dialog-modal>
