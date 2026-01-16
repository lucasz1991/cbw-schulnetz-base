<div x-data="{ openId: @entangle('openDayId').live }" class="space-y-3 py-8  container px-5 mx-auto">
    <x-alert>
        Dokumentation der Bausteintage<br>
        Hier findest du die Notizen des Dozenten zu jedem Bausteintag. Klicke einen Tag an, um die Details zu öffnen.
    </x-alert>

    <!-- Menüleiste -->
    <div class="flex items-center justify-between gap-4 ">
        <div class="flex items-center gap-4 text-sm text-gray-700">
            <div class="flex items-center gap-2">
                <span class="font-semibold">Tage:</span>
                <span>{{ $this->daysCount }}</span>
            </div>
            @php
                $from = $this->dateRange['from'];
                $to   = $this->dateRange['to'];
            @endphp
            <div class="flex items-center gap-2">
                <span class="font-semibold">Zeitraum:</span>
                <span>
                    @if($from && $to)
                        {{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }}
                    @else
                        —
                    @endif
                </span>
            </div>
        </div>
        <!--  <div class="flex items-center gap-2">
            <xbuttons.button-basic size="'sm'"
                wire:click="exportAllDoku"
                title="Alle Doku-Einträge als TXT herunterladen (on-the-fly)">
                Download
            </xbuttons.button-basic>
        </div>  -->
    </div>

    @forelse($days as $day)
        <div class="border rounded-lg bg-white shadow-sm overflow-hidden" wire:key="day-{{ $day->id }}">
            <!-- Header (div mit role=button erlaubt Blockcontent) -->
            <div role="button" tabindex="0"
                 @click="openId = (openId === {{ $day->id }} ? null : {{ $day->id }})"
                 @keydown.enter.prevent="openId = (openId === {{ $day->id }} ? null : {{ $day->id }})"
                 @keydown.space.prevent="openId = (openId === {{ $day->id }} ? null : {{ $day->id }})"
                 class="w-full flex items-center justify-between px-4 py-3 cursor-pointer"
                 :aria-expanded="String(openId === {{ $day->id }})"
                 :class="openId === {{ $day->id }}
                    ? 'bg-secondary text-white '
                    : 'bg-white hover:bg-gray-50 text-gray-600'"
                 aria-controls="panel-{{ $day->id }}">
                <div class="text-left">
                    <p class="text-sm ">
                        {{ $day->date ? \Illuminate\Support\Carbon::parse($day->date)->format('d.m.Y') : '—' }}
                    </p>
                </div>

                <div class="flex items-center gap-3">

                    <svg class="w-5 h-5  transition-transform"
                         :class="openId === {{ $day->id }} ? 'rotate-180' : ''"
                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>

            <!-- Panel -->
            <div id="panel-{{ $day->id }}"
                 x-show="openId === {{ $day->id }}"
                 x-collapse
                 x-cloak>
                <div class="px-4 py-4 text-sm text-gray-700 leading-relaxed">
                    @if($day->notes)
                        {!! $day->notes !!}
                    @else
                        <p class="text-gray-500 italic">Keine Dokumentation vorhanden.</p>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">Keine Bausteintage vorhanden.</p>
    @endforelse
</div>
