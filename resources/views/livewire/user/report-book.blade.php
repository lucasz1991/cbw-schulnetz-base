@php
  $editorKey = 'rb-editor-'.($selectedCourseId ?? 'x').'-'.($selectedCourseDayId ?? 'x');
@endphp

<div class="w-full">
  <div class="max-w-full grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">

    {{-- linke Spalte: Kurswahl & CourseDays --}}
    <aside class="space-y-4 lg:col-span-1">
      {{-- Kurse des Users --}}
      <div class="bg-white border border-gray-300 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Meine Kurse</h3>
        <div class="flex flex-wrap gap-2">
          @forelse($courses as $c)
            @php $active = (int)$c['id'] === (int)$selectedCourseId; @endphp
<button
  type="button"
  wire:click="selectCourse({{ $c['id'] }})"
  class="group w-full text-left rounded-lg border p-3 transition-all duration-150
         {{ $active
            ? 'bg-primary-600 text-white border-primary-600 shadow-sm'
            : 'bg-white text-gray-700 border-gray-200 hover:border-primary-300 hover:bg-gray-50'
         }}"
>
  <div class="flex items-center justify-between">
    {{-- Kurs-ID / Klassenkennung --}}
    <span class="text-xs font-semibold uppercase tracking-wide
                 {{ $active ? 'text-primary-100' : 'text-gray-500 group-hover:text-primary-600' }}">
      {{ $c['klassen_id'] ?? '—' }}
    </span>

    {{-- Optional: Pfeil beim aktiven Kurs --}}
    @if($active)
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7" />
      </svg>
    @endif
  </div>

  {{-- Kurs-Titel --}}
  <div class="mt-1 text-sm font-medium truncate
              {{ $active ? 'text-white' : 'text-gray-800 group-hover:text-primary-700' }}">
    {{ $c['title'] ?? 'Unbenannter Kurs' }}
  </div>

  {{-- Zeitraum (von–bis) --}}
  @php
    $start = isset($c['planned_start_date'])
        ? \Illuminate\Support\Carbon::parse($c['planned_start_date'])->format('d.m.Y')
        : null;
    $end = isset($c['planned_end_date'])
        ? \Illuminate\Support\Carbon::parse($c['planned_end_date'])->format('d.m.Y')
        : null;
  @endphp

  @if($start || $end)
    <div class="mt-0.5 text-xs font-normal 
                {{ $active ? 'text-primary-100' : 'text-gray-500 group-hover:text-primary-600' }}">
      {{ $start ?? '—' }} – {{ $end ?? '—' }}
    </div>
  @endif
</button>


          @empty
            <div class="text-sm text-gray-500">Keine Kurse gefunden.</div>
          @endforelse
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
              @php $isDay = (int)$d['id'] === (int)$selectedCourseDayId; @endphp
              <button
                type="button"
                wire:click="selectCourseDay({{ $d['id'] }})"
                class="w-full text-left rounded-lg border p-2 text-sm
                       {{ $isDay ? 'border-primary-300 ring-2 ring-primary-200 bg-white' : 'border-gray-200 bg-white hover:bg-gray-50' }}"
              >
                <div class="font-medium">{{ $d['label'] }}</div>
              </button>
            @endforeach
          </div>
        @endif
      </div>

      {{-- Letzte Einträge für dieses ReportBook (falls vorhanden) --}}
      <div class="bg-white border border-gray-300 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Letzte Einträge</h3>
        <div class="space-y-2">
          @forelse($recent as $r)
            <div class="rounded-lg border border-gray-200 p-3 bg-white">
              <div class="flex items-center justify-between">
                <div class="text-xs font-semibold text-gray-700">
                  {{ \Illuminate\Support\Carbon::parse($r['date'])->format('d.m.Y') }}
                </div>
                <span class="text-[10px] px-2 py-0.5 rounded border
                  {{ $r['status'] === 1 ? 'bg-green-50 text-green-700 border-green-200' : 'bg-slate-50 text-slate-700 border-slate-200' }}">
                  {{ $r['status'] === 1 ? 'Fertig' : 'Entwurf' }}
                </span>
              </div>
              @if(!empty($r['title']))
                <div class="text-xs font-semibold text-gray-700 mt-1">{{ $r['title'] }}</div>
              @endif
              <div class="text-xs text-gray-600 mt-0.5 line-clamp-2">{{ $r['excerpt'] }}</div>
            </div>
          @empty
            <div class="text-sm text-gray-500">Noch keine Einträge.</div>
          @endforelse
        </div>
      </div>
    </aside>

    {{-- rechte, große Spalte: Editor & Aktionen --}}
    <div class="lg:col-span-2 space-y-4 h-max bg-white border border-gray-300 rounded-lg p-4 overflow-hidden">
      <div class="flex items-start justify-between">
        <div class="text-sm text-gray-600">
          @if($selectedCourseId && $selectedCourseDayId)
            Kurs #{{ $selectedCourseId }} · Tag:
            <span class="font-semibold">
              {{ \Illuminate\Support\Arr::first($courseDays, fn($d) => (int)$d['id'] === (int)$selectedCourseDayId)['label'] ?? '—' }}
            </span>
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
        <x-ui.forms.label value="Inhalt"/>
        <div wire:key="{{ $editorKey }}">
          <x-ui.editor.toast
            wireModel="text"
            height="28rem"
            placeholder="Was wurde heute gemacht? (Übernahme aus Dozenten-Dokumentation ist später erweiterbar)"
          />
        </div>
      </div>

      {{-- Aktionen --}}
      <div class="mt-1 flex items-center flex-wrap gap-2">
        {{-- Speichern nur wenn dirty und ein Kurstag gewählt ist --}}
        @if($selectedCourseDayId && $isDirty && $status !== 1)
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
