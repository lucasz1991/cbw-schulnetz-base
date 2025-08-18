<div x-data="{ openFileForm: @entangle('openFileForm') ,  }">
  <div class="flex items-center justify-between mb-4">
    <div>
        <button wire:click="$toggle('openFileForm')" class=" px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H6a1 1 0 110-2h4V4a1 1 0 011-1z"/></svg>
        </button>
    </div>
    <div>
      <x-dropdown class="" :width="'min'">
        <x-slot name="trigger">
            <button type="button" class="inline-flex items-center px-2 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12 17.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                </svg>
            </button>
        </x-slot>
        <x-slot name="content">
            <x-dropdown-link wire:click="downloadAll" class="flex items-center gap-2">
                📥&nbsp;&nbsp;Alle&nbsp;Dateien&nbsp;herunterladen
            </x-dropdown-link>

        <x-dropdown-link wire:click="downloadZip" class="flex items-center gap-2">
            📦&nbsp;&nbsp;Alle&nbsp;als&nbsp;ZIP&nbsp;herunterladen
        </x-dropdown-link>

        <x-dropdown-link wire:click="deleteMyFiles" class="flex items-center gap-2 text-red-600">
            🗑️&nbsp;&nbsp;Meine&nbsp;Dateien&nbsp;löschen
        </x-dropdown-link>
        </x-slot>
    </x-dropdown>

    </div>
  </div>
  <div>
    <span>Dateien</span>
    <span class="text-gray-500">({{ $filePool->files->count() }})</span>
  </div>

  <x-dialog-modal wire:model="openFileForm">
    <x-slot name="title">Datei-Upload</x-slot>

    <x-slot name="content">
      <x-ui.filepool.drop-zone :model="'fileUploads.'.$filePool->id" />
        @error('fileUploads.'.$filePool->id)
          <span class="text-sm text-red-600">{{ $message }}</span>
        @enderror

      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Ablaufdatum (optional)</label>
        <input type="date" wire:model="expires.{{ $filePool->id }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
      </div>
    </x-slot>

    <x-slot name="footer">
        <div class="flex justify-end space-x-2">
            <x-button wire:click="uploadFile({{ $filePool->id }})">Hochladen</x-button>
            <x-button wire:click="$toggle('openFileForm')" class="mr-2">Abbrechen</x-button>
        </div>
    </x-slot>
  </x-dialog-modal>

  <div class="my-8 mx-2 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
    @forelse($filePool->files as $file)
      <x-ui.filepool.file-card :file="$file" />
    @empty
      <div class="text-sm text-gray-500">Keine Dateien vorhanden.</div>
    @endforelse
  </div>
</div>
