@php
  $editorKey = 'rb-editor-'.($selectedCourseId ?? 'x').'-'.($selectedCourseDayId ?? 'x');
@endphp

<div class="w-full" >
  <div class="max-w-full grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">

    {{-- linke Spalte: Kurswahl & CourseDays --}}
    <aside class="space-y-4 lg:col-span-1" wire:loading.class="cursor-wait opacity-50 animate-pulse">
{{-- Kurswahl: Navigation oben + Trigger mit Panel --}}
<div class="bg-white border border-gray-300 rounded-lg p-3 mb-4 space-y-2" x-data="{ open:false }">

  {{-- Navigation oben --}}
  <div class="flex items-center justify-between gap-2">
    <button type="button"
            wire:click="selectPrevCourse"
            class="inline-flex items-center px-3 py-2 rounded-md border text-sm
                   border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
      <svg class="w-4 h-4 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/>
      </svg>
      Zurück
    </button>

    <button type="button"
            wire:click="selectNextCourse"
            class="inline-flex items-center px-3 py-2 rounded-md border text-sm
                   border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
      Weiter
      <svg class="w-4 h-4 ml-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
      </svg>
    </button>
  </div>

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
    <button type="button"
            @click="open = !open"
            :aria-expanded="open ? 'true' : 'false'"
            class="w-full inline-flex items-center gap-3 px-3 py-2 rounded-md border text-sm
                   border-primary-300 ring-2 ring-primary-200 bg-white hover:bg-gray-50">
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

    {{-- Panel: deine Karten + Header + Scroll --}}
    <div x-cloak x-show="open" @click.outside="open=false"
         x-transition.opacity.duration.100ms
         class="absolute z-30 mt-2 w-[min(640px,90vw)] bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
      <h4 class="bg-gray-100 text-base font-semibold text-gray-700 p-3 border-b">Meine Kurse</h4>

      <div class="max-h-[70vh] overflow-y-auto p-3 scroll-container">
        <div class="grid gap-2">
          @forelse($courses as $c)
            @php
              $active = (int)$c['id'] === (int)$selectedCourseId;

              $startFmtC = $c['planned_start_date']
                          ? \Illuminate\Support\Carbon::parse($c['planned_start_date'])->format('d.m.Y') : null;
              $endFmtC   = $c['planned_end_date']
                          ? \Illuminate\Support\Carbon::parse($c['planned_end_date'])->format('d.m.Y') : null;

              $badge2 = fn($color,$text) => "<span class=\"inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-semibold
                bg-{$color}-50 text-{$color}-700 border-{$color}-200\">{$text}</span>";
              $phaseBadge2 = $badge2($c['phase_color'], ucfirst($c['phase']));
              $ampel2      = $c['ampel'];
              $ampelBadge2 = $badge2($ampel2['color'], $ampel2['info'] ? "{$ampel2['label']} – {$ampel2['info']}" : $ampel2['label']);

              $total2    = max(0, (int)($c['days_total'] ?? 0));
              $missing2  = max(0, (int)($c['days_missing'] ?? 0));
              $draft2    = max(0, (int)($c['days_draft'] ?? 0));
              $finished2 = max(0, (int)($c['days_finished'] ?? 0));
              $sum2 = $missing2 + $draft2 + $finished2;
              if ($total2 > 0 && $sum2 !== $total2) { $draft2 = max(0, $draft2 + ($total2 - $sum2)); }
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

            <button type="button"
                    wire:click="selectCourse({{ $c['id'] }})"
                    @click="open=false"
                    class="group relative w-full text-left rounded-lg border p-3 transition-all duration-150
                           {{ $active ? 'bg-primary-600 text-white border-primary-600 shadow-sm'
                                      : 'bg-white text-gray-700 border-gray-200 hover:border-primary-300 hover:bg-gray-50' }}">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide
                             {{ $active ? 'text-primary-100' : 'text-gray-500 group-hover:text-primary-600' }}">
                  {{ $c['klassen_id'] ?? '—' }}
                </span>
              </div>

              <div class="mt-1 text-sm font-medium truncate {{ $active ? 'text-white' : 'text-gray-800 group-hover:text-primary-700' }}">
                {{ $c['title'] ?? 'Unbenannter Kurs' }}
              </div>

              @if($startFmtC || $endFmtC)
                <div class="mt-0.5 text-xs {{ $active ? 'text-primary-100' : 'text-gray-500 group-hover:text-primary-600' }}">
                  {{ $startFmtC ?? '—' }} – {{ $endFmtC ?? '—' }}
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
              <div class="absolute right-1 top-1">
                {!! $phaseBadge2 !!}
              </div>
            </button>
          @empty
            <div class="text-sm text-gray-500 p-2">Keine Kurse gefunden.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>




      {{-- CourseDays des aktiven Kurses --}}
      <div class="bg-white border border-gray-300 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Kurstage</h3>

        @if(!$courseDays)
          <div class="text-sm text-gray-500">Für diesen Kurs sind noch keine Kurstage vorhanden.</div>
        @else
          <div class="grid sm:grid-cols-2 gap-2">
              @foreach($courseDays as $d)
                @php
                  $isDay = (int)$d['id'] === (int)$selectedCourseDayId;
                  $dotColor = $d['dot']['color']; // gray | amber | blue | green
                @endphp
                <button
                  type="button"
                  wire:click="selectCourseDay({{ $d['id'] }})"
                  class="w-full flex items-center justify-between rounded-lg border p-2 text-sm
                        {{ $isDay ? 'border-primary-300 ring-2 ring-primary-200 bg-white' : 'border-gray-200 bg-white hover:bg-gray-50' }}"
                >
                  <span class="font-medium">{{ $d['label'] }}</span>
                  <span class="inline-block h-2.5 w-2.5 rounded-full bg-{{ $dotColor }}-500" title="{{ $d['dot']['title'] }}"></span>
                </button>
              @endforeach
          </div>
        @endif
      </div>


    </aside>

    {{-- rechte, große Spalte: Editor & Aktionen --}}
    <div class="lg:col-span-2 space-y-4 h-max bg-white border border-gray-300 rounded-lg p-4 overflow-hidden">
      <div class="flex items-start justify-between">
        <div class="text-sm text-gray-600">
          @if($selectedCourseId && $selectedCourseDayId)
              <div>
                <div class="mb-2">
                  <x-ui.badge.badge :color="'blue'">
                    <span class="font-semibold">
                      Tag:  {{ \Illuminate\Support\Arr::first($courseDays, fn($d) => (int)$d['id'] === (int)$selectedCourseDayId)['label'] ?? '—' }}
                    </span>
                  </x-ui.badge.badge>
                </div>
                <div class="pl-1">
                  {{ $selTitle }}
                </div>
              </div>
          @else
            <span class="text-gray-500">Bitte Kurs & Kurstag wählen.</span>
          @endif
        </div>

        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border
          {{ $status === 1 ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-slate-700 border-slate-200' }}">
          Status: {{ $status === 1 ? 'Fertig' : 'Entwurf' }}
        </span>
      </div>

      {{-- Editor --}}
      <div>
        <div wire:key="{{ $editorKey }}">
          <x-ui.editor.toast
            wireModel="text"
            height="28rem"
            placeholder="Bitte gebe hier dein Bericht für den Tag ein."
          />
        </div>
      </div>

      {{-- Aktionen --}}
      <div class="mt-1 flex items-center flex-wrap gap-2" wire:loading.class="pointer-events-none">
        {{-- Speichern nur wenn dirty und ein Kurstag gewählt ist --}}
        @if($selectedCourseDayId && $isDirty )
          <x-buttons.button-basic
            wire:click="save"
            wire:target="save"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-70 cursor-wait"
          >Speichern</x-buttons.button-basic>
        @endif

        {{-- Fertigstellen wenn (dirty ODER Draft) und Kurstag gewählt und nicht fertig --}}
        @if($selectedCourseDayId && $status !== 1 && ($isDirty || $hasDraft))
          <x-buttons.button-basic
            wire:click="submit"
            wire:target="submit"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-70 cursor-wait"
          >Fertigstellen</x-buttons.button-basic>
        @endif

        <span wire:loading wire:target="save,submit" class="text-sm text-gray-500">Verarbeite …</span>
      </div>
    </div>
  </div>
</div>
