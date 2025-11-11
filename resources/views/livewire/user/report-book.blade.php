@php
  $editorKey = 'rb-editor-'.($reportBookId ?? 'x').'-'.$date; // nur falls du forciert re-mounten willst
@endphp

<div class="w-full">
  <div class="max-w-full grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
    {{-- Hauptbereich (links) --}}
    <div class="col-span-1 md:col-span-2 space-y-4 bg-white border border-gray-300 rounded-lg p-4 overflow-hidden md:mb-4">
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <x-ui.forms.label value="Titel"/>
          <x-ui.forms.input
            type="text"
            wire:model.live.defer="title"
            class="mt-1 w-full"
            placeholder="Kurzbeschreibung des Tages"
          />
        </div>
  
        <div class="flex items-start justify-end">
          <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border
            {{ $status === 1 ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-slate-700 border-slate-200' }}">
            Status: {{ $status === 1 ? 'Fertig' : 'Entwurf' }}
          </span>
        </div>
      </div>
  
      {{-- WYSIWYG-Editor (Toast) --}}
      <div>
        <x-ui.forms.label value="Tages-Eintrag"/>
  
        {{-- Optional: wenn du den Editor bei Datumwechsel hard neu initialisieren willst, gib den Key mit --}}
        <div wire:key="{{ $editorKey }}">
          <x-ui.editor.toast
            wireModel="text"
            height="28rem"
            placeholder="Was wurde heute gemacht? (Übernahme aus Dozenten-Dokumentation ist später erweiterbar)"
          />
        </div>
  
        <div class="mt-3 flex items-center flex-wrap gap-2">
          <x-buttons.button-basic
            wire:click="save"
            wire:target="save"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-70 cursor-wait"
          >
            Speichern
          </x-buttons.button-basic>
  
          <x-buttons.button-basic
            wire:click="submit"
            wire:target="submit"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-70 cursor-wait"
          >
            Fertigstellen
          </x-buttons.button-basic>
  
          <span wire:loading wire:target="save,submit" class="text-sm text-gray-500">Verarbeite …</span>
        </div>
      </div>
    </div>
  
    {{-- Sidebar (rechts) --}}
    <div class="space-y-3 ">
      <div class="bg-white border border-gray-300 rounded-lg p-4">
        <x-ui.forms.label value="Datum auswählen"/>
        <input
          type="date"
          wire:model.live="date"
          class="mt-1 w-full rounded-lg border-gray-300"
          max="{{ now()->toDateString() }}"
        />
      </div>
  
      <div class="bg-white border border-gray-300 rounded-lg p-4">
  
        <h3 class="text-sm font-semibold text-gray-700 mb-4">
          Letzte Einträge {{ $massnahmeId ? '— '.$massnahmeId : '' }}
        </h3>
    
        <div class="space-y-2">
          @forelse($recent as $r)
            @php
              $isActive = ($r['date'] === $date);
              $itemKey  = 'rb-recent-'.($reportBookId ?? 'x').'-'.$r['date'];
            @endphp
    
            <button
              type="button"
              wire:key="{{ $itemKey }}"
              wire:click="selectDate('{{ $r['date'] }}')"
              class="w-full text-left rounded-lg border p-3 bg-white hover:bg-gray-50 transition
                     {{ $isActive ? 'border-primary-300 ring-2 ring-primary-200' : 'border-gray-200' }}"
            >
              <div class="flex items-center justify-between">
                <div class="text-xs font-semibold {{ $isActive ? 'text-primary-700' : 'text-gray-700' }}">
                  {{ \Illuminate\Support\Carbon::parse($r['date'])->format('d.m.Y') }}
                </div>
                <span class="text-[10px] px-2 py-0.5 rounded border
                  {{ $r['status'] === 1 ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-slate-700 border-slate-200' }}">
                  {{ $r['status'] === 1 ? 'Fertig' : 'Entwurf' }}
                </span>
              </div>
    
              @if(!empty($r['title']))
                <div class="text-xs font-semibold text-gray-700 mt-1">{{ $r['title'] }}</div>
              @endif
              <div class="text-xs text-gray-600 mt-0.5 truncate">{{ $r['excerpt'] }}</div>
            </button>
          @empty
            <div class="text-sm text-gray-500">Noch keine Einträge.</div>
          @endforelse
        </div>
      </div>
  
    </div>
  </div>
</div>
