<div x-data="{ openFileForm: @entangle('openFileForm') ,  }">
  <div class="flex items-center justify-between mb-4">
    <div class="flex items-stretch space-x-3" >
      @if( $filePool->files->count() > 0)
        <x-dropdown class="" :width="'min'" :align="'left'">
          <x-slot name="trigger">
            <button class="flex items-center space-x-3 px-2 py-1 text-sm bg-gray-200 text-gray-600 rounded hover:bg-gray-300">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 " stroke-width="1.5" fill="currentColor" viewBox="0 0 141.85182 139.31191"><path d="M17.51106,104.69187a17.31,17.31,0,1,0,17.34617,17.272A17.32965,17.32965,0,0,0,17.51106,104.69187Zm.053,24.83384a7.524,7.524,0,1,1,7.507-7.541A7.53272,7.53272,0,0,1,17.5641,129.52571ZM1.4458,68.61466a4.89256,4.89256,0,1,1,7.13035-6.70125l5.963,6.34393,11.86607-11.64A4.8928,4.8928,0,1,1,33.25892,63.602L17.98678,78.5835a4.8755,4.8755,0,0,1-3.41652,1.40062c-.04661,0-.09268-.01232-.13928-.01339-.05625.00188-.112.01581-.16849.01581a4.87693,4.87693,0,0,1-3.5775-1.54233Zm-.11786-55.219A4.89257,4.89257,0,0,1,8.4583,6.69439l5.9633,6.34447L26.28767,1.4002a4.89289,4.89289,0,1,1,6.85366,6.98491L17.86892,23.36448a4.87438,4.87438,0,0,1-3.41652,1.40063c-.0466,0-.09241-.01206-.139-.0134-.05652.00215-.11224.01581-.16849.01608a4.87778,4.87778,0,0,1-3.57776-1.54259ZM49.77205,10.648a5.046,5.046,0,0,1,4.9725-3.68973l81.96991-.17491a4.90122,4.90122,0,0,1,4.76035,6.09616A5.04683,5.04683,0,0,1,136.50205,16.57l-81.96991.17464A4.901,4.901,0,0,1,49.77205,10.648ZM141.5916,67.68171a5.04622,5.04622,0,0,1-4.9725,3.69054l-81.97018.17464a4.90109,4.90109,0,0,1-4.76009-6.09669,5.04608,5.04608,0,0,1,4.9725-3.68974l81.97018-.17491A4.90107,4.90107,0,0,1,141.5916,67.68171Zm.11705,54.8023a5.04653,5.04653,0,0,1-4.97276,3.69054l-81.97018.17491a4.90156,4.90156,0,0,1-4.76009-6.097,5.04628,5.04628,0,0,1,4.9725-3.68974l81.97018-.17491A4.90133,4.90133,0,0,1,141.70865,122.484Z"/></svg>
            </button>
          </x-slot>
          <x-slot name="content">
            <x-dropdown-link wire:click="selectAll" class="flex items-center gap-2">
                ✅&nbsp;&nbsp;Alle&nbsp;auswählen
            </x-dropdown-link>

            <x-dropdown-link wire:click="clearSelection" class="flex items-center gap-2">
                ❌&nbsp;&nbsp;Auswahl&nbsp;entfernen
            </x-dropdown-link>
            <x-dropdown-link wire:click="deleteMyFiles" wire:confirm="Bist du dir sicher? Das Löschen ist unwiederruflich." class="flex items-center gap-2 text-red-600">
                  🗑️&nbsp;&nbsp;Meine&nbsp;Dateien&nbsp;löschen
              </x-dropdown-link>
          </x-slot>
        </x-dropdown>
      @endif
        
        <button wire:click="$toggle('openFileForm')" class="flex items-center space-x-3 px-2 py-1 text-sm bg-gray-200 text-gray-600 rounded hover:bg-blue-500 hover:text-white">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H6a1 1 0 110-2h4V4a1 1 0 011-1z"/></svg>
          Hinzufügen
        </button>
    </div>
    <div>
      @if( $filePool->files->count() > 0)
      <x-dropdown class="" :width="'min'">
        <x-slot name="trigger">
            <button type="button" class="inline-flex items-center px-2 py-1 bg-gray-200 text-gray-600 rounded hover:bg-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor"  class="h-5 fill-current" viewBox="0 0 187.92815 188.01923"><path d="M93.74843,9.7862a6.858,6.858,0,0,1,6.86464,6.836L100.754,82.665l.05035,23.62553,16.67036-16.74134L135.8079,71.13781a6.85131,6.85131,0,0,1,9.99031.29973c2.35446,2.64455,2.02232,7.05321-.7404,9.82768l-43.391,43.57634a10.85138,10.85138,0,0,1-15.34607.03241L42.28424,81.02522a6.85148,6.85148,0,0,1,.29973-9.99054,6.3537,6.3537,0,0,1,4.26513-1.56911,7.92632,7.92632,0,0,1,5.56282,2.31027L70.36209,89.65,87.104,106.31977l-.05036-23.62554-.14089-66.04285A6.8579,6.8579,0,0,1,93.74843,9.7862M93.72754,0a16.63677,16.63677,0,0,0-16.601,16.67223l.14089,66.04286L59.31674,64.84129a17.71988,17.71988,0,0,0-12.488-5.16188,16.09921,16.09921,0,0,0-10.752,4.04625,16.63705,16.63705,0,0,0-.6975,24.23411L79.4154,131.80852a20.63737,20.63737,0,0,0,29.18625-.06241l43.39071-43.5758c6.2842-6.311,7.03741-16.58866,1.11455-23.24036a16.6374,16.6374,0,0,0-24.2341-.697L110.54017,82.64415l-.14093-66.04286A16.63672,16.63672,0,0,0,93.72754,0Z"/><path d="M170.21339,128.47a7.88583,7.88583,0,0,1,7.894,7.86053l.03482,16.25358a25.37265,25.37265,0,0,1-25.29,25.39794l-117.63344.251a25.37276,25.37276,0,0,1-25.398-25.28973L9.7862,136.68941a7.8773,7.8773,0,0,1,15.75456-.03375l.02678,12.66509a13.19329,13.19329,0,0,0,13.2067,13.15018L149.23,162.23522a13.19313,13.19313,0,0,0,13.14991-13.20616l-.02705-12.66509a7.88638,7.88638,0,0,1,7.86058-7.894m-.02094-9.78616a17.66386,17.66386,0,0,0-17.62581,17.70107l.0271,12.66509a3.3918,3.3918,0,0,1-3.38468,3.39911l-110.45572.23571a3.39243,3.39243,0,0,1-3.39964-3.38491L35.32665,136.635a17.66291,17.66291,0,0,0-17.70076-17.62581h0A17.66339,17.66339,0,0,0,0,136.71031l.03482,16.25384a35.12994,35.12994,0,0,0,35.20473,35.055l117.63348-.251a35.12978,35.12978,0,0,0,35.055-35.205l-.03455-16.25357a17.6635,17.6635,0,0,0-17.70107-17.6258Z"/></svg>
            </button>
        </x-slot>
        <x-slot name="content">
            <x-dropdown-link wire:click="downloadAll" class="flex items-center gap-2">
                📥&nbsp;&nbsp;Alle&nbsp;Dateien&nbsp;herunterladen
            </x-dropdown-link>

        <x-dropdown-link wire:click="downloadZip" class="flex items-center gap-2">
            📦&nbsp;&nbsp;Alle&nbsp;als&nbsp;ZIP&nbsp;herunterladen
        </x-dropdown-link>
        </x-slot>
      </x-dropdown>
      @endif
    </div>
  </div>
  <div class="my-8 mx-2 flex space-x-4 ">
    @forelse($filePool->files as $file)
      <div class="w-32 max-w-[48%] mb-4">

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
