<div class="space-y-4 transition-opacity duration-300" wire:loading.class="opacity-30">
    {{-- kompakte Kopf-Stats (außerhalb des Modals) --}}
    <div class="flex rounded-md border border-gray-300 overflow-hidden w-max">
        @if($day)
            <div class="flex flex-wrap text-xs cursor-pointer" wire:click="$set('showManageAttendanceModal', true)">
                <span class="inline-flex items-center bg-green-100 text-green-800 px-2 py-0.5">
                     {{ $stats['present'] }}
                </span>
                <span class="inline-flex items-center bg-yellow-100 text-yellow-800 px-2 py-0.5">
                      {{ $stats['late'] }}
                </span>
                <span class="inline-flex items-center  bg-blue-100 text-blue-800 px-2 py-0.5">
                     {{ $stats['excused'] }}
                </span>
                <span class="inline-flex items-center  bg-red-100 text-red-800 px-2 py-0.5">
                    {{ $stats['absent'] }}
                </span>
                <span class="inline-flex items-center  bg-gray-100 text-gray-800 px-2 py-0.5">
                   {{ $stats['total'] }}
                </span>
            </div>
        @endif

        <button type="button"
            wire:click="$set('showManageAttendanceModal', true)"
            class="text-sm text-gray-600  p-2 bg-white hover:bg-gray-100 shadow-sm"
            title="Anwesenheit verwalten"
        >
            <svg class="w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7.75 4H19M7.75 4a2.25 2.25 0 0 1-4.5 0m4.5 0a2.25 2.25 0 0 0-4.5 0M1 4h2.25m13.5 6H19m-2.25 0a2.25 2.25 0 0 1-4.5 0m4.5 0a2.25 2.25 0 0 0-4.5 0M1 10h11.25m-4.5 6H19M7.75 16a2.25 2.25 0 0 1-4.5 0m4.5 0a2.25 2.25 0 0 0-4.5 0M1 16h2.25" />
            </svg>
        </button>
    </div>

    <x-dialog-modal wire:model="showManageAttendanceModal" max-width="4xl">
        <x-slot name="title">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold">Anwesenheit</h2>
                    <p class="text-sm text-gray-500">
                        @if($day)
                            <span class="bg-blue-200 text-blue-800 text-sm font-medium px-2.5 py-0.5 rounded">
                                {{ $day?->date?->format('d.m.Y') }}
                            </span>
                        @else
                            Kein Tag ausgewählt
                        @endif
                    </p>
                </div>

                {{-- Tagesstatus-Karten (Vormittag/Nachmittag) --}}
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <div class="flex items-center gap-2 rounded border px-2 py-1.5">
                        <div>
                            <div class="font-medium">Vormittag</div>
                            <div class="text-gray-500">
                                @if(($status['start'] ?? 0) > 0)
                                    erledigt ({{ $status['start'] }})
                                    • {{ \Illuminate\Support\Str::substr($status['updated_at'] ?? '', 11, 5) }}
                                @else
                                    offen
                                @endif
                            </div>
                        </div>
                        @if(($status['start'] ?? 0) > 0)
                            <button wire:click="undoStartDone" class="text-xs underline text-red-500">
                                <svg class="w-3 " fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M431.2 476.5L163.5 208.8C141.1 240.2 128 278.6 128 320C128 426 214 512 320 512C361.5 512 399.9 498.9 431.2 476.5zM476.5 431.2C498.9 399.8 512 361.4 512 320C512 214 426 128 320 128C278.5 128 240.1 141.1 208.8 163.5L476.5 431.2zM64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576C178.6 576 64 461.4 64 320z"/></svg>
                            </button>
                        @else
                            <button wire:click="markStartDone" class="text-xs underline text-green-500">
                                <svg class="w-3 " fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M530.8 134.1C545.1 144.5 548.3 164.5 537.9 178.8L281.9 530.8C276.4 538.4 267.9 543.1 258.5 543.9C249.1 544.7 240 541.2 233.4 534.6L105.4 406.6C92.9 394.1 92.9 373.8 105.4 361.3C117.9 348.8 138.2 348.8 150.7 361.3L252.2 462.8L486.2 141.1C496.6 126.8 516.6 123.6 530.9 134z"/></svg>
                            </button>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 rounded border px-2 py-1.5">
                        <div>
                            <div class="font-medium">Nachmittag</div>
                            <div class="text-gray-500">
                                @if(($status['end'] ?? 0) > 0)
                                    erledigt ({{ $status['end'] }})
                                    • {{ \Illuminate\Support\Str::substr($status['updated_at'] ?? '', 11, 5) }}
                                @else
                                    offen
                                @endif
                            </div>
                        </div>
                        @if(($status['end'] ?? 0) > 0)
                            <button wire:click="undoEndDone" class="text-xs underline text-red-500">
                                <svg class="w-3 " fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M431.2 476.5L163.5 208.8C141.1 240.2 128 278.6 128 320C128 426 214 512 320 512C361.5 512 399.9 498.9 431.2 476.5zM476.5 431.2C498.9 399.8 512 361.4 512 320C512 214 426 128 320 128C278.5 128 240.1 141.1 208.8 163.5L476.5 431.2zM64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576C178.6 576 64 461.4 64 320z"/></svg>
                            </button>
                        @else
                            <button wire:click="markEndDone" class="text-xs underline text-green-500">
                                <svg class="w-3 " fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M530.8 134.1C545.1 144.5 548.3 164.5 537.9 178.8L281.9 530.8C276.4 538.4 267.9 543.1 258.5 543.9C249.1 544.7 240 541.2 233.4 534.6L105.4 406.6C92.9 394.1 92.9 373.8 105.4 361.3C117.9 348.8 138.2 348.8 150.7 361.3L252.2 462.8L486.2 141.1C496.6 126.8 516.6 123.6 530.9 134z"/></svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </x-slot>

        <x-slot name="content">
            {{-- Bulk-Aktionen + Stats --}}
            <div class="flex flex-wrap justify-between items-center gap-2 mb-6 mt-8">
                @if($day)
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="inline-flex items-center rounded bg-green-100 text-green-800 px-2 py-0.5">
                            Anwesend: {{ $stats['present'] }}
                        </span>
                        <span class="inline-flex items-center rounded bg-yellow-100 text-yellow-800 px-2 py-0.5">
                            Verspätet: {{ $stats['late'] }}
                        </span>
                        <span class="inline-flex items-center rounded bg-blue-100 text-blue-800 px-2 py-0.5">
                            Entschuldigt: {{ $stats['excused'] }}
                        </span>
                        <span class="inline-flex items-center rounded bg-red-100 text-red-800 px-2 py-0.5">
                            Fehlend: {{ $stats['absent'] }}
                        </span>
                        <span class="inline-flex items-center rounded bg-gray-100 text-gray-800 px-2 py-0.5">
                            Gesamt: {{ $stats['total'] }}
                        </span>
                    </div>
                @endif
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
                        <x-dropdown-link wire:click="bulk('reset_all')">Zurücksetzen</x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </div>

<div class=" rounded border border-gray-200 bg-white scroll-container">
  <table class="min-w-full text-sm">
    <thead>
      <tr class="bg-gray-50 text-left">
        <th class="p-2 w-[28%]">Teilnehmer</th>
        <th class="p-2 w-[28%]">Aktionen</th>
        <th class="p-2 w-[10%] text-right">Status</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse($rows as $r)
        @php
        $d = $r['data'];
        $fmt = fn($t) => $t ? \Illuminate\Support\Str::substr($t, 11, 5) : '—';

        // Wurde diese Zeile schon "angefasst"?
        $touched = ($d['present'] === true)
                || ($d['excused'] === true)
                || (($d['late_minutes'] ?? 0) > 0)
                || (($d['left_early_minutes'] ?? 0) > 0)
                || !empty($d['in'])
                || !empty($d['out']);

        // Status + Badge
        $statusLabel = 'Fehlend';
        $badge = 'bg-red-100 text-red-700';

        if (!$touched) {
            $statusLabel = 'Unbekannt';
            $badge = 'bg-gray-100 text-gray-700';
        } elseif ($d['excused']) {
            $statusLabel = 'Entschuldigt';
            $badge = 'bg-blue-100 text-blue-800';
        } elseif ($d['present'] && ($d['late_minutes'] ?? 0) > 0) {
            $statusLabel = 'teilweise anwesend';
            $badge = 'bg-yellow-100 text-yellow-800';
        } elseif ($d['present']) {
            $statusLabel = 'Anwesend';
            $badge = 'bg-green-100 text-green-700';
        }
        @endphp


        <tr x-data="{ lateOpen:false, noteOpen:false }" class="hover:bg-gray-50">
          {{-- Teilnehmer --}}
          <td class="p-2">
            @if($r['user'])
              <x-user.public-info :person="$r['user']" />
            @else
              <div class="font-medium">Teilnehmer #{{ $r['id'] }}</div>
            @endif
          </td>

          {{-- Button-Group --}}
          <td class="p-2">
            <div class="inline-flex items-center gap-1">
              {{-- ✔️ Pünktlich anwesend / Check-in jetzt --}}
              <button
                class="inline-flex items-center justify-center w-8 h-8 rounded border border-green-600 text-green-700 hover:bg-green-50"
                title="Anwesend (Check-in + Verspätung berechnen)"
                wire:click.stop="checkInNow({{ $r['id'] }})"
              >
                {{-- Heroicon: check --}}
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M5 13l4 4L19 7" />
                </svg>
              </button>

              {{-- ❌ Abwesend / Check-out jetzt (falls anwesend) --}}
              <button
                class="inline-flex items-center justify-center w-8 h-8 rounded border border-red-600 text-red-700 hover:bg-red-50"
                title="Abwesend (Check-out + ggf. Früh-weg berechnen)"
                wire:click.stop="markAbsentNow({{ $r['id'] }})"
              >
                {{-- Heroicon: x --}}
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>

              {{-- 🕒 Verspätung / Früh-weg Editor (inline Popover) --}}
              <div class="relative">
                <button
                  class="inline-flex items-center justify-center w-8 h-8 rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                  title="Verspätung / Früh weg eintragen"
                  @click="lateOpen = !lateOpen"
                >
                  {{-- Heroicon: clock --}}
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </button>

                <div
                  x-cloak
                  x-show="lateOpen"
                  @click.outside="lateOpen=false"
                  class="absolute z-10 mt-2 w-64 rounded border border-gray-300 bg-white p-3 shadow"
                >
                  <div class="space-y-2">
                    <label class="block text-xs text-gray-600">Verspätung (Minuten)</label>
                    <input type="number" min="0" class="w-full rounded border-gray-300"
                           value="{{ $d['late_minutes'] }}"
                           wire:change="setLateMinutes({{ $r['id'] }}, $event.target.value)" />
                    <label class="block text-xs text-gray-600">Früh weg (Minuten)</label>
                    <input type="number" min="0" class="w-full rounded border-gray-300"
                           value="{{ $d['left_early_minutes'] }}"
                           wire:change="setLeftEarlyMinutes({{ $r['id'] }}, $event.target.value)" />
                    <div class="flex justify-end">
                      <button class="text-xs text-gray-600 underline" @click="lateOpen=false">Schließen</button>
                    </div>
                  </div>
                </div>
              </div>

              {{-- 📝 Notiz Editor (inline Popover) --}}
              <div class="relative">
                <button
                  class="inline-flex items-center justify-center w-8 h-8 rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                  title="Notiz hinzufügen"
                  @click="noteOpen = !noteOpen"
                >
                  {{-- Heroicon: pencil-square --}}
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z" />
                  </svg>
                </button>

                <div
                  x-cloak
                  x-show="noteOpen"
                  @click.outside="noteOpen=false"
                  class="absolute z-10 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow"
                >
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

          {{-- Status-Badge --}}
          <td class="p-2 text-right">
            <span class="inline-flex rounded px-2 py-0.5 text-xs {{ $badge }}">{{ $statusLabel }}</span>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="p-6 text-center text-gray-500">Keine Einträge.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

            @unless($day)
                <div class="rounded border border-amber-300 bg-amber-50 text-amber-800 p-3 text-sm mt-3">
                    Bitte zuerst einen Kurstag auswählen.
                </div>
            @endunless
        </x-slot>

        <x-slot name="footer">
            {{-- Speichern ist nicht nötig: alles speichert on-change. --}}
            <x-button wire:click="$set('showManageAttendanceModal', false)">Schließen</x-button>
        </x-slot>
    </x-dialog-modal>
</div>
