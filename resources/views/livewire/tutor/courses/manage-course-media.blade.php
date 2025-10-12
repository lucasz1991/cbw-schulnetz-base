<div class="pt-6"
     x-data="{ group: 'course-{{ $course->id }}', activeId: $persist('roter-faden').as('acc-course-{{ $course->id }}') }"
     @accordion-set.window="
        if ($event.detail.group === group) activeId = $event.detail.id
     "
     @accordion-toggle.window="
        if ($event.detail.group === group) activeId = (activeId === $event.detail.id ? null : $event.detail.id)
     ">

  <div class="mb-8">
    <x-ui.dropdown.course-dropdown
      group="course-{{ $course->id }}"
      item-id="roter-faden">
      <x-slot name="trigger"><h1>Roter Faden</h1></x-slot>
<x-slot name="content">
  <div class="rounded-xl border border-blue-100 bg-white p-4 space-y-4">

    @if($roterFaden)
      <div class="flex items-center justify-between">
        <div class="text-sm">
          <div class="font-medium">{{ $roterFaden->name }}</div>
          <div class="text-gray-500"></div>
        </div>

        <div class="flex items-center gap-2">
          {{-- Vorschau im Modal --}}
          <button
            wire:click="$set('openPreview', true)"
            class="px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm">
            Vorschau
          </button>

          {{-- Direkter Download/Öffnen in neuem Tab (optional beibehalten) --}}
          <a href="{{ $roterFaden->getEphemeralPublicUrl(10) }}" target="_blank"
             class="px-3 py-1.5 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 text-sm">
            Öffnen
          </a>

          <button wire:click="removeRoterFaden"
                  class="px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 text-sm">
            Entfernen
          </button>

          <button wire:click="$set('openRoterFadenForm', true)"
                  class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">
            Ersetzen
          </button>
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
        <x-button wire:click="uploadRoterFaden">Hochladen</x-button>
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
  </div>

  <div>
    <x-ui.dropdown.course-dropdown
      group="course-{{ $course->id }}"
      item-id="medien">
      <x-slot name="trigger">
        <div class="flex items-center space-x-3">
          <h1>Medien</h1>
          <div class="text-blue-600 bg-white h-6 w-6 aspect-square rounded-full flex justify-center items-center">
            <span class="text-xs font-semibold">{{ $course->filePool->files->count() }}</span>
          </div>
        </div>
      </x-slot>
      <x-slot name="content">
        <livewire:tools.file-pools.manage-file-pools
          :modelType="\App\Models\Course::class"
          :modelId="$course->id"
          lazy
        />
      </x-slot>
    </x-ui.dropdown.course-dropdown>
  </div>

</div>
