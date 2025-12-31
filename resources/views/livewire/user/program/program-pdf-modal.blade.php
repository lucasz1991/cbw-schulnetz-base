<div
    x-data="{
        previewUrl: @entangle('previewUrl'),
        downloadUrl: @entangle('downloadUrl'),
        downloadName: @entangle('downloadName'),

        printPdf() {
            const url = this.previewUrl || this.downloadUrl;
            if (!url) return;

            // Viele Browser feuern onload bei PDF nicht sauber -> fallback timer
            const tryPrint = () => {
                try { w.focus(); w.print(); } catch (e) {}
            };

            w.onload = () => tryPrint();
            setTimeout(() => tryPrint(), 700);
            setTimeout(() => tryPrint(), 1500);
        },

        downloadPdf() {
            const url = this.downloadUrl || this.previewUrl;
            if (!url) return;

            // Programmatic download 
            const a = document.createElement('a');
            a.href = url;

            // Hinweis: download-Attribut wird bei manchen PDFs/Browsern ignoriert,
            // aber in Kombi mit same-origin klappt es meist.
            if (this.downloadName) a.setAttribute('download', this.downloadName);

            document.body.appendChild(a);
            a.click();
            a.remove();
        }
    }" 
    x-cloak
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
                <button @click="printPdf()" class="inline-flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-500 rounded-full p-2 focus:outline-none focus:ring focus:ring-gray-300" title="Drucken">
                    <i class="fas fa-print w-4 h-4 leading-none"></i>
                </button>
                <button @click="downloadPdf()" class="inline-flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-500 rounded-full p-2 focus:outline-none focus:ring focus:ring-gray-300" title="Herunterladen">
                    <i class="fas fa-download w-4 h-4 leading-none"></i>
                </button>
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
            <div class="relative rounded-md border border-gray-400 overflow-hidden bg-white">
                @if($previewUrl)
                     <div class="pdf-container">
                        <iframe title="PDF Vorschau"
                                x-ref="viewer"                            
                                class="w-full min-h-[60vh] max-h-[85vh]"
                                src="{{ $previewUrl }}"
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