@props([
  'participants',
  'selectedDay',
  'stats',
  'rows',
  'sortBy',
  'sortDir',
  'selectPreviousDayPossible',
  'selectNextDayPossible',
    'availableDays',
  'plannedStart',
  'plannedEnd',
])

<div class="space-y-4 transition-opacity duration-300">
    <div class="flex max-md:flex-wrap items-center space-x-3 justify-between mb-8">
        <div class="flex justify-between items-center space-x-3 w-full">
            <div class="flex items-center gap-2">
            <div
                  x-data="{ open: false }"
                  class="relative"
              >
                <div class="relative flex items-stretch rounded-md border border-gray-200 shadow-sm overflow-hidden h-max w-max max-md:mb-4">
                  {{-- Zurück --}}
                  @if($selectPreviousDayPossible)
                      <button
                          type="button"
                          wire:click="selectPreviousDay"
                          class="px-4 py-2 text-sm text-white bg-blue-400 hover:bg-blue-700"
                      >
                          <i class="fas fa-chevron-left text-xs"></i>
                      </button>
                  @endif
                  {{-- Datum / Dropdown Trigger --}}
                  <button
                      type="button"
                      @click="open = !open"
                      class="flex items-center gap-2 bg-blue-200 text-blue-800 text-sm font-medium px-3 py-2 hover:bg-blue-300 focus:outline-none"
                  >
                      <span class="whitespace-nowrap">
                          {{ $selectedDay?->date?->format('d.m.Y') }}
                      </span>
                      <i
                          class="fas fa-chevron-down text-xs transition-transform"
                          :class="{ 'rotate-180': open }"
                      ></i>
                  </button>
                  {{-- Vor --}}
                  @if($selectNextDayPossible)
                      <button
                          type="button"
                          wire:click="selectNextDay"
                          class="px-4 py-2 bg-blue-400 text-sm text-white hover:bg-blue-700"
                      >
                          <i class="fas fa-chevron-right text-xs"></i>
                      </button>
                  @endif
                </div>
                  {{-- Dropdown --}}
                  <div
                      x-show="open"
                      x-transition
                      @click.outside="open = false"
                      x-cloak
                      class="absolute top-full left-0 mt-1 z-50 w-full min-w-[12rem]
                            bg-white border border-gray-200 rounded-md shadow-lg
                            max-h-64 overflow-y-auto scroll-container"
                  >
                      @foreach($availableDays as $day)
                          <button
                              type="button"
                              wire:click="selectDay('{{ $day->id }}')"
                              @click="open = false"
                              class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50
                                    {{ $selectedDay?->date?->isSameDay($day->date) ? 'bg-blue-100 font-semibold' : '' }}"
                          >
                              {{ $day->date->format('d.m.Y') }}
                          </button>
                      @endforeach
                  </div>
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
            <div class="hidden md:block">
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
    @if($selectedDay)
        <div class="inline-flex overflow-hidden rounded-full border border-gray-200 bg-white text-xs shadow-sm">
            {{-- Anwesend --}}
            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-green-50 text-green-800">
                <i class="fas fa-check-circle text-green-600"></i>
                <span class="font-semibold">{{ $stats['present'] }}</span>
                <span class="hidden md:inline">Anwesend</span>
            </span>
            {{-- Divider --}}
            <span class="w-px bg-gray-200"></span>
            {{-- Teilweise --}}
            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-yellow-50 text-yellow-800">
                <i class="fas fa-clock text-yellow-600"></i>
                <span class="font-semibold">{{ $stats['late'] }}</span>
                <span class="hidden md:inline">Teilweise</span>
            </span>
            <span class="w-px bg-gray-200"></span>
            {{-- Entschuldigt --}}
            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-800">
                <i class="fas fa-file-medical text-blue-600"></i>
                <span class="font-semibold">{{ $stats['excused'] }}</span>
                <span class="hidden md:inline">Entschuldigt</span>
            </span>
            <span class="w-px bg-gray-200"></span>
            {{-- Fehlend --}}
            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 text-red-800">
                <i class="fas fa-times-circle text-red-600"></i>
                <span class="font-semibold">{{ $stats['absent'] }}</span>
                <span class="hidden md:inline">Fehlend</span>
            </span>
            <span class="w-px bg-gray-200"></span>
            {{-- Gesamt --}}
            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-50 text-gray-800">
                <i class="fas fa-users text-gray-600"></i>
                <span class="font-semibold">{{ $stats['total'] }}</span>
                <span class="hidden md:inline">Gesamt</span>
            </span>
        </div>
    @endif
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
                        $late     = $d['late_minutes'] ?? null;
                        $early    = $d['left_early_minutes'] ?? null;

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
                      <tr
                        x-data="{
                          lateOpen:false,
                          noteOpen:false,
                          arrive: @entangle('arriveInput.' . $r['id']).live,
                          leave:  @entangle('leaveInput.'  . $r['id']).live,
                          note:   @entangle('noteInput.'   . $r['id']).live,
                        }"
                        wire:key="row-{{ $r['id'] }}"
                        class="hover:bg-gray-50"
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
                                    <div wire:loading wire:target="clearTimes({{ $r['id'] }})" class="flex items-center">
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
                                        class="relative inline-flex items-center justify-center w-8 h-8 rounded border  @if(($d['arrived_at'] ?? null) || ($d['left_at'] ?? null)) border-yellow-500 text-yellow-600 bg-yellow-50 hover:bg-yellow-100 @else border-gray-300 text-gray-500 hover:bg-gray-200  @endif"
                                        title="Verspätung / Früh weg eintragen"
                                        @click="lateOpen = !lateOpen"
                                    >
                                        <i class="far fa-clock text-sm"></i>
                                        @if(($d['arrived_at'] ?? null) || ($d['left_at'] ?? null))
                                          <span class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-400  rounded-full animate-ping"></span>
                                          <span class="absolute -top-1 -right-1 w-3 h-3 bg-yellow-400 border-2 border-white rounded-full"></span>
                                        @endif
                                    </button>
                                    <div x-cloak x-show="lateOpen" @click.outside="lateOpen=false"
                                         class="absolute right-0 z-10 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow">
                                         <div class="absolute right-0 top-0 flex justify-end gap-2 mb-4 p-2">
                                               <button
                                                    type="button"
                                                    class="text-xs text-gray-600 hover:text-red-600"
                                                    wire:click="clearTimes({{ $r['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="clearTimes({{ $r['id'] }})"
                                                    title="Zeiten löschen"
                                                ><i class="far fa-trash-alt"></i></button>
                                           <button class="text-xs text-gray-600" @click="lateOpen=false"><i class="far fa-times-circle"></i></button>
                                         </div>
                                        <div class="space-y-4">
                                            {{-- Gekommen --}}
                                            <div>
                                                <label for="arrive-{{ $r['id'] }}" class="block mb-2 text-xs font-medium text-gray-600">Gekommen (Uhrzeit)</label>
                                                <div class="flex items-end">
                                                    <div class="relative flex-1">
                                                        <div class="absolute inset-y-0 end-0 top-0 flex items-center pe-3.5 pointer-events-none">
                                                            <i class="far fa-clock text-gray-500"></i>
                                                        </div>
                                                        <input
                                                            x-model="arrive"
                                                            type="time"
                                                            id="arrive-{{ $r['id'] }}"
                                                            class="bg-gray-50 border border-r-0 leading-none border-gray-300 text-gray-900 text-sm rounded-l-lg focus:ring-blue-500 focus:border-blue-500 block cursor-pointer w-full h-[40px] p-2.5"
                                                            min="{{ $plannedStart }}"
                                                            max="{{ $plannedEnd }}"
                                                            step="60"
                                                            wire:model.live="arriveInput.{{ $r['id'] }}"
                                                            wire:change="saveArrival({{ $r['id'] }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="saveArrival({{ $r['id'] }})"
                                                        />
                                                    </div>
                                                    {{-- Schnellauswahl (BEHALTEN) --}}
                                                    <div class="w-10 shrink-0">
                                                        <select
                                                            id="arrive-quick-{{ $r['id'] }}"
                                                            @change="
                                                                arrive = $event.target.value;
                                                                $wire.set('arriveInput.{{ $r['id'] }}', arrive);
                                                                $wire.saveArrival({{ $r['id'] }});
                                                            "
                                                            class="bg-gray-50 border border-gray-300 text-white/0 text-sm rounded-r-lg focus:ring-blue-500 focus:border-blue-500 block p-2 cursor-pointer h-[40px] w-10 "
                                                            wire:loading.attr="disabled"
                                                            wire:target="saveArrival({{ $r['id'] }})"
                                                        >
                                                            <option class="text-gray-700" value="">Bitte wählen</option>
                                                            <option class="text-gray-700" value="{{ $plannedStart }}">Pünktlich</option>
                                                            <option class="text-gray-700" value="08:30">08:30</option>
                                                            <option class="text-gray-700" value="09:00">09:00</option>
                                                            <option class="text-gray-700" value="09:30">09:30</option>
                                                            <option class="text-gray-700" value="10:00">10:00</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            {{-- Gegangen --}}
                                            <div>
                                                <label for="leave-{{ $r['id'] }}" class="block mb-2 text-xs font-medium text-gray-600">Gegangen (Uhrzeit)</label>
                                                <div class="flex items-end">
                                                    <div class="relative flex-1">
                                                        <div class="absolute inset-y-0 end-0 top-0 flex items-center pe-3.5 pointer-events-none">
                                                            <i class="far fa-clock text-gray-500"></i>
                                                        </div>
                                                        <input
                                                            x-model="leave"
                                                            type="time"
                                                            id="leave-{{ $r['id'] }}"
                                                            class="bg-gray-50 border border-r-0 leading-none border-gray-300 text-gray-900 text-sm rounded-l-lg focus:ring-blue-500 focus:border-blue-500 block cursor-pointer w-full h-[40px] p-2.5"
                                                            min="{{ $plannedStart }}"
                                                            max="{{ $plannedEnd }}"
                                                            step="60"
                                                            wire:model.live="leaveInput.{{ $r['id'] }}"
                                                            wire:change="saveLeave({{ $r['id'] }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="saveLeave({{ $r['id'] }})"
                                                        />
                                                    </div>
                                                    {{-- Schnellauswahl (BEHALTEN) --}}
                                                    <div class="w-10 shrink-0">
                                                        <select
                                                            id="leave-quick-{{ $r['id'] }}"
                                                            @change="
                                                                leave = $event.target.value;
                                                                $wire.set('leaveInput.{{ $r['id'] }}', leave);
                                                                $wire.saveLeave({{ $r['id'] }});
                                                            "
                                                            class="bg-gray-50 border border-gray-300 text-white/0 text-sm rounded-r-lg focus:ring-blue-500 focus:border-blue-500 block p-2 cursor-pointer h-[40px] w-10 "
                                                            wire:loading.attr="disabled"
                                                            wire:target="saveLeave({{ $r['id'] }})"
                                                        >
                                                            <option class="text-gray-700" value="">Bitte wählen</option>
                                                            <option class="text-gray-700" value="12:30">12:30</option>
                                                            <option class="text-gray-700" value="13:00">13:00</option>
                                                            <option class="text-gray-700" value="13:30">13:30</option>
                                                            <option class="text-gray-700" value="14:00">14:00</option>
                                                            <option class="text-gray-700" value="14:30">14:30</option>
                                                            <option class="text-gray-700" value="15:00">15:00</option>
                                                            <option class="text-gray-700" value="15:30">15:30</option>
                                                            <option class="text-gray-700" value="16:00">16:00</option>
                                                            <option class="text-gray-700" value="16:30">16:30</option>
                                                            <option class="text-gray-700" value="{{ $plannedEnd }}">Pünktlich</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- Notiz Popover (Text + SaveNote Wrapper) --}}
                                <div class="relative"
                                    x-data="{
                                        tipOpen: false,
                                        showTooltip() { this.tipOpen = true; clearTimeout(this.__tipT); this.__tipT = setTimeout(() => this.tipOpen = false, 2500); },
                                        hideTooltip() { this.tipOpen = false; clearTimeout(this.__tipT); },
                                    }"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded border
                                            @if($d['note']) border-blue-300 text-blue-400 bg-blue-50/70 hover:bg-blue-100
                                            @else border-gray-300 text-gray-500 hover:bg-gray-50
                                            @endif
                                            @if(!$hasEntry) opacity-80 @endif
                                        "
                                        title="Notiz hinzufügen"
                                        @mouseenter="@if(!$hasEntry) showTooltip() @endif"
                                        @mouseleave="hideTooltip()"
                                        @focus="@if(!$hasEntry) showTooltip() @endif"
                                        @blur="hideTooltip()"
                                        @click="
                                            @if($hasEntry)
                                                noteOpen = !noteOpen
                                            @else
                                                showTooltip()
                                            @endif
                                        "
                                    >
                                        <i class="fas fa-pen text-sm"></i>

                                        @if($d['note'])
                                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-blue-300 border-2 border-white rounded-full"></span>
                                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-blue-200 rounded-full animate-ping"></span>
                                        @endif
                                    </button>
                                {{-- Tooltip wenn kein Entry --}}
                                @if(!$hasEntry)
                                    <div
                                        x-cloak
                                        x-show="tipOpen"
                                        x-transition.opacity.duration.150ms
                                        @click.outside="hideTooltip()"
                                        class="absolute right-0 z-20 mt-2 w-64 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 shadow"
                                    >
                                        <div class="font-semibold mb-0.5">Notiz erst nach Eintrag</div>
                                        <div>
                                            Bitte zuerst <span class="font-medium">Anwesend/Fehlend/Entschuldigt</span> setzen (oder eine Zeit eintragen),
                                            dann kannst du eine Notiz speichern.
                                        </div>
                                    </div>
                                @endif
                                {{-- Normales Notiz-Popover --}}
                                <div
                                    x-cloak
                                    x-show="noteOpen"
                                    @click.outside="noteOpen=false"
                                    class="absolute right-0 z-10 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow"
                                >
                                    <label class="block text-xs text-gray-600 mb-1">Notiz</label>
                                    <textarea
                                        x-model="note"
                                        rows="3"
                                        class="w-full rounded border-gray-300 text-sm"
                                        wire:change="saveNote({{ $r['id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="saveNote({{ $r['id'] }})"
                                    ></textarea>
                                    <div class="mt-2 flex justify-end">
                                        <button type="button" class="text-xs text-gray-600 underline" @click="noteOpen=false">Schließen</button>
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