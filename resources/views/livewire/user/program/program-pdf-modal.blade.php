<div x-data="{
        printIframe() {
            const frame = $refs.viewer;
            if (frame && frame.contentWindow) {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            }
        }
    }" x-cloak
>
    <x-dialog-modal wire:model="show" maxWidth="4xl">
        <x-slot name="title">
        <div class="flex flex-wrap sm:flex-nowrap items-start sm:items-center justify-between gap-2 ">
            {{-- Linke Spalte: Titel (ellipsen auf kleinen Screens) --}}
            <div class="min-w-0 flex-1">
            <span class="text-sm text-gray-800 block truncate" title="{{ $title }}">{{ $title }}</span>
            </div>

            {{-- Rechte Spalte: Actions (fixbreit) --}}
            <div class="shrink-0  flex items-center gap-2">
            <button
                wire:click="$set('show', false)"
                class="inline-flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-500 rounded-full p-2 focus:outline-none focus:ring focus:ring-gray-300"
                title="Schließen"
            >
                <i class="fas fa-times w-4 h-4 leading-none"></i>
                <span class="sr-only">Schließen</span>
            </button>
            </div>
        </div>
        </x-slot>

        <x-slot name="content">
            <div class="relative rounded-md border overflow-hidden bg-white">
                @if($previewUrl)
                     <div class="pdf-container">
                        <iframe title="PDF Vorschau"
 x-ref="viewer"                            class="w-full min-h-[60vh] max-h-[70vh]"
                                src="{{ $previewUrl }}#toolbar=0&navpanes=0&scrollbar=1"
                        ></iframe>
                    </div>
                @else
                    <div class="p-10 text-center text-neutral-500">PDF wird vorbereitet …</div>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex items-center justify-end gap-2 w-full">
                <x-secondary-button wire:click="$set('show', false)">Schließen</x-secondary-button>

            </div>
        </x-slot>
    </x-dialog-modal>
</div>