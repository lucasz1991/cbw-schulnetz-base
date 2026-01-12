<div class="space-y-4 transition-opacity duration-300  pt-6" wire:loading.class="" x-data>
    <div class="w-max">
      <x-ui.forms.toggle-button 
          model="isExternalExam"
          label="Keine Klausurpflicht"
      />
    </div>
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
                <th class="px-4 py-2 text-right">Prüfungsergebnis</th>
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
                  <td class="px-4 py-2 text-right">
                      <div 
                          class="flex items-stretch justify-end gap-2 relative"
                          wire:target="saveOne('{{ $personId }}')"
                          wire:loading.class="opacity-60"
                      >
                          <div class="w-8 flex items-center">
                            {{-- Loader LINKS neben dem Input (nur für diese Person) --}}
                            <div 
                                wire:loading 
                                wire:target="saveOne('{{ $personId }}')" 
                                class="flex items-center"
                            >
                                <span class="loader2 w-4 h-4"></span>
                            </div>
                          </div>
                          {{-- Ergebnis-Input --}}
                          <input 
                              type="text"
                              x-data 
                              x-mask="999" 
                                  x-on:input="
                                  let v = $event.target.value.replace(/[^0-9]/g, '');
                                  if (v === '') { $event.target.value = ''; return; }
                                  let n = parseInt(v);
                                  if (n > 100) n = 100;
                                  $event.target.value = n;
                              "
                              wire:model.live.defer.200ms="results.{{ $personId }}"
                              placeholder="0–100"
                              wire:change="saveOne('{{ $personId }}')"
                              class="flex-1 rounded-md border border-gray-300 px-2 max-w-20 text-center"
                              wire:loading.attr="disabled"
                              wire:target="saveOne('{{ $personId }}')"
                          />
                      </div>
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
