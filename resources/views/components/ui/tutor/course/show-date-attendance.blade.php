<div class="space-y-4">
  {{-- Kopfzeile: Day-Navigation (dein bestehender Block bleibt unverändert) --}}
  <div class="flex max-md:flex-wrap justify-between mb-4">
    <div class="flex space-x-3">
      <div class="flex items-stretch rounded-md border border-gray-200 shadow-sm overflow-hidden h-max w-max max-md:mb-4">
        @if($selectPreviousDayPossible)
          <button type="button" wire:click="selectPreviousDay"
                  class="px-4 py-2 text-sm text-white bg-blue-400 hover:bg-blue-700">
            <svg class="h-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 8 14">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 1 1.3 6.326a.91.91 0 0 0 0 1.348L7 13"/>
            </svg>
          </button>
        @endif

        <span class="bg-blue-200 text-blue-800 text-lg font-medium px-2.5 py-0.5">
          {{ $selectedDay?->date?->format('d.m.Y') ?? '–' }}
        </span>

        @if($selectNextDayPossible)
          <button type="button" wire:click="selectNextDay"
                  class="px-4 py-2 bg-blue-400 text-sm text-white hover:bg-blue-700">
            <svg class="h-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 8 14">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="m1 13 5.7-5.326a.909.909 0 0 0 0-1.348L1 1"/>
            </svg>
          </button>
        @endif
      </div>

      <div>
        <button type="button"
                class="text-sm text-gray-500 border rounded-md px-1 py-1 bg-white shadow-sm"
                :class="{
                  'hover:bg-green-100 hover:text-green-300': !showSelectDayCalendar,
                  'hover:bg-red-100 hover:text-red-300': showSelectDayCalendar
                }"
                @click="showSelectDayCalendar = !showSelectDayCalendar">
          <svg class="h-6 w-6 inline-block" fill="currentColor" viewBox="0 0 640 640" xmlns="http://www.w3.org/2000/svg">
            <path d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM160 304L160 336C160 344.8 167.2 352 176 352L208 352C216.8 352 224 344.8 224 336L224 304C224 295.2 216.8 288 208 288L176 288C167.2 288 160 295.2 160 304zM288 304L288 336C288 344.8 295.2 352 304 352L336 352C344.8 352 352 344.8 352 336L352 304C352 295.2 344.8 288 336 288L304 288C295.2 288 288 295.2 288 304zM432 288C423.2 288 416 295.2 416 304L416 336C416 344.8 423.2 352 432 352L464 352C472.8 352 480 344.8 480 336L480 304C480 295.2 472.8 288 464 288L432 288zM160 432L160 464C160 472.8 167.2 480 176 480L208 480C216.8 480 224 472.8 224 464L224 432C224 423.2 216.8 416 208 416L176 416C167.2 416 160 423.2 160 432zM304 416C295.2 416 288 423.2 288 432L288 464C288 472.8 295.2 480 304 480L336 480C344.8 480 352 472.8 352 464L352 432C352 423.2 344.8 416 336 416L304 416zM416 432L416 464C416 472.8 423.2 480 432 480L464 480C472.8 480 480 472.8 480 464L480 432C480 423.2 472.8 416 464 416L432 416C423.2 416 416 423.2 416 432z"/>
          </svg>
        </button>
      </div>
    </div>
  </div>

  {{-- Stats + Bulk-Aktionen --}}
  <div class="flex flex-wrap justify-between items-center gap-2">
    @if($selectedDay)
      <div class="flex flex-wrap gap-2 text-xs">
        <span class="inline-flex items-center rounded bg-green-100 text-green-800 px-2 py-0.5">Anwesend: {{ $stats['present'] }}</span>
        <span class="inline-flex items-center rounded bg-yellow-100 text-yellow-800 px-2 py-0.5">Verspätet: {{ $stats['late'] }}</span>
        <span class="inline-flex items-center rounded bg-blue-100 text-blue-800 px-2 py-0.5">Entschuldigt: {{ $stats['excused'] }}</span>
        <span class="inline-flex items-center rounded bg-red-100 text-red-800 px-2 py-0.5">Fehlend: {{ $stats['absent'] }}</span>
        <span class="inline-flex items-center rounded bg-gray-100 text-gray-800 px-2 py-0.5">Gesamt: {{ $stats['total'] }}</span>
      </div>
    @endif

    <div class="flex items-center gap-2">
      <x-dropdown>
        <x-slot name="trigger">
          <button class="px-2 py-1 rounded border text-xs">Weitere Aktionen</button>
        </x-slot>
        <x-slot name="content">
          <x-dropdown-link wire:click="bulk('all_present')">Alle anwesend</x-dropdown-link>
          <x-dropdown-link wire:click="bulk('all_excused')">Alle entschuldigt</x-dropdown-link>
          <x-dropdown-link wire:click="bulk('all_absent')">Alle fehlend</x-dropdown-link>
          <x-dropdown-link wire:click="bulk('checkin_all')">Check-in alle</x-dropdown-link>
          <x-dropdown-link wire:click="bulk('checkout_all')">Check-out alle</x-dropdown-link>
        </x-slot>
      </x-dropdown>
    </div>
  </div>

  {{-- Tabelle: pro Tag alle TN mit Aktionen --}}
  <div class="overflow-x-auto border rounded bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left">
            <button wire:click="sort('name')" class="font-semibold">Teilnehmer</button>
          </th>
          <th class="px-4 py-2">Zeiten</th>
          <th class="px-4 py-2">Aktionen</th>
          <th class="px-4 py-2 text-right">Status</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-gray-100">
        @forelse($rows as $r)
          @php
            $d = $r['data'];
            $fmt = fn($t) => $t ? \Illuminate\Support\Str::substr($t, 11, 5) : '—';
            $touched = ($d['present'] === true) || ($d['excused'] === true)
                    || (($d['late_minutes'] ?? 0) > 0) || (($d['left_early_minutes'] ?? 0) > 0)
                    || !empty($d['in']) || !empty($d['out']);

            $statusLabel = 'Fehlend';
            $badge = 'bg-red-100 text-red-700';
            if (!$touched) { $statusLabel = 'Unbekannt'; $badge = 'bg-gray-100 text-gray-700'; }
            elseif ($d['excused']) { $statusLabel = 'Entschuldigt'; $badge = 'bg-blue-100 text-blue-800'; }
            elseif ($d['present'] && ($d['late_minutes'] ?? 0) > 0) { $statusLabel = 'Verspätet'; $badge = 'bg-yellow-100 text-yellow-800'; }
            elseif ($d['present']) { $statusLabel = 'Anwesend'; $badge = 'bg-green-100 text-green-700'; }
          @endphp

          <tr x-data="{ lateOpen:false, noteOpen:false }" class="hover:bg-gray-50">
            {{-- Teilnehmer --}}
            <td class="px-4 py-2">
              @if($r['user'])
                <x-user.public-info :person="$r['user']" />
              @else
                <div class="font-medium">Teilnehmer #{{ $r['id'] }}</div>
              @endif
            </td>

            {{-- Zeiten --}}
            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">
              in: {{ $fmt($d['in'] ?? null) }} / out: {{ $fmt($d['out'] ?? null) }}
              <div class="text-xs text-gray-500">
                +{{ (int)($d['late_minutes'] ?? 0) }} min spät • {{ (int)($d['left_early_minutes'] ?? 0) }} min früher
              </div>
            </td>

            {{-- Aktionen --}}
            <td class="px-4 py-2">
              <div class="inline-flex items-center gap-1">
                {{-- Check-in (berechnet Verspätung ggü. Slot 1) --}}
                <button
                  class="inline-flex items-center justify-center w-8 h-8 rounded border border-green-600 text-green-700 hover:bg-green-50"
                  title="Anwesend (Check-in + Verspätung berechnen)"
                  wire:click.stop="checkInNow({{ $r['id'] }})">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </button>

                {{-- Abwesend (falls vorher anwesend -> Checkout + Frühweg) --}}
                <button
                  class="inline-flex items-center justify-center w-8 h-8 rounded border border-red-600 text-red-700 hover:bg-red-50"
                  title="Abwesend (Check-out + ggf. Früh-weg berechnen)"
                  wire:click.stop="markAbsentNow({{ $r['id'] }})">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>

                {{-- Nur Check-out --}}
                <button
                  class="inline-flex items-center justify-center w-8 h-8 rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                  title="Nur Check-out"
                  wire:click.stop="checkOutNow({{ $r['id'] }})">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H7a2 2 0 01-2-2v-1m8-10V5a2 2 0 00-2-2H7a2 2 0 00-2 2v1" />
                  </svg>
                </button>

                {{-- Verspätung/Frühweg bearbeiten (Popover) --}}
                <div class="relative">
                  <button
                    class="inline-flex items-center justify-center w-8 h-8 rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                    title="Verspätung / Früh weg eintragen"
                    @click="lateOpen = !lateOpen">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </button>
                  <div x-cloak x-show="lateOpen" @click.outside="lateOpen=false"
                       class="absolute z-10 mt-2 w-64 rounded border border-gray-300 bg-white p-3 shadow">
                    <div class="space-y-2">
                      <label class="block text-xs text-gray-600">Verspätung (Minuten)</label>
                      <input type="number" min="0" class="w-full rounded border-gray-300"
                             value="{{ (int)($d['late_minutes'] ?? 0) }}"
                             wire:change="setLateMinutes({{ $r['id'] }}, $event.target.value)" />
                      <label class="block text-xs text-gray-600">Früh weg (Minuten)</label>
                      <input type="number" min="0" class="w-full rounded border-gray-300"
                             value="{{ (int)($d['left_early_minutes'] ?? 0) }}"
                             wire:change="setLeftEarlyMinutes({{ $r['id'] }}, $event.target.value)" />
                      <div class="flex justify-end">
                        <button class="text-xs text-gray-600 underline" @click="lateOpen=false">Schließen</button>
                      </div>
                    </div>
                  </div>
                </div>

                {{-- Notiz bearbeiten (Popover) --}}
                <div class="relative">
                  <button
                    class="inline-flex items-center justify-center w-8 h-8 rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                    title="Notiz hinzufügen"
                    @click="noteOpen = !noteOpen">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                  </button>
                  <div x-cloak x-show="noteOpen" @click.outside="noteOpen=false"
                       class="absolute z-10 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow">
                    <label class="block text-xs text-gray-600 mb-1">Notiz</label>
                    <textarea rows="3" class="w-full rounded border-gray-300"
                              wire:change="setNote({{ $r['id'] }}, $event.target.value)">{{ $d['note'] }}</textarea>
                    <div class="mt-2 flex justify-end">
                      <button class="text-xs text-gray-600 underline" @click="noteOpen=false">Schließen</button>
                    </div>
                  </div>
                </div>
              </div>
            </td>

            {{-- Status --}}
            <td class="px-4 py-2 text-right">
              <span class="inline-flex rounded px-2 py-0.5 text-xs {{ $badge }}">{{ $statusLabel }}</span>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="p-6 text-center text-gray-500">Keine Einträge.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @unless($selectedDay)
    <div class="rounded border border-amber-300 bg-amber-50 text-amber-800 p-3 text-sm">
      Bitte zuerst einen Kurstag auswählen.
    </div>
  @endunless
</div>
