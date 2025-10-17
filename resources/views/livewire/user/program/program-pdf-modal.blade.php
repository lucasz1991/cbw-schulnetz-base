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
    <x-dialog-modal wire:model="show" maxWidth="4xl"
    >
        <x-slot name="title">
            <div class="flex items-center justify-between w-full">
                <span>{{ $title }}</span>
                <button  wire:click="$set('show', false)">
                    x
                </button>
            </div>
        </x-slot>

        <x-slot name="content">
            <div class="relative">
                @if($previewUrl)
                    <div wire:ignore>
                        <iframe x-ref="viewer"
                                src="{{ $previewUrl }}#toolbar=0&navpanes=0&scrollbar=1"
                                class="w-full h-[75vh] sm:h-[80vh]"
                                title="PDF Vorschau"></iframe>
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