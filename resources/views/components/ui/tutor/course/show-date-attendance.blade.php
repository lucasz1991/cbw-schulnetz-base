@props([
  'participants',
  'selectedDay',
  'stats',
  'rows',
  'sortBy',
  'sortDir',
  'selectPreviousDayPossible',
  'selectNextDayPossible',
  'plannedStart',
  'plannedEnd',
])

<div class="space-y-4 transition-opacity duration-300">
    <div class="flex max-md:flex-wrap items-center space-x-3 justify-between mb-8">
        <div class="flex justify-between items-center space-x-3 w-full">
            <div class="flex items-center gap-2">
                <div class="flex items-stretch rounded-md border border-gray-200 shadow-sm overflow-hidden h-max w-max max-md:mb-4">
                    @if($selectPreviousDayPossible)
                        <button
                            type="button"
                            wire:click="selectPreviousDay"
                            class="px-4 py-2 text-sm text-white bg-blue-400 hover:bg-blue-700"
                        >
                            <i class="fas fa-chevron-left text-white text-xs"></i>
                        </button>
                    @endif

                    <span class="bg-blue-200 text-blue-800 text-lg font-medium px-2.5 py-0.5">
                        {{ $selectedDay?->date?->format('d.m.Y') }}
                    </span>

                    @if($selectNextDayPossible)
                        <button
                            type="button"
                            wire:click="selectNextDay"
                            class="px-4 py-2 bg-blue-400 text-sm text-white hover:bg-blue-700"
                        >
                            <i class="fas fa-chevron-right text-white text-xs"></i>
                        </button>
                    @endif
                </div>

                @php
                    use Illuminate\Support\Carbon;
                    $isToday = $selectedDay?->date ? Carbon::parse($selectedDay->date)->isToday() : false;
                @endphp

                @if($isToday)
                    <div>
                        <span
                            class="h-max rounded-lg bg-green-100 border border-green-700 text-green-700 text-xs px-1.5 py-0.5 shadow"
                            title="Heutiger Tag"
                        >
                            Heute
                        </span>
                    </div>
                @endif
            </div>

            <div>
                <button
                    type="button"
                    @click="showSelectDayCalendar = !showSelectDayCalendar"
                    class="inline-flex items-center gap-2 text-sm border rounded-md px-2 py-1 bg-white shadow-sm transition"
                    :class="showSelectDayCalendar
                        ? 'hover:bg-blue-100 hover:text-gray-600 border-blue-200'
                        : 'hover:bg-blue-100 hover:text-blue-600 border-blue-200'">

                    <i class="far fa-calendar-alt text-gray-500"></i>

                    <span class="relative w-9 h-5 rounded-full transition-colors"
                          :class="showSelectDayCalendar ? 'bg-blue-600' : 'bg-gray-200'">
                        <span class="absolute top-[2px] left-[2px] h-4 w-4 rounded-full bg-white border border-gray-300 transition-transform"
                              :class="showSelectDayCalendar ? 'translate-x-4' : 'translate-x-0'"></span>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap justify-between items-center gap-2">
        @if($selectedDay)
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center rounded bg-green-100 text-green-800 px-2 py-0.5">Anwesend: {{ $stats['present'] }}</span>
                <span class="inline-flex items-center rounded bg-yellow-100 text-yellow-800 px-2 py-0.5">Teilweise anwesend: {{ $stats['late'] }}</span>
                <span class="inline-flex items-center rounded bg-blue-100 text-blue-800 px-2 py-0.5">Entschuldigt: {{ $stats['excused'] }}</span>
                <span class="inline-flex items-center rounded bg-red-100 text-red-800 px-2 py-0.5">Fehlend: {{ $stats['absent'] }}</span>
                <span class="inline-flex items-center rounded bg-gray-100 text-gray-800 px-2 py-0.5">Gesamt: {{ $stats['total'] }}</span>
            </div>
        @endif

        <div class="flex items-center gap-2">
            <x-dropdown>
                <x-slot name="trigger">
                    <button class="px-2 py-1 rounded border text-xs bg-white">Weitere Aktionen</button>
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

    <div class="border rounded bg-white">
        <table class="min-w-full text-sm table-fixed">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left w-1/3">
                        <button type="button" wire:click="sort('name')" class="flex items-center gap-1 font-semibold group">
                            Teilnehmer

                            @if($sortBy === 'name')
                                @if($sortDir === 'asc')
                                    <i class="fas fa-chevron-up text-blue-600 group-hover:text-blue-800 transition text-xs"></i>
                                @else
                                    <i class="fas fa-chevron-down text-blue-600 group-hover:text-blue-800 transition text-xs"></i>
                                @endif
                            @else
                                <i class="fas fa-chevron-down text-gray-400 group-hover:text-gray-600 transition text-xs"></i>
                            @endif
                        </button>
                    </th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $r)
                    @php
                        $d        = $r['data'];
                        $hasEntry = $r['hasEntry'] ?? false;
                        $late     = (int)($d['late_minutes'] ?? 0);
                        $early    = (int)($d['left_early_minutes'] ?? 0);

                        if (!$hasEntry) {
                            $statusLabel = 'Anwesend';
                            $badge = 'bg-green-100 text-green-700';
                        } elseif ($d['excused']) {
                            $statusLabel = 'Entschuldigt';
                            $badge = 'bg-blue-100 text-blue-800';
                        } elseif ($d['present'] && $late > 0) {
                            $statusLabel = 'Teilweise anwesend';
                            $badge = 'bg-yellow-100 text-yellow-800';
                        } elseif ($d['present']) {
                            $statusLabel = 'Anwesend';
                            $badge = 'bg-green-100 text-green-700';
                        } elseif (!$d['present']) {
                            $statusLabel = 'Fehlend';
                            $badge = 'bg-red-100 text-red-700';
                        } else {
                            $statusLabel = 'Unbekannt';
                            $badge = 'bg-red-100 text-red-700';
                        }

                        $isAbsent = ($r['hasEntry'] ?? false) && ($d['present'] === false) && !($d['excused'] ?? false);
                    @endphp

                    <tr x-data="{ lateOpen:false, noteOpen:false, arrive:'{{ $d['arrived_at'] ?? null }}', leave:'{{ $d['left_at'] ?? null }}' }"
                        class="hover:bg-gray-50"
                        wire:key="row-{{ $r['id'] }}"
                    >
                        <td class="px-1 md:px-4 py-2">
                            <div class="w-min md:w-max">
                                @if($r['user'])
                                    <x-user.public-info :person="$r['user']" />
                                @else
                                    <div class="font-medium">Teilnehmer #{{ $r['id'] }}</div>
                                @endif
                            </div>
                        </td>

                        <td class="px-1 md:px-4 py-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex rounded px-2 py-0.5 text-xs {{ $badge }}">
                                    {{ $statusLabel }}
                                </span>

                                @if($late > 0)
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs bg-yellow-100 text-yellow-800">
                                        +{{ $late }} min spät
                                    </span>
                                @endif

                                @if($early > 0)
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs bg-orange-100 text-orange-800">
                                        {{ $early }} min früher
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="px-1 md:px-4 py-2">
                            <div class="flex items-center justify-end gap-1 relative">

                                {{-- ✅ Loader links neben Buttons (1 Target pro Loader) --}}
                                <div class="w-8 flex items-center justify-center">
                                    <div wire:loading wire:target="markPresent({{ $r['id'] }})" class="flex items-center">
                                        <span class="loader2 w-4 h-4"></span>
                                    </div>
                                    <div wire:loading wire:target="markAbsent({{ $r['id'] }})" class="flex items-center">
                                        <span class="loader2 w-4 h-4"></span>
                                    </div>

                                    {{-- Wrapper: 1 Target für Time/Note --}}
                                    <div wire:loading wire:target="saveArrival({{ $r['id'] }})" class="flex items-center">
                                        <span class="loader2 w-4 h-4"></span>
                                    </div>
                                    <div wire:loading wire:target="saveLeave({{ $r['id'] }})" class="flex items-center">
                                        <span class="loader2 w-4 h-4"></span>
                                    </div>
                                    <div wire:loading wire:target="saveNote({{ $r['id'] }})" class="flex items-center">
                                        <span class="loader2 w-4 h-4"></span>
                                    </div>
                                </div>

                                {{-- Present/Absent (Buttons NICHT verändert) --}}
                                @if($isAbsent)
                                    <button
                                        class="inline-flex items-center justify-center w-8 h-8 rounded border border-green-600 text-green-700 hover:bg-green-50"
                                        title="Anwesend"
                                        wire:key="row-markpresentbutton-{{ $r['id'] }}"
                                        wire:click="markPresent({{ $r['id'] }})"
                                        wire:loading.class="pointer-events-none opacity-50 cursor-wait"
                                        wire:target="markPresent({{ $r['id'] }})"
                                    >
                                        <i class="fas fa-check text-sm"></i>
                                    </button>
                                @else
                                    <button
                                        class="inline-flex items-center justify-center w-8 h-8 rounded border border-red-600 text-red-700 hover:bg-red-50"
                                        title="Abwesend"
                                        wire:key="row-markabsentbutton-{{ $r['id'] }}"
                                        wire:click="markAbsent({{ $r['id'] }})"
                                        wire:loading.class="pointer-events-none opacity-50 cursor-wait"
                                        wire:target="markAbsent({{ $r['id'] }})"
                                    >
                                        <i class="fas fa-times text-sm"></i>
                                    </button>
                                @endif

                                {{-- Verspätung/Frühweg Popover (inkl. Schnellauswahl BEHALTEN) --}}
                                <div class="relative">
                                    <button
                                        class="relative inline-flex items-center justify-center w-8 h-8 rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                                        title="Verspätung / Früh weg eintragen"
                                        @click="lateOpen = !lateOpen"
                                    >
                                        <i class="far fa-clock text-sm"></i>
                                        @if(($d['arrived_at'] ?? null) || ($d['left_at'] ?? null))
                                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-300 border-2 border-white rounded-full"></span>
                                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-200  rounded-full animate-ping"></span>
                                        @endif
                                    </button>

                                    <div x-cloak x-show="lateOpen" @click.outside="lateOpen=false"
                                         class="absolute right-0 z-10 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow">
                                        <div class="space-y-4">

                                            {{-- Gekommen --}}
                                            <div>
                                                <label for="arrive-{{ $r['id'] }}" class="block mb-2 text-xs font-medium text-gray-600">Gekommen (Uhrzeit)</label>
                                                <div class="flex items-end gap-2">
                                                    <div class="relative flex-1">
                                                        <div class="absolute inset-y-0 end-0 top-0 flex items-center pe-3.5 pointer-events-none">
                                                            <i class="far fa-clock text-gray-500"></i>
                                                        </div>

                                                        <input
                                                            x-model="arrive"
                                                            type="time"
                                                            id="arrive-{{ $r['id'] }}"
                                                            class="bg-gray-50 border leading-none border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                                            min="{{ $plannedStart }}"
                                                            max="{{ $plannedEnd }}"
                                                            step="60"
                                                            wire:model.defer="arriveInput.{{ $r['id'] }}"
                                                            wire:change="saveArrival({{ $r['id'] }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="saveArrival({{ $r['id'] }})"
                                                        />
                                                    </div>

                                                    {{-- Schnellauswahl (BEHALTEN) --}}
                                                    <div class="w-28 shrink-0">
                                                        <label class="sr-only" for="arrive-quick-{{ $r['id'] }}">Schnellwahl</label>
                                                        <select
                                                            id="arrive-quick-{{ $r['id'] }}"
                                                            @change="
                                                                arrive = $event.target.value;
                                                                $wire.set('arriveInput.{{ $r['id'] }}', arrive);
                                                                $wire.saveArrival({{ $r['id'] }});
                                                            "
                                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                                            wire:loading.attr="disabled"
                                                            wire:target="saveArrival({{ $r['id'] }})"
                                                        >
                                                            <option value="">– wählen –</option>
                                                            <option value="{{ $plannedStart }}">{{ $plannedStart }}</option>
                                                            <option value="08:30">08:30</option>
                                                            <option value="09:00">09:00</option>
                                                            <option value="09:30">09:30</option>
                                                            <option value="10:00">10:00</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Gegangen --}}
                                            <div>
                                                <label for="leave-{{ $r['id'] }}" class="block mb-2 text-xs font-medium text-gray-600">Gegangen (Uhrzeit)</label>
                                                <div class="flex items-end gap-2">
                                                    <div class="relative flex-1">
                                                        <div class="absolute inset-y-0 end-0 top-0 flex items-center pe-3.5 pointer-events-none">
                                                            <i class="far fa-clock text-gray-500"></i>
                                                        </div>

                                                        <input
                                                            x-model="leave"
                                                            type="time"
                                                            id="leave-{{ $r['id'] }}"
                                                            class="bg-gray-50 border leading-none border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                                            min="{{ $plannedStart }}"
                                                            max="{{ $plannedEnd }}"
                                                            step="60"
                                                            wire:model.defer="leaveInput.{{ $r['id'] }}"
                                                            wire:change="saveLeave({{ $r['id'] }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="saveLeave({{ $r['id'] }})"
                                                        />
                                                    </div>

                                                    {{-- Schnellauswahl (BEHALTEN) --}}
                                                    <div class="w-28 shrink-0">
                                                        <label class="sr-only" for="leave-quick-{{ $r['id'] }}">Schnellwahl</label>
                                                        <select
                                                            id="leave-quick-{{ $r['id'] }}"
                                                            @change="
                                                                leave = $event.target.value;
                                                                $wire.set('leaveInput.{{ $r['id'] }}', leave);
                                                                $wire.saveLeave({{ $r['id'] }});
                                                            "
                                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                                            wire:loading.attr="disabled"
                                                            wire:target="saveLeave({{ $r['id'] }})"
                                                        >
                                                            <option value="">– wählen –</option>
                                                            <option value="{{ $plannedEnd }}">{{ $plannedEnd }}</option>
                                                            <option value="12:30">12:30</option>
                                                            <option value="13:00">13:00</option>
                                                            <option value="13:30">13:30</option>
                                                            <option value="14:00">14:00</option>
                                                            <option value="14:30">14:30</option>
                                                            <option value="15:00">15:00</option>
                                                            <option value="15:30">15:30</option>
                                                            <option value="16:00">16:00</option>
                                                            <option value="16:30">16:30</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex justify-end">
                                                <button class="text-xs text-gray-600 underline" @click="lateOpen=false">Schließen</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Notiz Popover (Text + SaveNote Wrapper) --}}
                                <div class="relative">
                                    <button
                                        class="inline-flex items-center justify-center w-8 h-8 rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
                                        title="Notiz hinzufügen"
                                        @click="noteOpen = !noteOpen"
                                    >
                                        <i class="fas fa-pen text-sm"></i>
                                        @if($d['note'])
                                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-blue-300 border-2 border-white rounded-full"></span>
                                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-blue-200  rounded-full animate-ping"></span>
                                        @endif
                                    </button>

                                    <div x-cloak x-show="noteOpen" @click.outside="noteOpen=false"
                                         class="absolute right-0 z-10 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow">
                                        <label class="block text-xs text-gray-600 mb-1">Notiz</label>

                                        <textarea
                                            rows="3"
                                            class="w-full rounded border-gray-300 text-sm"
                                            wire:model.defer="noteInput.{{ $r['id'] }}"
                                            wire:change="saveNote({{ $r['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="saveNote({{ $r['id'] }})"
                                        >{{ $d['note'] }}</textarea>

                                        <div class="mt-2 flex justify-end">
                                            <button class="text-xs text-gray-600 underline" @click="noteOpen=false">Schließen</button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </td>
                    </tr>

                @empty
                    <tr>
                        <td colspan="4" class="p-6 text-center text-gray-500">Keine Einträge.</td>
                    </tr>
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
