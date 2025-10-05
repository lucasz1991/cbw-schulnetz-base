<div
    x-data="{
        show: @entangle('show').live,
        printIframe() {
            const frame = this.$refs.viewer;
            if (frame && frame.contentWindow) {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            }
        }
    }"
    x-cloak
>
    <div x-show="show" x-transition.opacity class="fixed inset-0 z-[60] bg-black/60"></div>

    <div x-show="show" x-transition class="fixed inset-0 z-[70] flex items-center justify-center p-4">
        <div class="w-full max-w-6xl bg-white  rounded-2xl shadow-2xl border border-neutral-200  overflow-hidden flex flex-col">
            <div class="flex items-center justify-between px-5 py-3 border-b border-neutral-200 ">
                <h3 class="text-lg font-semibold">{{ $title }}</h3>
                <button type="button" class="w-9 h-9 rounded-full hover:bg-neutral-100 " @click="$wire.close()">✕</button>
            </div>

            <div class="relative">
                @if($previewUrl)
                    <iframe x-ref="viewer" src="{{ $previewUrl }}#toolbar=0&navpanes=0&scrollbar=1" class="w-full h-[75vh] sm:h-[80vh]" title="PDF Vorschau"></iframe>
                @else
                    <div class="p-10 text-center text-neutral-500">PDF wird vorbereitet …</div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-neutral-200 ">
                <button type="button" class="px-4 py-2 text-sm rounded-lg border border-neutral-300 hover:bg-neutral-50 " @click="$wire.close()">Schließen</button>
            </div>
        </div>
    </div>
</div>
