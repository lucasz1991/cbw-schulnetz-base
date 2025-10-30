<div x-data="{ openFileForm: @entangle('openFileForm') ,  }">
  <div class="flex items-center justify-between mb-4">
    <div class="flex items-stretch space-x-3">
        @if(!$readOnly)
          <button wire:click="$toggle('openFileForm')" class="flex items-center space-x-3 px-2 py-1 text-sm bg-gray-200 text-gray-600 rounded hover:bg-blue-500 hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H6a1 1 0 110-2h4V4a1 1 0 011-1z"/></svg>
            Hinzufügen
          </button>
        @endif
    </div>
    <div>
      @if( $filePool->files->count() > 0)
      <x-dropdown class="" :width="'min'">
        <x-slot name="trigger">
            <button type="button" class="inline-flex items-center px-2 py-2 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">
                <i class="fad fa-download fa-lg h-5 w-5"></i>
            </button>
        </x-slot>
        <x-slot name="content">
          <x-dropdown-link wire:click="downloadAll" class="flex items-center gap-2">
              <i class="fad fa-file-archive  fa-lg"></i>&nbsp;&nbsp;Alle&nbsp;Dateien&nbsp;herunterladen
          </x-dropdown-link>
        </x-slot>
      </x-dropdown>
      @endif
    </div>
  </div>
  <div class="my-8 mx-2 flex flex-wrap">
    @forelse($filePool->files as $file)
      <div class="w-32  mb-4 mr-4">
        <x-ui.filepool.file-card :file="$file" />
      </div>
    @empty
      <div class="text-sm text-gray-500">Keine Dateien vorhanden.</div>
    @endforelse
  </div>
  {{-- FileForm Modal --}}
  <x-dialog-modal wire:model="openFileForm">
    <x-slot name="title">Datei-Upload</x-slot>
    <x-slot name="content">
      <x-ui.filepool.drop-zone :model="'fileUploads.'.$filePool->id" />
        @error('fileUploads.'.$filePool->id)
          <span class="text-sm text-red-600">{{ $message }}</span>
        @enderror
      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Ablaufdatum</label>
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
  {{-- EditFileForm Modal --}}
  <x-dialog-modal wire:model="openEditFileForm">
    <x-slot name="title">Datei bearbeiten </x-slot>
    <x-slot name="content">
      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Dateiname</label>
        <input type="text" wire:model="selectedFileName" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
      </div>
      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Ablaufdatum</label>
        <input type="date" wire:model="selectedFileExpiresDate" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
      </div>
    </x-slot>
    <x-slot name="footer">
        <div class="flex justify-end space-x-2">
            <x-button wire:click="safeFile()">speichern</x-button>
            <x-button wire:click="$toggle('openEditFileForm')" class="mr-2">Abbrechen</x-button>
        </div>
    </x-slot>
  </x-dialog-modal>



</div>
