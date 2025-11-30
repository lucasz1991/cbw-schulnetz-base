<div class="py-8 container px-5 mx-auto"
     x-data="{ group: 'course-{{ $course->id }}', activeId: $persist('roter-faden').as('acc-course-{{ $course->id }}') }"
     @accordion-set.window="if ($event.detail.group === group) activeId = $event.detail.id"
     @accordion-toggle.window="if ($event.detail.group === group) activeId = (activeId === $event.detail.id ? null : $event.detail.id)">
<x-alert>
    <p class="text-sm">
        Vorschau & Download der Bausteinmedien und des „Roten Fadens“. 
        Direkte Links sind aus Sicherheitsgründen nur kurz gültig. 
        Bei Anzeigeproblemen bitte „In neuem Tab öffnen“ nutzen oder den Dozenten kontaktieren.
    </p>
</x-alert>

  <div class="mb-8 space-y-8">

    {{-- Bildungsmittel (leer/info) --}}
    <x-ui.dropdown.course-dropdown group="course-{{ $course->id }}" item-id="resources">
      <x-slot name="trigger"><h1>Bildungsmittel</h1></x-slot>
      <x-slot name="content">
        <div class="my-4">
            <ul class="divide-y divide-gray-200 rounded-md border border-gray-200 overflow-hidden">
            @php $materials = $course->materials; @endphp
            @foreach($materials as $m)
                <li class="p-3 bg-white hover:bg-gray-50 transition">
                <div class="font-semibold text-gray-800">{{ $m['titel'] ?? '—' }}</div>
                <div class="text-sm text-gray-600">{{ $m['titel2'] ?? '' }}</div>
                <div class="text-xs text-gray-500 mt-1">
                    @if(!empty($m['verlag'])){{ $m['verlag'] }}@endif
                    @if(!empty($m['isbn'])) | ISBN: {{ $m['isbn'] }}@endif
                </div>
                </li>
            @endforeach
            </ul>
            <livewire:user.program.course.materials-acknowledgement :course="$course" lazy />

        </div>
      </x-slot>
    </x-ui.dropdown.course-dropdown>

    {{-- Roter Faden (nur Preview & Download) --}}
    <x-ui.dropdown.course-dropdown group="course-{{ $course->id }}" item-id="roter-faden">
      <x-slot name="trigger"><h1>Roter Faden</h1></x-slot>

      <x-slot name="content">
        <div class="p-4 space-y-4">
          @if($roterFaden)
            <div class="md:flex items-center flex-wrap justify-between">
              <div class="text-base max-md:mb-4 flex items-center gap-2">
                <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center">
                  <img class="w-12 h-12 object-contain" src="{{ $roterFaden->icon_or_thumbnail }}" alt="Datei-Icon">
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-medium truncate">{{ $roterFaden->name }}</div>
                  <div class="font-medium text-xs text-gray-500">{{ $roterFaden->size_formatted }}</div>
                </div>
              </div>

              <div class="flex items-center flex-wrap gap-2">
                <x-buttons.btn-group.btn-group>
                  <x-buttons.btn-group.btn-group-item 
                    @click="window.dispatchEvent(new CustomEvent('filepool-preview', { detail: { id: {{ $roterFaden->id }} } }))"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Vorschau
                  </x-buttons.btn-group.btn-group-item>

                  <x-buttons.btn-group.btn-group-item  wire:click="downloadFile({{ $roterFaden->id }})">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download
                  </x-buttons.btn-group.btn-group-item>
                </x-buttons.btn-group.btn-group>
              </div>
            </div>
          @else
            <p class="text-gray-600 text-sm">Noch kein „Roter Faden“ vorhanden.</p>
          @endif
        </div> 

      </x-slot>
    </x-ui.dropdown.course-dropdown>

    <x-ui.dropdown.course-dropdown group="course-{{ $course->id }}" item-id="medien">
      <x-slot name="trigger">
        <div class="flex items-center space-x-3">
          <h1>Medien</h1>
          <div class="text-blue-600 bg-white h-6 w-6 aspect-square rounded-full flex justify-center items-center">
            <span class="text-xs font-semibold">{{ optional($course->filePool)->files?->count() ?? 0 }}</span>
          </div>
        </div>
      </x-slot>
      <x-slot name="content">
        <livewire:tools.file-pools.manage-file-pools
          :modelType="\App\Models\Course::class"
          :modelId="$course->id"
          :readOnly="true"
          lazy
        />
      </x-slot>
    </x-ui.dropdown.course-dropdown>

  </div>

</div>
