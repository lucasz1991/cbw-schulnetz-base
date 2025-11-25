<div class="space-y-4 transition-opacity duration-300  pt-6" wire:loading.class="opacity-30" x-data>
    <x-ui.forms.toggle-button 
        model="isExternalExam"
        label="{{ $isExternalExam ? 'Externe Prüfung aktiviert' : 'Interne Prüfung' }}"
    />

    @if($isExternalExam)

        <x-alert type="info" class="!mb-0">
            <strong>Externe Prüfung:</strong>
              <br>
            Die Ergebnisse für externe Prüfungen können nur durch die Institutsverwaltung eingetragen werden.
        </x-alert>
    @else
        {{-- Content for internal exam --}}
        <div class="flex max-md:flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <div class="relative">
              <input type="text" wire:model.debounce.400ms="search" placeholder="Teilnehmer suchen…" class="rounded-md border-gray-300 pr-8"/>
              <svg class="w-4 h-4 absolute right-2 top-1/2 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/>
              </svg>
            </div>

            <button type="button" wire:click="saveAll"
              class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 bg-blue-600 text-white hover:bg-blue-700">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
              Alle speichern
            </button>
          </div>
        </div>

        <div class="border rounded bg-white">
          <table class="min-w-full text-sm table-fixed">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left w-2/3">
                  <button type="button" wire:click="sort('name')" class="flex items-center gap-1 font-semibold group">
                    Teilnehmer
                    <svg class="w-3 h-3 text-gray-400 group-hover:text-gray-600 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 8 5 5 5-5"/>
                    </svg>
                  </button>
                </th>
                <th class="px-4 py-2 text-left">Ergebnis</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
              @forelse($rows as $personId => $r)
                <tr class="hover:bg-gray-50" wire:key="row-{{ $personId }}">
                  <td class="px-4 py-2">
                    @if(!empty($r['user']))
                      <x-user.public-info :person="$r['user']" />
                    @else
                      <div class="font-medium">{{ $r['name'] }}</div>
                      <div class="text-xs text-gray-500">#{{ $personId }}</div>
                    @endif
                  </td>
                  <td class="px-4 py-2 flex items-stretch gap-2">


                      {{-- Ergebnis-Input --}}
                      <input type="number"
                            min="0"
                            max="100"
                            wire:model.defer="results.{{ $personId }}"
                            wire:blur="saveOne('{{ $personId }}')"
                            class="flex-1 rounded-md border border-gray-300 px-2"/>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="2" class="p-6 text-center text-gray-500">Keine Teilnehmer gefunden.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
    @endif
</div>
