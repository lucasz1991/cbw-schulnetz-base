@php
  $editorKey = 'rb-editor-'
      .($selectedCourseId ?? 'x')
      .'-'
      .($selectedCourseDayId ?? 'x')
      .'-'
      .($editorVersion ?? 0);
@endphp

<div class="w-full"  wire:loading.class="cursor-wait opacity-50 animate-pulse"  wire:target="date,selectCourse,selectCourseDay,selectPrevCourse,selectNextCourse,selectPrevDay,selectNextDay,selectedCourseId,submit,save,importTutorDocToDraft" >
  <div class="max-w-full grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">

    {{-- linke Spalte: Kurswahl & CourseDays --}}
    <aside class="space-y-4 lg:col-span-1"  >
      {{-- Kurswahl: Navigation oben + Trigger mit Panel --}}

      @php
        $sel = collect($courses)->firstWhere('id', $selectedCourseId);
        $selTitle  = $sel['title'] ?? 'Kurs wählen …';
        $selKlasse = $sel['klassen_id'] ?? null;
        $startFmt  = !empty($sel['planned_start_date']) ? \Illuminate\Support\Carbon::parse($sel['planned_start_date'])->format('d.m.Y') : null;
        $endFmt    = !empty($sel['planned_end_date'])   ? \Illuminate\Support\Carbon::parse($sel['planned_end_date'])->format('d.m.Y') : null;

        $badge = fn($color,$text) => "<span class=\"inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-semibold
          bg-{$color}-50 text-{$color}-700 border-{$color}-200\">{$text}</span>";
        $phaseBadge = $sel ? $badge($sel['phase_color'], ucfirst($sel['phase'])) : '';
        $ampel      = $sel['ampel'] ?? null;
        $ampelBadge = $ampel ? $badge($ampel['color'], $ampel['info'] ? "{$ampel['label']} – {$ampel['info']}" : $ampel['label']) : '';

        // Progress für den Trigger
        $total    = max(0, (int)($sel['days_total'] ?? 0));
        $missing  = max(0, (int)($sel['days_missing'] ?? 0));
        $draft    = max(0, (int)($sel['days_draft'] ?? 0));
        $finished = max(0, (int)($sel['days_finished'] ?? 0));
        $sum = $missing + $draft + $finished;
        if ($total > 0 && $sum !== $total) { $draft = max(0, $draft + ($total - $sum)); }
        $p = fn($n) => $total > 0 ? round(($n / $total) * 100, 2) : 0;

        $segments = [];
        if ($total === 0) {
          $segments = [['w'=>100,'class'=>'bg-slate-200','title'=>'Keine Kurstage']];
        } else {
          if ($missing  > 0) $segments[] = ['w'=>$p($missing), 'class'=>'bg-gray-300',  'title'=>'Fehlend'];
          if ($draft    > 0) $segments[] = ['w'=>$p($draft),   'class'=>'bg-amber-400', 'title'=>'Entwurf'];
          if ($finished > 0) $segments[] = ['w'=>$p($finished),'class'=>'bg-green-500', 'title'=>'Fertig'];
          if (empty($segments)) $segments = [['w'=>100,'class'=>'bg-slate-200','title'=>'Keine Kurstage']];
        }
      @endphp

      {{-- Trigger: aktueller Kurs (truncate + Mini-Progress) --}}
      <div class="relative">
        <x-ui.dropdown.anchor-dropdown
          align="left"
          width="auto"
          :matchTriggerWidth="true"
          dropdownClasses="mt-2 w-[min(640px,90vw)] bg-white  rounded-xl shadow overflow-hidden"
          contentClasses="bg-white"
          :overlay="true"
          :trap="true"
          :offset="8"
          :scrollOnOpen="true"
          :scrollOnTrigger="true"
          :headerOffset="20"
        >
            <x-slot name="trigger">
              {{-- hier kommt dein kompletter Trigger-Button rein (aktueller Kurs mit Progressbar) --}}
              <button type="button"
                  class="relative  w-full inline-flex items-center gap-3 px-3 py-2 rounded-md border text-sm
                        border-primary-300  bg-white hover:bg-gray-50">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  @if($selKlasse)
                    <span class="shrink-0 inline-flex items-center rounded border px-1.5 py-0.5 text-[10px] font-semibold
                                bg-slate-50 text-slate-700 border-slate-200">{{ $selKlasse }}</span>
                  @endif
                  <div class="mt-1 flex items-center gap-1.5 text-xs overflow-hidden">
                    <div class="shrink-0">{!! $phaseBadge !!}</div>
                  </div>
                </div>
                <div class="text-left mt-2">
                  <div class="text-sm font-semibold text-gray-800 truncate">{{ $selTitle }}</div>
                  @if($startFmt || $endFmt)
                    <div class="mt-1 text-xs text-gray-500 truncate">
                      {{ $startFmt ?? '—' }} – {{ $endFmt ?? '—' }}
                    </div>
                  @endif
                </div>


                {{-- Mini-Progressbar --}}
                <div class="mt-2">
                  <div class="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden" aria-hidden="true">
                    <div class="flex h-full w-full">
                      @foreach($segments as $i => $seg)
                        <div class="h-full {{ $seg['class'] }} {{ $i===0 ? 'rounded-l-full' : '' }} {{ $i===count($segments)-1 ? 'rounded-r-full' : '' }}"
                            style="width: {{ $seg['w'] }}%" title="{{ $seg['title'] }}: {{ $seg['w'] }}%"></div>
                      @endforeach
                    </div>
                  </div>
                  <div class="mt-1 flex flex-wrap items-center gap-3 text-[11px]">
                          @foreach($segments as $seg)
                            @php
                              $count = match($seg['title']) {
                                'Fehlend' => $missing,
                                'Entwurf' => $draft,
                                'Fertig'  => $finished,
                                default   => null
                              };
                            @endphp
                            @if($count)
                              <span class="inline-flex items-center gap-1">
                                <span class="inline-block h-2 w-2 rounded-full {{ $seg['class'] }}"></span>
                                {{ $seg['title'] }} {{ $count }}
                              </span>
                            @endif
                          @endforeach
                          <span class="ml-auto">
                            <span class="font-medium">{{ $finished }}/{{ $total }}</span> Tage fertig
                          </span>
                  </div>
                </div>
              </div>

              <svg class="w-4 h-4 shrink-0" :class="open && 'rotate-180 transition-transform'"
                  viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/>
              </svg>
                </button>
            </x-slot>

            <x-slot name="content">
              <div class="relative bg-gray-100 text-base font-semibold text-gray-700 p-3 border-b border-gray-400 flex items-center justify-between">
                <h4>
                  <span>Meine Kurse</span>
                </h4>
                <button
                  type="button"
                  @click="open = false"
                  class="absolute right-2 top-1"
                  title="Schließen"
                >
                  <i class="fad fa-times-circle text-gray-500"></i>
                </button>
              </div>

              <x-ui.scrollcontainer.scrollcontainer
                    axis="y"
                    snap="start"
                    snapMode="mandatory"
                    :visibleRows="4" 
                    maxHeightClass="max-h-[60vh]"
                    containerClass=""
                >
                  @foreach($courses as $c)
                    <x-ui.scrollcontainer.scrollcontainer-item
                        :item="$c"                        
                        axis="y"
                        snap="start"
                        :itemClass="$loop->even ? 'bg-gray-100' : 'bg-white'"
                        keyPattern="{id}-{klassen_id}" 

                    >
                @php
                  $active   = (int)($c['id'] ?? 0) === (int)($selectedCourseId ?? 0);
                  $startFmt = !empty($c['planned_start_date']) ? \Illuminate\Support\Carbon::parse($c['planned_start_date'])->format('d.m.Y') : null;
                  $endFmt   = !empty($c['planned_end_date'])   ? \Illuminate\Support\Carbon::parse($c['planned_end_date'])->format('d.m.Y') : null;

                  $badge2 = fn($color,$text) => "<span class=\"inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-semibold bg-{$color}-50 text-{$color}-700 border-{$color}-200\">{$text}</span>";
                  $phaseBadge2 = $badge2($c['phase_color'] ?? 'slate', ucfirst($c['phase'] ?? ''));
                  $ampel2 = $c['ampel'] ?? ['color'=>'slate','label'=>'','info'=>null];

                  $total2    = max(0, (int)($c['days_total'] ?? 0));
                  $missing2  = max(0, (int)($c['days_missing'] ?? 0));
                  $draft2    = max(0, (int)($c['days_draft'] ?? 0));
                  $finished2 = max(0, (int)($c['days_finished'] ?? 0));
                  $sum2 = $missing2 + $draft2 + $finished2;
                  if ($total2 > 0 && $sum2 !== $total2) $draft2 = max(0, $draft2 + ($total2 - $sum2));
                  $p2 = fn($n) => $total2 > 0 ? round(($n / $total2) * 100, 2) : 0;

                  $segments2 = [];
                  if ($total2 === 0) {
                    $segments2 = [['w'=>100,'class'=>'bg-slate-200','title'=>'Keine Kurstage']];
                  } else {
                    if ($missing2  > 0) $segments2[] = ['w'=>$p2($missing2),  'class'=>'bg-gray-300',  'title'=>'Fehlend'];
                    if ($draft2    > 0) $segments2[] = ['w'=>$p2($draft2),    'class'=>'bg-amber-400', 'title'=>'Entwurf'];
                    if ($finished2 > 0) $segments2[] = ['w'=>$p2($finished2), 'class'=>'bg-green-500', 'title'=>'Fertig'];
                    if (empty($segments2)) $segments2 = [['w'=>100,'class'=>'bg-slate-200','title'=>'Keine Kurstage']];
                  }
                @endphp
                @if(!empty($c['id']))
                  <button type="button" 
                          wire:click="selectCourse({{ $c['id'] }})"
                          @click="open=false"
                          class="group block relative w-full text-left px-3 py-4 transition-all duration-150
                                {{ $active ? 'bg-primary-800 text-white border-primary-600 shadow-sm active'
                                            : ' text-gray-700 border-gray-400 hover:border-primary-300 hover:bg-primary-50' }}">
                    <div class="flex items-center justify-between">
                      <span class="text-xs font-semibold uppercase tracking-wide
                                  {{ $active ? 'text-primary-100' : 'text-gray-500 group-hover:text-primary-600' }}">
                        {{ $c['klassen_id'] ?? '—' }}
                      </span>
                    </div>
                    <div class="mt-1 text-sm font-medium truncate {{ $active ? 'text-white' : 'text-gray-800 group-hover:text-primary-700' }}">
                      {{ $c['title'] ?? 'Unbenannter Kurs' }}
                    </div>
                      @if($startFmt || $endFmt)
                        <div class="mt-0.5 text-xs {{ $active ? 'text-primary-100' : 'text-gray-500 group-hover:text-primary-600' }}">
                          {{ $startFmt ?? '—' }} – {{ $endFmt ?? '—' }}
                        </div>
                      @endif
                    {{-- Progressbar --}}
                    <div class="mt-2">
                      <div class="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden" aria-hidden="true">
                        <div class="flex h-full w-full">
                          @foreach($segments2 as $i => $seg)
                            <div class="h-full {{ $seg['class'] }} {{ $i===0 ? 'rounded-l-full' : '' }} {{ $i===count($segments2)-1 ? 'rounded-r-full' : '' }}"
                                style="width: {{ $seg['w'] }}%" title="{{ $seg['title'] }}: {{ $seg['w'] }}%"></div>
                          @endforeach
                        </div>
                      </div>
                    </div>
                    {{-- Badges: Phase oben rechts (wie gewünscht) + Ampel unten mit Progress --}}
                    <div class="absolute right-2 top-1">
                      {!! $phaseBadge2 !!}
                    </div>
                  </button>
                    @endif
                  </x-ui.scrollcontainer.scrollcontainer-item>
                @endforeach
              </x-ui.scrollcontainer.scrollcontainer>
            </x-slot>
          </x-ui.dropdown.anchor-dropdown>
      </div>
      <div class=" mb-4 space-y-2">
      {{-- CourseDays des aktiven Kurses --}}
      <div class="">

        @if(!$courseDays)
          <div class="text-sm text-gray-500">Für diesen Kurs sind noch keine Kurstage vorhanden.</div>
        @else
          {{-- Kurstage Desktop (ab md): bleibt wie aktuell --}}
            <div class="hidden md:grid sm:grid-cols-2 gap-2">
              @foreach($courseDays as $d)
                @php
                  $isDay = (int)$d['id'] === (int)$selectedCourseDayId;
                  $dotColor = $d['dot']['color']; // gray | amber | green
                @endphp
                <button
                  type="button"
                  wire:click="selectCourseDay({{ $d['id'] }})"
                  class="w-full flex items-center justify-between rounded-lg border p-2 text-sm
                        {{ $isDay ? 'border-primary-300 ring-2 ring-primary-200 bg-white' : 'border-gray-200 bg-white hover:bg-gray-50' }}"
                >
                  <span class="font-medium truncate">{{ $d['label'] }}</span>
                  <span class="inline-block h-2.5 w-2.5 rounded-full bg-{{ $dotColor }}-500" title="{{ $d['dot']['title'] }}"></span>
                </button>
              @endforeach
            </div>

            {{-- Kurstage Mobile (unter md): Navigations-Toolbar + Dropdown --}}
            <div class="md:hidden mt-3" x-data="{ open:false }">
              <div class="flex items-center gap-2 mb-2">
                <button type="button"
                        wire:click="selectPrevDay"
                        wire:target="selectPrevDay,selectCourseDay"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-3 py-2 rounded-md border text-sm
                              border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                  <svg class="w-4 h-4 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/>
                  </svg>
                  <span class="hidden md:inline-block">Zurück</span>
                </button>
                @php
                  $cur = collect($courseDays)->firstWhere('id', $selectedCourseDayId);
                @endphp
                <div class="relative flex-1 min-w-0">
                  <button type="button"
                          @click="open = !open"
                          :aria-expanded="open ? 'true' : 'false'"
                          class="w-full inline-flex items-center justify-between gap-3 px-3 py-2 rounded-md border text-sm
                                border-primary-300 ring-1 ring-primary-200 bg-white hover:bg-gray-50">
                    <span class="truncate text-center md:text-left">
                      {{ $cur['label'] ?? 'Kurstag wählen …' }}
                    </span>
                    <svg class="w-4 h-4 shrink-0" :class="open && 'rotate-180 transition-transform'"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/>
                    </svg>
                  </button>
                  <div x-cloak x-show="open" @click.outside="open=false"
                      x-transition.opacity.duration.100ms
                      class="absolute z-30 mt-2 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-[60vh] overflow-y-auto">
                    <ul class="p-2 space-y-1">
                      @foreach($courseDays as $d)
                        @php
                          $isDay = (int)$d['id'] === (int)$selectedCourseDayId;
                          $dotColor = $d['dot']['color'];
                        @endphp
                        <li>
                          <button type="button"
                                  wire:click="selectCourseDay({{ $d['id'] }})"
                                  @click="open=false"
                                  class="w-full flex items-center justify-between rounded-md px-2 py-2 text-sm
                                        {{ $isDay ? 'bg-primary-50 text-primary-700' : 'hover:bg-gray-50 text-gray-700' }}">
                            <span class="truncate">{{ $d['label'] }}</span>
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-{{ $dotColor }}-500"></span>
                          </button>
                        </li>
                      @endforeach
                    </ul>
                  </div>
                </div>
                <button type="button"
                        wire:click="selectNextDay"
                        wire:target="selectNextDay,selectCourseDay"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-3 py-2 rounded-md border text-sm
                              border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                  <span class="hidden md:inline-block">Weiter</span>
                  <svg class="w-4 h-4 ml-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                  </svg>
                </button>
              </div>
            </div>
        @endif
      </div>
    </aside>
    {{-- rechte, große Spalte: Editor & Aktionen --}}
    <div class="lg:col-span-2 h-max ">
      <div class=" border bg-gray-50 border-gray-300 rounded-lg  overflow-hidden">
        <div class="flex items-center justify-between px-3 py-2 border-b border-gray-300">
          <div class="flex flex-wrap items-center gap-2 text-gray-500">
              {{-- Speichern-Button (nur wenn dirty) --}}
              @if($selectedCourseDayId && $isDirty)
                  <x-buttons.button-basic
                      wire:click="save"
                      wire:target="save"
                      wire:loading.attr="disabled"
                      wire:loading.class="opacity-70 cursor-wait"
                      :size="'sm'"
                      class="px-2 relative  !text-gray-500 hover:!text-gray-700"
                      title="Entwurf speichern"
                  >
                        <i class="fad fa-save text-[16px]  sm:mr-2 text-amber-500 animate-pulse"></i>
                        <span class="hidden sm:inline">Speichern</span>
                      <div class="absolute -right-1 -top-1">
                        <span class="relative flex size-3">  <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>  <span class="relative inline-flex size-3 rounded-full bg-amber-500"></span></span>
                      </div>
                  </x-buttons.button-basic>
              @endif
              {{-- Fertigstellen-Button --}}
              @if($selectedCourseDayId && $status !== 1 && (!$isDirty))
                  <x-buttons.button-basic
                      wire:click="submit"
                      wire:target="submit"
                      wire:loading.attr="disabled"
                      wire:loading.class="opacity-70 cursor-wait"
                      :size="'sm'"
                      class="px-2 !text-gray-500 hover:!text-gray-700"
                      title="Eintrag als fertig markieren"
                  >
                      <i class="fad fa-check-circle text-[16px]   sm:mr-2 text-green-600"></i>
                      <span class="hidden sm:inline">Fertigstellen</span>
                  </x-buttons.button-basic>
              @endif
              {{-- Export-Dropdown (statt einfachem Download-Button) --}}
              <x-ui.dropdown.anchor-dropdown
                  align="left"
                  width="48"
                  dropdownClasses="mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
                  contentClasses="bg-white"
                  :overlay="false"
                  :trap="false"
                  :scrollOnOpen="false"
                  :offset="6"
              >
                  <x-slot name="trigger">
                      <x-buttons.button-basic
                          type="button"
                          :size="'sm'"
                          class="px-2 !text-gray-500"
                          title="Export-Optionen"
                      >
                          <i class="fad fa-download text-[16px]  mr-1"></i>
                          <span class="hidden sm:inline">Download</span>
                          <i class="fal fa-angle-down ml-1 text-xs"></i>
                      </x-buttons.button-basic>
                  </x-slot>
                  <x-slot name="content">
                      <div class="py-1 text-sm text-gray-700">
                          {{-- Tag-Export --}}
                          <button
                              type="button"
                              wire:click="exportReportEntry"
                              wire:target="exportReportEntry"
                              wire:loading.attr="disabled"
                              class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50 "
                          >
                              <i class="fal fa-file-pdf text-[14px] "></i>
                              <span>Tag einzeln</span>
                          </button>
                          {{-- Baustein-Export (TODO: passende Methode implementieren) --}}
                          <button
                              type="button"
                              wire:click="exportReportModule"
                              wire:target="exportReportModule"
                              wire:loading.attr="disabled"
                              class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50 "
                          >
                              <i class="fal fa-layer-group text-[14px] "></i>
                              <span>Baustein</span>
                          </button>
                          {{-- Alle Bausteine-Export (TODO: passende Methode implementieren) --}}
                          <button
                              type="button"
                              wire:click="exportReportAll"
                              wire:target="exportReportAll"
                              wire:loading.attr="disabled"
                              class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50 border-t border-gray-100 "
                          >
                              <i class="fal fa-file-archive text-[14px] "></i>
                              <span>Berichtsheft komplett</span>
                          </button>
                      </div>
                  </x-slot>
              </x-ui.dropdown.anchor-dropdown>
              {{-- Dozenten-Doku übernehmen --}}
{{-- Dozenten-Doku übernehmen --}}
@php
    $currentDay = collect($courseDays)->firstWhere('id', $selectedCourseDayId);
@endphp

@if($currentDay && $currentDay['hasTutorDoc'])
    <x-ui.dropdown.anchor-dropdown
        align="left"
        width="48"
        dropdownClasses="mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
        contentClasses="bg-white"
        :overlay="false"
        :trap="false"
        :scrollOnOpen="false"
        :offset="6"
    >
        {{-- Trigger bleibt wie bisher --}}
        <x-slot name="trigger">
            <x-buttons.button-basic
                type="button"
                :size="'sm'"
                class="px-2  text-gray-500"
                title="Dozenten-Dokumentation übernehmen"
            >
                <i class="fad fa-file-signature text-[16px]"></i>
                <span class="hidden md:inline-block ml-2">Doku</span>
            </x-buttons.button-basic>
        </x-slot>

        {{-- Dropdown-Inhalt: eigentliche Aktion --}}
        <x-slot name="content">
            <div class="py-1 text-sm text-gray-700">
                <button
                    type="button"
                    wire:click="importTutorDocToDraft"
                    wire:loading.attr="disabled"
                    @click="open = false"
                    class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50  "
                >
                    <i class="fal fa-file-import text-[14px] "></i>
                    <span>Dozenten-Doku einfügen</span>
                </button>
            </div>
        </x-slot>
    </x-ui.dropdown.anchor-dropdown>
@else
    {{-- disabled Variante bleibt ein normaler Button --}}
    <x-buttons.button-basic
        :size="'sm'"
        class="px-2 opacity-40 cursor-not-allowed !text-gray-500"
        title="Dozenten-Dokumentation noch nicht vorhanden"
    >
        <i class="fad fa-file-signature text-[16px] "></i>
        <span class="hidden md:inline-block ml-2 ">Doku</span>
    </x-buttons.button-basic>
@endif

{{-- KI-Assistent --}}
@if($reportBookEntryId)
    <x-ui.dropdown.anchor-dropdown
        align="left"
        width="48"
        dropdownClasses="mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
        contentClasses="bg-white"
        :overlay="false"
        :trap="false"
        :scrollOnOpen="false"
        :offset="6"
    >
        {{-- Trigger bleibt wie bisher --}}
        <x-slot name="trigger">
            <x-buttons.button-basic
                type="button"
                :size="'sm'"
                class="px-2 "
                title="KI-Assistent für diesen Eintrag"
            >
                <i class="fad fa-magic text-[16px] text-gray-500"></i>
                <span class="hidden md:inline-block ml-2 text-gray-500">Assistent</span>
            </x-buttons.button-basic>
        </x-slot>

        {{-- Dropdown-Inhalt: KI-Assistent öffnen --}}
        <x-slot name="content">
            <div class="py-1 text-sm text-gray-700">
                <button
                    type="button"
                    @click="$dispatch('open-reportbook-ai-assistant', [{ id: {{ $reportBookEntryId }} }]); open = false"
                    class="flex w-full items-center gap-2 px-3 py-2 hover:bg-gray-50"
                >
                    <i class="fal fa-comments-alt text-[14px] "></i>
                    <span class="">KI-Assistent öffnen</span>
                </button>
            </div>
        </x-slot>
    </x-ui.dropdown.anchor-dropdown>
@else
    {{-- disabled Variante bleibt ein normaler Button --}}
    <x-buttons.button-basic
        :size="'sm'"
        class="px-2 opacity-60 cursor-not-allowed"
        title="Assistent erst verfügbar, wenn ein Entwurf gespeichert wurde"
    >
        <i class="fad fa-magic text-[16px]"></i>
        <span class="hidden md:inline-block ml-2">Assistent</span>
    </x-buttons.button-basic>
@endif
              {{-- Optional: kleiner Loading-Hinweis direkt in der Toolbar --}}
              <span
                  wire:loading
                  wire:target="save,submit,exportReportEntry,exportReportModule,exportReportAll,importTutorDocToDraft"
                  class="text-[11px] text-gray-500 ml-1"
              >
                  Verarbeite …
              </span>
          </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border
                {{ $status === 1 
                    ? 'bg-green-50 text-green-700 border-green-200' 
                    : 'bg-slate-50 text-slate-700 border-slate-200' }}">
                Status: {{ $status >= 1 ? 'Fertig' : ($status === 0 ? 'Entwurf' : 'Fehlend') }}
            </span>
        </div>
        <div>
          <div wire:key="{{ $editorKey }}" class="relative z-20 ">
            <x-ui.editor.toast
              wireModel="text"
              placeholder="Bitte gebe hier dein Bericht für den Tag ein."
            />
          </div>
        </div>
      </div>
    </div>
  </div>
  <livewire:tools.ai.report-book-ai-assistant />

  {{-- Signatur-Formular --}} 
<livewire:tools.signatures.signature-form lazy />


</div>
