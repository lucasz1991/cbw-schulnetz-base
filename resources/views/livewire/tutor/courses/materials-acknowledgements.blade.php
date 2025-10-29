<div class="rounded-md border border-yellow-300 bg-yellow-50 p-4 mb-2">
  <div class="mb-6"> 
    <h2 class="text-base font-semibold text-gray-800 mb-2">Bildungsmittel-Bestätigungen</h2>
    <p class="text-sm text-gray-600">Hier finden Sie eine Übersicht über die Bestätigungen der Bildungsmittel für den Kurs der Teilnehmer.</p>
  </div>
  {{-- Kurzübersicht für den Direktblick --}}
  <div class="flex flex-wrap items-center gap-3">
    <span class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 bg-gray-50">
      <span class="text-xs uppercase text-gray-500">Gesamt</span>
      <span class="font-semibold">{{ $total }}</span>
    </span>

    <span class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 bg-emerald-50 border-emerald-200">
      <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
      <span class="text-xs uppercase text-emerald-700">Bestätigt</span>
      <span class="font-semibold text-emerald-800">{{ $ackCount }}</span>
    </span>

    <span class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 bg-amber-50 border-amber-200">
      <span class="w-2 h-2 rounded-full bg-amber-500"></span>
      <span class="text-xs uppercase text-amber-700">Offen</span>
      <span class="font-semibold text-amber-800">{{ $pendingCount }}</span>
    </span>

    <div class="ms-auto">
      <button type="button"
              wire:click="open"
              class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-1.5 text-white hover:bg-blue-700">
        <svg xmlns="http://www.w3.org/2000/svg"  class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Liste öffnen
      </button>
    </div>
  </div>

  {{-- Modal: vollständige Liste --}}
  <x-dialog-modal wire:model="openModal" :maxWidth="'4xl'">
    <x-slot name="title">
      Bildungsmittel-Bestätigungen ({{ $ackCount }}/{{ $total }})
    </x-slot>

    <x-slot name="content">
      <div class="mb-3 flex items-center gap-2">
        <input type="search"
               wire:model.live.debounce.300ms="search"
               placeholder="Teilnehmer suchen …"
               class="w-full rounded-md border-gray-300"/>
      </div>

      <div class="overflow-x-auto rounded-md border">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left font-medium text-gray-600">Name</th>
              <th class="px-4 py-2 text-left font-medium text-gray-600">E-Mail</th>
              <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
              <th class="px-4 py-2 text-left font-medium text-gray-600">Datum</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white">
            @forelse($filteredRows as $r)
              <tr>
                <td class="px-4 py-2">
                  <x-user.public-info :person="$r->person" />
                </td>
                <td class="px-4 py-2 text-gray-600">{{ $r->email }}</td>
                <td class="px-4 py-2">
                  @if($r->acknowledged)
                    <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs bg-emerald-100 text-emerald-800">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Bestätigt
                    </span>
                  @else
                    <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs bg-amber-100 text-amber-800">
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Offen
                    </span>
                  @endif
                </td>
                <td class="px-4 py-2 text-gray-600">
                  {{ $r->acknowledged_at ? $r->acknowledged_at->timezone('Europe/Berlin')->format('d.m.Y H:i') : '—' }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="px-4 py-6 text-center text-gray-500">Keine Teilnehmer gefunden.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </x-slot>

    <x-slot name="footer">
      <x-button wire:click="close">Schließen</x-button>
    </x-slot>
  </x-dialog-modal>
</div>
