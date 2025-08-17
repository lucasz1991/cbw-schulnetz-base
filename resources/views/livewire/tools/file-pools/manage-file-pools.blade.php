<div x-data="{ openFileForm: @entangle('openFileForm') ,  }">
  <div class="flex items-center justify-between mb-4">
    <span></span>
    <button wire:click="$toggle('openFileForm')" class="mb-4 px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H6a1 1 0 110-2h4V4a1 1 0 011-1z"/></svg>
    </button>
  </div>

  <x-dialog-modal wire:model="openFileForm">
    <x-slot name="title">Datei-Upload</x-slot>

    <x-slot name="content">
      <x-ui.filepool.drop-zone :model="'fileUploads.'.$filePool->id" />


      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Ablaufdatum (optional)</label>
        <input type="date" wire:model="expires.{{ $filePool->id }}" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
      </div>
    </x-slot>

    <x-slot name="footer">
      <x-button wire:click="uploadFile({{ $filePool->id }})">Hochladen</x-button>
      <x-button wire:click="$toggle('openFileForm')" class="mr-2">Abbrechen</x-button>
    </x-slot>
  </x-dialog-modal>

  <div class="mt-4 flex flex-wrap gap-3">
    @forelse($filePool->files as $file)
      <x-ui.filepool.file-card :file="$file" />
    @empty
      <div class="text-sm text-gray-500">Keine Dateien vorhanden.</div>
    @endforelse
  </div>
</div>
