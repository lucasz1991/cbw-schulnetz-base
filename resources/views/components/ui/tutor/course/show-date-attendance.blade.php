<div class="space-y-4 transition-opacity duration-300" wire:loading.class="opacity-30">
    <div class="flex  max-md:flex-wrap  items-center space-x-3  justify-between mb-8">
        <div class="flex  justify-between items-center space-x-3 w-full">
          <div class="flex items-center gap-2 ">
            <div class="flex   items-stretch rounded-md border border-gray-200 shadow-sm overflow-hidden h-max w-max max-md:mb-4">
                <!-- zurück (minus) -->
                 @if($selectPreviousDayPossible)
                <button
                    type="button"
                    wire:click="selectPreviousDay"
                    class="px-4 py-2  text-sm text-white   bg-blue-400 hover:bg-blue-700 "
                >
                    <svg class="h-3 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 8 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 1 1.3 6.326a.91.91 0 0 0 0 1.348L7 13"></path>
                    </svg>
                </button>
                @endif
  
                      <span class="bg-blue-200 text-blue-800 text-lg font-medium px-2.5 py-0.5 ">
                          {{ $selectedDay?->date?->format('d.m.Y') }}
                      </span>
  
    
                <!-- vorwärts (plus) -->
                @if($selectNextDayPossible)
                <button
                    type="button"
                    wire:click="selectNextDay"
                    class="px-4 py-2 bg-blue-400 text-sm text-white hover:bg-blue-700"
                >
                    <svg class="h-3 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 8 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 13 5.7-5.326a.909.909 0 0 0 0-1.348L1 1"></path>
                    </svg>
                </button>
                @endif
            </div>
                @php
                    use Illuminate\Support\Carbon;
                    $isToday = $selectedDay?->date
                        ? Carbon::parse($selectedDay->date)->isToday()
                        : false;
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

              <!-- Kalender-Icon -->
              <svg class="h-5 w-5 text-gray-500" fill="currentColor" viewBox="0 0 640 640" aria-hidden="true">
                <path d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM160 304L160 336C160 344.8 167.2 352 176 352L208 352C216.8 352 224 344.8 224 336L224 304C224 295.2 216.8 288 208 288L176 288C167.2 288 160 295.2 160 304zM288 304L288 336C288 344.8 295.2 352 304 352L336 352C344.8 352 352 344.8 352 336L352 304C352 295.2 344.8 288 336 288L304 288C295.2 288 288 295.2 288 304zM432 288C423.2 288 416 295.2 416 304L416 336C416 344.8 423.2 352 432 352L464 352C472.8 352 480 344.8 480 336L480 304C480 295.2 472.8 288 464 288L432 288zM160 432L160 464C160 472.8 167.2 480 176 480L208 480C216.8 480 224 472.8 224 464L224 432C224 423.2 216.8 416 208 416L176 416C167.2 416 160 423.2 160 432zM304 416C295.2 416 288 423.2 288 432L288 464C288 472.8 295.2 480 304 480L336 480C344.8 480 352 472.8 352 464L352 432C352 423.2 344.8 416 336 416L304 416zM416 432L416 464C416 472.8 423.2 480 432 480L464 480C472.8 480 480 472.8 480 464L480 432C480 423.2 472.8 416 464 416L432 416C423.2 416 416 423.2 416 432z"/>
              </svg>

              <!-- visueller Toggle -->
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
        <span class="inline-flex items-center rounded bg-yellow-100 text-yellow-800 px-2 py-0.5">Verspätet: {{ $stats['late'] }}</span>
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

  {{-- Tabelle: pro Tag alle TN mit Aktionen --}}
  <div class="overflow-x-auto border rounded bg-white">
    <table class="min-w-full text-sm table-fixed">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left w-1/3">
            <button
              type="button"
              wire:click="sort('name')"
              class="flex items-center gap-1 font-semibold group"
            >
              Teilnehmer
              {{-- (Sortierpfeile bleiben wie gehabt) --}}
              @if($sortBy === 'name')
                @if($sortDir === 'asc')
                  <svg class="w-3 h-3 text-blue-600 group-hover:text-blue-800 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 5-5 5 5"/>
                  </svg>
                @else
                  <svg class="w-3 h-3 text-blue-600 group-hover:text-blue-800 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 8 5 5 5-5"/>
                  </svg>
                @endif
              @else
                <svg class="w-3 h-3 text-gray-400 group-hover:text-gray-600 transition" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 8 5 5 5-5"/>
                </svg>
              @endif
            </button>
          </th>

          {{-- NEU: Status & Zeiten mittlere Spalte --}}
          <th class="px-4 py-2 text-left">Status &amp; Zeiten</th>

          {{-- NEU: Aktionen rechte Spalte --}}
          <th class="px-4 py-2 text-right"></th>
        </tr>
      </thead>


      <tbody class="divide-y divide-gray-100">
        @forelse($rows as $r)
          @php
            $d        = $r['data'];
            $hasEntry = $r['hasEntry'] ?? false; // << nutzen
            $late     = (int)($d['late_minutes'] ?? 0);
            $early    = (int)($d['left_early_minutes'] ?? 0);

            if (!$hasEntry) {
                $statusLabel = 'Unbekannt';
                $badge = 'bg-gray-100 text-gray-700';
            } elseif ($d['excused']) {
                $statusLabel = 'Entschuldigt';
                $badge = 'bg-blue-100 text-blue-800';
            } elseif ($d['present'] && $late > 0) {
                $statusLabel = 'Verspätet';
                $badge = 'bg-yellow-100 text-yellow-800';
            } elseif ($d['present']) {
                $statusLabel = 'Anwesend';
                $badge = 'bg-green-100 text-green-700';
            } else {
                // hat Eintrag, aber nicht anwesend & nicht entschuldigt => Abwesend
                $statusLabel = 'Fehlend';
                $badge = 'bg-red-100 text-red-700';
            }
          @endphp


          <tr x-data="{ lateOpen:false, noteOpen:false }" class="hover:bg-gray-50">
            {{-- 1) Teilnehmer (links) --}}
            <td class="px-4 py-2">
              <div class="w-min md:w-max">
                @if($r['user'])
                  <x-user.public-info :person="$r['user']" />
                @else
                  <div class="font-medium">Teilnehmer #{{ $r['id'] }}</div>
                @endif
              </div>
            </td>

            {{-- 2) Status & Zeiten (Mitte, linksbündig) --}}
          @php
            $d        = $r['data'];
            $hasEntry = $r['hasEntry'] ?? false;
            $late     = (int)($d['late_minutes'] ?? 0);
            $early    = (int)($d['left_early_minutes'] ?? 0);

            if (!$hasEntry) {
                // KEIN Eintrag => Default: Anwesend
                $statusLabel = 'Anwesend';
                $badge = 'bg-green-100 text-green-700';
            } elseif ($d['excused']) {
                $statusLabel = 'Entschuldigt';
                $badge = 'bg-blue-100 text-blue-800';
            } elseif ($d['present'] && $late > 0) {
                $statusLabel = 'Verspätet';
                $badge = 'bg-yellow-100 text-yellow-800';
            } elseif ($d['present']) {
                $statusLabel = 'Anwesend';
                $badge = 'bg-green-100 text-green-700';
            } else {
                // Eintrag vorhanden, nicht anwesend & nicht entschuldigt
                $statusLabel = 'Fehlend';
                $badge = 'bg-red-100 text-red-700';
            }
          @endphp


          <td class="px-4 py-2">
            <div class="flex items-center gap-2 flex-wrap">
              {{-- Status-Badge --}}
              <span class="inline-flex rounded px-2 py-0.5 text-xs {{ $badge }}">
                {{ $statusLabel }}
              </span>

              {{-- Verspätung / Frühweg Badges --}}
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




            {{-- 3) Aktionen (rechts, Buttons ganz nach rechts) --}}
          @php
            // Abwesend nur, wenn Eintrag existiert und present=false & !excused
            $isAbsent = ($r['hasEntry'] ?? false) && ($d['present'] === false) && !($d['excused'] ?? false);
          @endphp
            <td class="px-4 py-2">
              <div class="flex items-center justify-end gap-1">

                {{-- Anwesend / Abwesend: nur Flags setzen --}}
                @if($isAbsent)
                  <button
                    class="inline-flex items-center justify-center w-8 h-8 rounded border border-green-600 text-green-700 hover:bg-green-50"
                    title="Anwesend"
                    wire:click.stop="markPresent({{ $r['id'] }})">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 13l4 4L19 7" />
                    </svg>
                  </button>
                @else
                  <button
                    class="inline-flex items-center justify-center w-8 h-8 rounded border border-red-600 text-red-700 hover:bg-red-50"
                    title="Abwesend"
                    wire:click.stop="markAbsent({{ $r['id'] }})">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                @endif

{{-- Verspätung/Frühweg (Popover) --}}
<div class="relative" x-data="{
    arrive: '{{ $d['arrived_at'] ?? $plannedStart }}',
    leave:  '{{ $d['left_at']    ?? $plannedEnd }}'
}">
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
       class="absolute right-0 z-10 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow">
    <div class="space-y-4">

      {{-- Gekommen (Uhrzeit) --}}
      <div>
        <label for="arrive-{{ $r['id'] }}" class="block mb-2 text-xs font-medium text-gray-600">Gekommen (Uhrzeit)</label>
        <div class="flex items-end gap-2">
          <div class="relative flex-1">
            <div class="absolute inset-y-0 end-0 top-0 flex items-center pe-3.5 pointer-events-none">
              <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm11-4a1 1 0 1 0-2 0v4a1 1 0 0 0 .293.707l3 3a1 1 0 0 0 1.414-1.414L13 11.586V8Z" clip-rule="evenodd"/>
              </svg>
            </div>
            <input
              x-model="arrive"
              type="time"
              id="arrive-{{ $r['id'] }}"
              class="bg-gray-50 border leading-none border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
              min="{{ $plannedStart }}"
              max="{{ $plannedEnd }}"
              step="60"
              value="{{ $d['arrived_at'] ?? $plannedStart }}"
              wire:change="setArrivalTime({{ $r['id'] }}, $event.target.value)"
            />
          </div>

          {{-- Schnellwahl --}}
          <div class="w-28 shrink-0">
            <label class="sr-only" for="arrive-quick-{{ $r['id'] }}">Schnellwahl</label>
            <select
              id="arrive-quick-{{ $r['id'] }}"
              @change="
                arrive = $event.target.value;
                $wire.setArrivalTime({{ $r['id'] }}, arrive);
              "
              class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
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

      {{-- Gegangen (Uhrzeit) --}}
      <div>
        <label for="leave-{{ $r['id'] }}" class="block mb-2 text-xs font-medium text-gray-600">Gegangen (Uhrzeit)</label>
        <div class="flex items-end gap-2">
          <div class="relative flex-1">
            <div class="absolute inset-y-0 end-0 top-0 flex items-center pe-3.5 pointer-events-none">
              <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12Zm11-4a1 1 0 1 0-2 0v4a1 1 0 0 0 .293.707l3 3a1 1 0 0 0 1.414-1.414L13 11.586V8Z" clip-rule="evenodd"/>
              </svg>
            </div>
            <input
              x-model="leave"
              type="time"
              id="leave-{{ $r['id'] }}"
              class="bg-gray-50 border leading-none border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
              min="{{ $plannedStart }}"
              max="{{ $plannedEnd }}"
              step="60"
              value="{{ $d['left_at'] ?? $plannedEnd }}"
              wire:change="setLeaveTime({{ $r['id'] }}, $event.target.value)"
            />
          </div>

          {{-- Schnellwahl --}}
          <div class="w-28 shrink-0">
            <label class="sr-only" for="leave-quick-{{ $r['id'] }}">Schnellwahl</label>
            <select
              id="leave-quick-{{ $r['id'] }}"
              @change="
                leave = $event.target.value;
                $wire.setLeaveTime({{ $r['id'] }}, leave);
              "
              class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
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



                {{-- Notiz (Popover) --}}
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
                      class="absolute right-0 z-10 mt-2 w-72 rounded border border-gray-300 bg-white p-3 shadow">
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
