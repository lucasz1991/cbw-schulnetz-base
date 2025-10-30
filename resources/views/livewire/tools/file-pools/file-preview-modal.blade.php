<div
  x-data="{ bust: 0 }"
  x-on:filepool-preview.window="$wire.openWith($event.detail.id); bust = Date.now()"
>
  <x-dialog-modal wire:model="open" :maxWidth="'4xl'">
    <x-slot name="title">
        @if($file && $open)
        <div class="text-sm text-gray-800 truncate  mb-1" title="{{ $file->name }}">{{ $file->name }}</div>
        <div class="text-xs text-gray-500 truncate w-full mb-1" title="{{ $file->getMimeTypeForHumans() }}">
            <span>{{ $file->getMimeTypeForHumans() }}</span>
        </div>
        <div class="text-xs text-gray-500 ">
            <span>{{ number_format($file->size / 1024, 1) }} KB</span>
        </div>
        @else
        <span class="font-semibold">Dateivorschau</span>
        @endif
    </x-slot>

    <x-slot name="content">
      @if($file && $open)
        @php
          $mime    = $file->mime_type ?? '';
          $isImage = $mime && str_starts_with($mime, 'image/');
          $isVideo = $mime && str_starts_with($mime, 'video/');
          $isAudio = $mime && str_starts_with($mime, 'audio/');
          $isPdf   = $mime && str_contains($mime, 'pdf');
          $isText  = $mime && str_contains($mime, 'text');
          // Ephemerale URL (10 Min). Wird unten zusätzlich mit Cache-Buster kombiniert.
          $tempUrl = $this->url;
        @endphp

        <div class="rounded-md border overflow-hidden bg-white">
          {{-- Bilder --}}
          @if($isImage)
            <div class="img-container">
                <img
                  class="block w-full h-auto"
                  src="{{ $tempUrl }}"
                  alt="{{ $file->name_with_extension ?? $file->name }}"
                />
            </div>

          {{-- Videos --}}
          @elseif($isVideo)
            <div class="video-container">
              <video
                class="block w-full h-[75vh] min-h-[420px]"
                controls
                src="{{ $tempUrl }}"
              ></video>
            </div>

          {{-- Audio --}}
          @elseif($isAudio)
            <div class="audio-container p-4">
              <audio class="w-full" controls
                     src="{{ $tempUrl }}"></audio>
            </div>

          {{-- PDF & sonstiger Text/HTML --}}
          @elseif($isPdf || $isText)
            <div class="pdf-container">
              <iframe
                key="file-preview-{{ $file->id }}-{{ $file->updated_at?->timestamp ?? $file->id }}"
                class="w-full h-[75vh] min-h-[420px]"
                src="{{ $tempUrl }}"
              ></iframe>
            </div>

          {{-- Fallback --}}
          @else
            <div class="p-6 flex items-center justify-between gap-4">
              <div class="flex items-center gap-3 min-w-0">
                <img class="w-10 h-10 object-contain"
                     src="{{ $file->icon_or_thumbnail }}"
                     alt="Datei-Icon">
                <div class="min-w-0">
                  <div class="font-medium text-gray-900 truncate">
                    {{ $file->name_with_extension ?? $file->name }}
                  </div>
                  @if($mime)
                    <div class="text-xs text-gray-500 mt-0.5">{{ $mime }}</div>
                  @endif
                  <div class="text-xs text-gray-500">
                    Keine Inline-Vorschau verfügbar. Bitte im neuen Tab öffnen.
                  </div>
                </div>
              </div>
            </div>
          @endif
        </div>
      @else
        <p class="text-sm text-gray-600">Keine Datei ausgewählt.</p>
      @endif
    </x-slot>
        <x-slot name="footer">
        <div class="flex items-center gap-2">
            @if($file)
            <x-buttons.button-basic
                :mode="'secondary'"
                href="{{ $file->getEphemeralPublicUrl() }}"
                target="blank"
            >
                In neuem Tab öffnen
            </x-buttons.button-basic>


            @endif

            {{-- Info-Button: Modal schließen --}}
            <x-buttons.button-basic
            :mode="'info'"
            wire:click="close"
            >
            Schließen
            </x-buttons.button-basic>
        </div>
        </x-slot>

  </x-dialog-modal>
</div>
