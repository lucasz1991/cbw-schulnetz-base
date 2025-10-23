<div class="pt-6"
     x-data="{ group: 'course-{{ $course->id }}', activeId: $persist('roter-faden').as('acc-course-{{ $course->id }}') }"
     @accordion-set.window="
        if ($event.detail.group === group) activeId = $event.detail.id
     "
     @accordion-toggle.window="
        if ($event.detail.group === group) activeId = (activeId === $event.detail.id ? null : $event.detail.id)
     ">
 
  <div class="mb-8  space-y-8">
    <x-ui.dropdown.course-dropdown
      group="course-{{ $course->id }}"
      item-id="resources">
      <x-slot name="trigger">
        <div class="flex items-center space-x-3">
          <h1>Bildungsmittel</h1>
        </div>
      </x-slot>
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

        </div>
      </x-slot>
    </x-ui.dropdown.course-dropdown>

    <x-ui.dropdown.course-dropdown
      group="course-{{ $course->id }}"
      item-id="roter-faden">
        <x-slot name="trigger"><h1>Roter Faden</h1></x-slot>
        <x-slot name="content">
        <div class=" p-4 space-y-4">
            @if($roterFaden)
            <div class="md:flex items-center flex-wrap justify-between">
                <div class="text-base max-md:mb-4 flex items-center gap-2">
                    <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center">
                        <img class="w-12 h-12 object-contain"
                            src="{{ $roterFaden->icon_or_thumbnail }}"
                            alt="Datei-Icon">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium truncate">
                            {{ $roterFaden->name }}
                        </div>
                        <div class="font-medium text-xs text-gray-500">
                            {{ $roterFaden->size_formatted }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center flex-wrap gap-2">
                    <x-buttons.btn-group.btn-group>
                        <x-buttons.btn-group.btn-group-item wire:click="$set('openPreview', true)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Vorschau
                        </x-buttons.btn-group.btn-group-item>
                        <x-buttons.btn-group.btn-group-item href="{{ $roterFaden->getEphemeralPublicUrl(10) }}" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Öffnen
                        </x-buttons.btn-group.btn-group-item>
                        <x-buttons.btn-group.btn-group-item wire:click="removeRoterFaden" class="text-red-600 hover:text-red-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            Entfernen
                        </x-buttons.btn-group.btn-group-item>
                        <x-buttons.btn-group.btn-group-item wire:click="$set('openRoterFadenForm', true)">
                            <svg xmlns="http://www.w3.org/2000/svg"  class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>        
                            Ersetzen
                        </x-buttons.btn-group.btn-group-item>
                    </x-buttons.btn-group.btn-group>
                </div>
            </div>
            @else
            <div class="flex items-center justify-between">
                <p class="text-gray-600 text-sm">Noch kein „Roter Faden“ hochgeladen.</p>
                <button wire:click="$set('openRoterFadenForm', true)"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-sm">
                PDF hochladen
                </button>
            </div>
            @endif
        </div>

        {{-- Modal: Dropzone Upload (Single PDF) --}}
        <x-dialog-modal wire:model="openRoterFadenForm">
            <x-slot name="title">Roter Faden (PDF) hochladen</x-slot>
            <x-slot name="content">
            <x-ui.filepool.drop-zone :model="'roterFadenUpload'" mode="single" acceptedFiles=".pdf" :maxFilesize="30" />
            @error('roterFadenUpload')
                <span class="text-sm text-red-600">{{ $message }}</span>
            @enderror
            </x-slot>
            <x-slot name="footer">
            <div class="flex justify-end space-x-2">
                @if(!empty($roterFadenUpload)) 
                <x-button wire:click="uploadRoterFaden" 
                    wire:loading.attr="disabled">
                    Hochladen
                </x-button>
                @endif 
                <x-button wire:click="$toggle('openRoterFadenForm')" class="mr-2">Abbrechen</x-button>
            </div>
            </x-slot>
        </x-dialog-modal>

        {{-- Modal: Vorschau --}}
        <x-dialog-modal wire:model="openPreview" >
            <x-slot name="title">
            Roter Faden – Vorschau
            </x-slot>

            <x-slot name="content">
            @if($roterFaden)
                <div class="rounded-md border overflow-hidden">
                <iframe
                    key="roter-faden-preview-{{ $roterFaden->id }}-{{ $roterFaden->updated_at?->timestamp ?? $roterFaden->id }}"
                    class="w-full h-[75vh] min-h-[420px]"
                    src="{{ $roterFaden->getEphemeralPublicUrl(10) }}"
                ></iframe>
                </div>
            @else
                <p class="text-sm text-gray-600">Keine Datei vorhanden.</p>
            @endif
            </x-slot>

            <x-slot name="footer">
            <div class="flex items-center gap-2">
                @if($roterFaden)
                <a href="{{ $roterFaden->getEphemeralPublicUrl(10) }}"
                    target="_blank"
                    class="px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm">
                    In neuem Tab öffnen
                </a>
                @endif
                <x-button wire:click="$set('openPreview', false)">Schließen</x-button>
            </div>
            </x-slot>
        </x-dialog-modal>
        </x-slot>


    </x-ui.dropdown.course-dropdown>
    <x-ui.dropdown.course-dropdown
      group="course-{{ $course->id }}"
      item-id="medien">
      <x-slot name="trigger">
        <div class="flex items-center space-x-3">
          <h1>Medien</h1>
          <div class="text-blue-600 bg-white h-6 w-6 aspect-square rounded-full flex justify-center items-center">
            <span class="text-xs font-semibold">{{ $course->filePool?->files->count() ?? 0 }}</span>
          </div>
        </div>
      </x-slot>
      <x-slot name="content">
        <livewire:tools.file-pools.manage-file-pools
          :modelType="\App\Models\Course::class"
          :modelId="$course->id"
          :readOnly="false"
          lazy
        />
      </x-slot>
    </x-ui.dropdown.course-dropdown>

</div>
</div>
