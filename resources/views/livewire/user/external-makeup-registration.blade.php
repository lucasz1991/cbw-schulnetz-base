<x-modal.modal wire:model="showModal" maxWidth="2xl">
  <x-slot name="title">Antrag auf externe Nachprüfung</x-slot>

  <x-slot name="content">
    <form class="space-y-6">
      {{-- Kopfzeile --}}
      <div class="grid md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <x-ui.forms.label value="Person"/>
          <p class="text-gray-700 font-semibold">{{ auth()->user()?->person ? ( auth()->user()?->person?->vorname.' '.auth()->user()?->person?->nachname ) :  auth()->user()->name }}</p>
          <p class="text-gray-500 text-xs">{{ auth()->user()?->person?->person_id ?  auth()->user()?->person?->person_id :  'keine person Id vorhanden' }}</p>
        </div>
        <div class="text-right">
          <label for="klasse" class="block text-sm font-medium">Klasse</label>
          <input id="klasse" type="text" maxlength="12" placeholder="z. B. INF23A"
                 class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
                 wire:model.defer="klasse">
        </div>
      </div>

      {{-- Zertifizierungsauswahl --}}
      <div>
        <label for="cert" class="block text-sm font-medium">Zertifizierung</label>
        <select id="cert"
                class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
                wire:model.live="certification_key">
          <option value="">– Bitte wählen –</option>
          @foreach($certOptions as $opt)
            <option value="{{ $opt['key'] }}">
              {{ $opt['price'] }} | {{ $opt['label'] }}
            </option>
          @endforeach
        </select>
        @if($certification_label)
          <p class="text-xs text-gray-500 mt-1">Ausgewählt: {{ $certification_label }}</p>
        @endif
      </div>

      {{-- Termin + Begründung --}}
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <x-ui.forms.label for="scheduled_at" value="Prüfungstermin (ab {{ $minimumDateLabel }})" />
          <select
            id="scheduled_at"
            class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
            wire:model.defer="scheduled_at"
            required
          >
            <option value="">- Bitte wählen -</option>
            @forelse($examSlots as $slot)
              <option value="{{ $slot['timestamp'] }}">{{ $slot['label'] }}</option>
            @empty
              <option value="" disabled>Keine passenden internen Prüfungstermine verfügbar</option>
            @endforelse
          </select>
          @error('scheduled_at')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div>
          <p class="font-semibold mb-2">Begründung der Nachprüfung</p>
          <div class="space-y-2">
            <label class="flex items-center">
              <input type="radio" class="mr-2" value="zert_faild"
                     :checked="$wire.reason==='zert_faild'"
                     @click="$wire.reason='zert_faild'">
              ursprüngliche Prüfung nicht bestanden
            </label>
            <label class="flex items-center">
              <input type="radio" class="mr-2" value="krankMitAtest"
                     :checked="$wire.reason==='krankMitAtest'"
                     @click="$wire.reason='krankMitAtest'">
              Krankheit am Prüfungstag, <b>mit Attest</b>
            </label>
            <label class="flex items-center">
              <input type="radio" class="mr-2" value="krankOhneAtest"
                     :checked="$wire.reason==='krankOhneAtest'"
                     @click="$wire.reason='krankOhneAtest'">
              Krankheit am Prüfungstag, <b>ohne Attest</b>
            </label>
          </div>
        </div>
      </div>

      {{-- Optionale private E-Mail --}}
      <div>
        <label for="email_priv" class="block text-sm font-medium">Private E-Mail (optional)</label>
        <input id="email_priv" type="email" placeholder="name@example.com"
               class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
               wire:model.defer="email_priv">
      </div>

      {{-- Upload-Platzhalter (später zu Livewire Upload migrieren) --}}
      <div class="border-t pt-4">
        <label class="block text-sm font-medium mb-2">Anlage (jpg, png, gif, pdf)</label>
        <x-ui.filepool.drop-zone
          :model="'exam_registration_attachments'"
          acceptedFiles=".jpg,.jpeg,.png,.gif,.pdf"
        />
      </div>

      {{-- Hinweise --}}
      <div class="prose text-sm text-gray-600">
        <p>Die evtl. anfallende Gebühr ist mit Abgabe dieser Anmeldung vor der Prüfung zu entrichten.</p>
        <p class="italic">Bei externen Prüfungen kann es in Einzelfällen zu Verzögerungen kommen (technische Gründe beim Prüfungsanbieter).</p>
      </div>
    </form>
  </x-slot>

  <x-slot name="footer">
    <x-secondary-button wire:click="close">Schließen</x-secondary-button>
    <x-button class="ml-2" wire:click="save">Antrag senden</x-button>
  </x-slot>
</x-modal.modal>
