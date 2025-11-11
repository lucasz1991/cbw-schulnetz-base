<x-dialog-modal wire:model="showModal" maxWidth="2xl">
  <x-slot name="title">Antrag auf Nachprüfung</x-slot>

  <x-slot name="content">
    <form x-data="{}" class="space-y-8" wire:submit.prevent="save">

      {{-- Person + Klasse --}}
      <div class="grid md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <x-ui.forms.label value="Person"/>
          <p class="text-gray-700 font-semibold">
            {{ auth()->user()?->person ? (auth()->user()?->person?->vorname.' '.auth()->user()?->person?->nachname) : auth()->user()->name }}
          </p>
          <p class="text-gray-500 text-xs">
            {{ auth()->user()?->person?->person_id ?: 'keine person Id vorhanden' }}
          </p>
        </div>

        <div>
          <x-ui.forms.label for="klasse" value="Klasse"/>
          <x-ui.forms.input
            id="klasse"
            type="text"
            maxlength="12"
            placeholder="z. B. INF23A"
            wire:model.live.debounce.300ms="klasse"
          />
          <x-ui.forms.input-error for="klasse"/>
        </div>
      </div>

      {{-- Art der Nachprüfung --}}
      <div>
        <div class="mb-2 font-semibold">Ich beantrage gemäß Qualifizierungsordnung:</div>

<x-ui.forms.radio-btn-group aria-label="Art der Nachprüfung" breakpoint="md">
  <x-ui.forms.radio-btn-group-item
    name="wiederholung"
    label="Nach-/Wiederholungsprüfung (20,00 €)"
    value="wiederholung_1"
    wire:model="wiederholung"
    icon="fa-redo"
    iconStyle="fas"
  />
  <x-ui.forms.radio-btn-group-item
    name="wiederholung"
    label="Ergebnisverbesserung (40,00 €)"
    value="wiederholung_2"
    wire:model="wiederholung"
    icon="fa-chart-line"
    iconStyle="fas"
  />
</x-ui.forms.radio-btn-group>




        <x-ui.forms.input-error for="wiederholung"/>
      </div>

      {{-- Termin & Baustein --}}
      <div class="grid md:grid-cols-2 gap-6">
        <div>
          <x-ui.forms.label for="nachKlTermin" value="Nachprüfungstermin"/>
          <select
            id="nachKlTermin"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
            wire:model.defer="nachKlTermin"
          >
            <option value="">Bitte Termin wählen</option>
            <option value="1749208500">06.06.2025 – 13:15</option>
            <option value="1750411800">20.06.2025 – 11:30</option>
            <option value="1751621400">04.07.2025 – 11:30</option>
            <option value="1752831000">18.07.2025 – 11:30</option>
            <option value="1754040600">01.08.2025 – 11:30</option>
          </select>
          <x-ui.forms.input-error for="nachKlTermin"/>
        </div>

        <div>
          <x-ui.forms.label for="nKlBaust" value="Baustein"/>
          <x-ui.forms.input
            id="nKlBaust"
            type="text"
            maxlength="10"
            placeholder="z. B. PRG101"
            wire:model.defer="nKlBaust"
          />
          <x-ui.forms.input-error for="nKlBaust"/>
        </div>
      </div>

      {{-- Dozent & ursprüngliche Prüfung --}}
      <div class="grid md:grid-cols-2 gap-6">
        <div>
          <x-ui.forms.label for="nKlDozent" value="Instruktor / Dozent"/>
          <x-ui.forms.input
            id="nKlDozent"
            type="text"
            placeholder="Dozent"
            wire:model.defer="nKlDozent"
          />
          <x-ui.forms.input-error for="nKlDozent"/>
        </div>

        <div>
          <x-ui.forms.date-input
            id="nKlOrig"
            model="nKlOrig"
            label="Ursprüngliche Prüfung am"
            :inline="true"
            :altInput="true"
            dateFormat="Y-m-d"
            altFormat="d.m.Y"
            placeholder="Datum wählen …"
            required
          />
          <x-ui.forms.input-error for="nKlOrig"/>
        </div>
      </div>

      {{-- Begründung --}}
      <div>
        <div class="mb-2 font-semibold">Begründung</div>

        <x-ui.forms.radio-btn-group aria-label="Begründung" breakpoint="md">
          <x-ui.forms.radio-btn-group-item
            label="Ursprüngliche Prüfung unter 51 Punkte"
            value="unter51"
            wire:model="grund"
            icon="fa-tachometer-alt"
            iconStyle="fas"
          />
          <x-ui.forms.radio-btn-group-item
            label="Krankheit am Prüfungstag (mit Attest)"
            value="krankMitAtest"
            wire:model="grund"
            icon="fa-file-medical"
            iconStyle="fas"
          />
          <x-ui.forms.radio-btn-group-item
            label="Krankheit am Prüfungstag (ohne Attest)"
            value="krankOhneAtest"
            wire:model="grund"
            icon="fa-user-injured"
            iconStyle="fas"
          />
        </x-ui.forms.radio-btn-group>

        <x-ui.forms.input-error for="grund"/>

        <div class="mt-3 text-sm text-gray-600 space-y-1">
          <p>(*) Eine Nachprüfung ist kostenfrei, wenn ein Attest für den ursprünglichen Prüfungstag vorliegt.</p>
          <p>(**) Die Gebühr ist mit Abgabe der Anmeldung vor dem Nachprüfungstermin zu entrichten.</p>
        </div>
      </div>

      {{-- Upload --}}
      <div class="pt-2 border-t">
        <x-ui.forms.label value="Anlagen (jpg, png, gif, pdf)"/>
        <x-ui.filepool.drop-zone
          :model="'examRegistrationAttachments'"
          acceptedFiles=".jpg,.jpeg,.png,.gif,.pdf"
          maxFiles="3"
        />
        <x-ui.forms.input-error for="examRegistrationAttachments.*"/>
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
