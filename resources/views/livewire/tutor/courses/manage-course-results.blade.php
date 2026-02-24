<div
    class="space-y-4 transition-opacity duration-300 pt-6"
    wire:loading.class=""
    x-data="{
        hoveredPersonId: null,
        hoverTimer: null,
        setHover(personId) {
            clearTimeout(this.hoverTimer);
            this.hoverTimer = setTimeout(() => {
                this.hoveredPersonId = String(personId);
            }, 300);
        },
        clearHover(personId) {
            clearTimeout(this.hoverTimer);
            if (String(this.hoveredPersonId) === String(personId)) {
                this.hoveredPersonId = null;
            }
        },
        isHovered(personId) {
            return String(this.hoveredPersonId) === String(personId);
        }
    }"
>
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
                          class="flex items-center justify-end gap-2 relative"
                          wire:target="saveOne('{{ $personId }}')"
                          wire:loading.class="opacity-60"
                          x-on:mouseenter="setHover('{{ $personId }}')"
                          x-on:mouseleave="clearHover('{{ $personId }}')"
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
                          @php
                              $statusMap = [
                                  'V' => 'Betrugsversuch',
                                  '+' => 'Teilgenommen',
                                  '-' => 'Nicht teilgenommen',
                              ];
                              $currentStatus = $statuses[$personId] ?? '';
                              $currentStatusLabel = $statusMap[$currentStatus] ?? 'Status wählen';
                              $statusBadgeClass = match ($currentStatus) {
                                  'V' => 'bg-red-50 text-red-700 border-red-200',
                                  '-' => 'bg-amber-50 text-amber-700 border-amber-200',
                                  '+' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                  default => 'bg-gray-50 text-gray-700 border-gray-200',
                              };
                          @endphp
                          <x-ui.dropdown.anchor-dropdown
                              align="left"
                              width="auto"
                              dropdownClasses="mt-2 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
                              contentClasses="bg-white"
                              :overlay="false"
                              :trap="false"
                              :offset="6"
                          >
                              <x-slot name="trigger">
                                  <button
                                      type="button"
                                      class="inline-flex w-44 items-center justify-between gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold {{ $statusBadgeClass }} transition"
                                      wire:loading.attr="disabled"
                                      wire:loading.class="cursor-wait opacity-70"
                                      wire:target="saveOne('{{ $personId }}'), setStatus('{{ $personId }}', 'V'), setStatus('{{ $personId }}', '+'), setStatus('{{ $personId }}', '-')"
                                  >
                                      <span class="truncate text-left">{{ $currentStatusLabel }}</span>
                                      <i wire:loading.remove wire:target="setStatus('{{ $personId }}', 'V'), setStatus('{{ $personId }}', '+'), setStatus('{{ $personId }}', '-')" class="fal fa-angle-down text-[10px]"></i>
                                      <span wire:loading wire:target="setStatus('{{ $personId }}', 'V'), setStatus('{{ $personId }}', '+'), setStatus('{{ $personId }}', '-')" class="loader2 w-3 h-3"></span>
                                  </button>
                              </x-slot>
                              <x-slot name="content">
                                  <div class="py-1 text-sm" wire:loading.class="pointer-events-none opacity-70" wire:target="setStatus('{{ $personId }}', 'V'), setStatus('{{ $personId }}', '+'), setStatus('{{ $personId }}', '-')">
                                      <button type="button" wire:click="setStatus('{{ $personId }}', '+')" wire:loading.attr="disabled" class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50 text-left disabled:cursor-wait">
                                          <span class="inline-block h-2 w-2 rounded-full aspect-square bg-emerald-500"></span>
                                          <span>An Prüfung teilgenommen</span>
                                      </button>
                                      <button type="button" wire:click="setStatus('{{ $personId }}', '-')" wire:loading.attr="disabled" class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50 text-left disabled:cursor-wait">
                                          <span class="inline-block h-2 w-2 rounded-full aspect-square bg-amber-500"></span>
                                          <span>Nicht an Prüfung teilgenommen</span>
                                      </button>
                                      <button type="button" wire:click="setStatus('{{ $personId }}', 'V')" wire:loading.attr="disabled" class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50 text-left disabled:cursor-wait">
                                          <span class="inline-block h-2 w-2 rounded-full aspect-square bg-red-500"></span>
                                          <span>0 Punkte wegen versuchtem Betruges</span>
                                      </button>
                                  </div>
                              </x-slot>
                          </x-ui.dropdown.anchor-dropdown>
                          {{-- Ergebnis-Input --}}
                          @php($disableResultInput = in_array(($statuses[$personId] ?? null), ['-', 'V'], true))
                          @php($hasEntryToDelete = !empty($statuses[$personId]) || ($results[$personId] ?? null) !== null && (string) ($results[$personId] ?? '') !== '')
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
                              class="flex-1 rounded-md border border-gray-300 px-2 max-w-20 text-center disabled:opacity-50 disabled:cursor-not-allowed"
                              @disabled($disableResultInput)
                              wire:loading.attr="disabled"
                              wire:target="saveOne('{{ $personId }}')"
                          />
                          <div x-show="isHovered('{{ $personId }}') && @js($hasEntryToDelete)" x-transition class="absolute -top-2 -right-2 ">
                            <button 
                                type="button" 
                                wire:click="clearResult('{{ $personId }}')" 
                                wire:loading.attr="disabled" 
                                class="text-red-400 hover:text-red-600 transition disabled:cursor-wait aspect-square border border-red-200 hover:border-red-300 rounded-full px-1.5 py-0.5 bg-white hover:bg-red-50/70"
                            >
                                <i class="fal fa-trash-alt text-xs aspect-square"></i>
                            </button>
                          </div>
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

