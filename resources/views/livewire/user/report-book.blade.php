<div
  x-data="{
    date: @entangle('date'),
    init(){
      // falls du deine Flatpickr/Date-Komponente hast, hier anbinden
      // sonst normales <input type='date'> nutzen
    } 
  }"
  class="grid md:grid-cols-3 gap-6"
>
  <div class="md:col-span-2 space-y-4">
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <x-ui.forms.label value="Datum"/>
        <input type="date"
               x-model="date"
               class="mt-1 w-full rounded-lg border-gray-300"
               max="{{ now()->toDateString() }}"
        />
      </div>
    </div>

    <div>
      <x-ui.forms.label value="Tages-Eintrag (ein Feld pro Tag)"/>
      <textarea
        wire:model.defer="text"
        rows="12"
        class="mt-1 w-full rounded-lg border-gray-300"
        placeholder="Was wurde heute gemacht? (Übernahme aus Dozenten-Dokumentation ist später erweiterbar)"
      ></textarea>
      <div class="mt-3 flex items-center gap-3">
        <x-ui.button.primary wire:click="save" wire:target="save" wire:loading.attr="disabled">
          Speichern
        </x-ui.button.primary>
        <span wire:loading wire:target="save" class="text-sm text-gray-500">Speichere …</span>
      </div>
    </div>
  </div>

  <aside class="space-y-3">
    <h3 class="text-sm font-semibold text-gray-700">Letzte Einträge</h3>
    <div class="space-y-2">
      @forelse($recent as $r)
        <div class="rounded-lg border border-gray-200 p-3 bg-white hover:bg-gray-50">
          <div class="text-xs font-semibold text-gray-700">{{ $r['date'] }}</div>
          <div class="text-xs text-gray-600">{{ $r['excerpt'] }}</div>
        </div>
      @empty
        <div class="text-sm text-gray-500">Noch keine Einträge.</div>
      @endforelse
    </div>
  </aside>
</div>
