<div class="space-y-4 transition-opacity duration-300  pt-6" wire:loading.class="opacity-30" x-data>
    <x-ui.forms.toggle-button 
        model="isExternalExam"
        label="Keine Klausurpflicht"
    />

    @if($isExternalExam)
        <x-alert type="info" class="!mb-0">
            <strong>Keine Klausurpflicht:</strong>
              <br>
           Für diesen Baustein besteht keine Klausurpflicht.
        </x-alert>
    @else 


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
