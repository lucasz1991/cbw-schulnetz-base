<div
    x-data="{
        mode:'draw',
        ctx:null,
        drawing:false,
        ratio:Math.max(window.devicePixelRatio||1,1),
        bound:false,
        openState: @entangle('open').live,

        sync(){
            const c = this.$refs.c;
            if (!c) return;

            // Breite vom umgebenden Container nehmen (die graue Box)
            const container = c.parentElement;
            const availableWidth = container ? container.clientWidth : c.getBoundingClientRect().width;

            if (!availableWidth) return;

            // 16:9 Verhältnis
            const cssWidth  = availableWidth;
            const cssHeight = availableWidth * 9 / 16;

            // CSS-Größe (sichtbar)
            c.style.width  = cssWidth + 'px';
            c.style.height = cssHeight + 'px';

            // interne Pixelgröße für HiDPI
            c.width  = Math.round(cssWidth * this.ratio);
            c.height = Math.round(cssHeight * this.ratio);

            this.ctx = c.getContext('2d');
            this.ctx.setTransform(this.ratio,0,0,this.ratio,0,0);
            this.ctx.lineWidth = 2;
            this.ctx.lineCap   = 'round';
        },

        pos(e){
            const c = this.$refs.c;
            if (!c) return [0,0];
            const rect = c.getBoundingClientRect();
            const t = e.touches?.[0] ?? e;
            return [t.clientX - rect.left, t.clientY - rect.top];
        },

        bind(){
            const c = this.$refs.c;
            if (!c || this.bound) return;

            const start = e => { this.drawing = true; this.ctx.beginPath(); this.ctx.moveTo(...this.pos(e)); };
            const move  = e => { if(!this.drawing) return; this.ctx.lineTo(...this.pos(e)); this.ctx.stroke(); };
            const end   = () => { this.drawing = false; };

            c.addEventListener('mousedown', start);
            c.addEventListener('mousemove', move);
            window.addEventListener('mouseup', end);

            c.style.touchAction = 'none';
            c.addEventListener('touchstart', e => { e.preventDefault(); start(e); }, { passive:false });
            c.addEventListener('touchmove',  e => { e.preventDefault(); move(e);  }, { passive:false });
            window.addEventListener('touchend', end);

            this.bound = true;
        },

        clear(){
            const c = this.$refs.c;
            if (!c || !this.ctx) return;
            this.ctx.setTransform(1,0,0,1,0,0);
            this.ctx.clearRect(0,0,c.width,c.height);
            this.ctx.setTransform(this.ratio,0,0,this.ratio,0,0);
        },

        hasInk(){
            const c = this.$refs.c;
            if (!c || !this.ctx) return false;
            if (c.width === 0 || c.height === 0) return false;
            const {data} = this.ctx.getImageData(0,0,c.width,c.height);
            for(let i=3; i<data.length; i+=4*64){
                if(data[i] !== 0) return true;
            }
            return false;
        },

        data(){
            const c = this.$refs.c;
            if (!c || c.width === 0 || c.height === 0) return null;
            return c.toDataURL('image/png');
        },

        initCanvas(){
            this.$nextTick(() => {
                setTimeout(() => {
                    const c = this.$refs.c;
                    if (!c) return;

                    this.sync();
                    this.bind();
                }, 100); // an deine Dialog-Animation anpassen
            });
        }
    }"
 
    
>
    <x-dialog-modal wire:model="open" :maxWidth="'4xl'" :trapClose="true">
        <x-slot name="title">
            {{ $label ?? 'Unterschrift' }}
        </x-slot>

        <x-slot name="content">
            <div class="space-y-4">
                <p class="text-sm text-gray-700">
                    {!! $this->defaultConfirmText !!}
                </p>
<div class="flex items-center gap-4 justify-center mb-2">
    <div class="inline-flex rounded-md overflow-hidden border border-gray-300 text-sm">
        <!-- Draw -->
        <button
            type="button"
            class="px-4 py-1.5 flex items-center gap-1 transition"
            :class="mode === 'draw'
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white text-gray-700 hover:bg-gray-50'"
            @click="
                mode = 'draw';
                initCanvas();
            "
        >
            <i class="fad fa-pen mr-2"></i>
            Zeichnen
        </button>
    
        <!-- Upload -->
        <button
            type="button"
            class="px-4 py-1.5 flex items-center gap-1 border-l border-gray-300 transition"
            :class="mode === 'upload'
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white text-gray-700 hover:bg-gray-50'"
            @click="mode = 'upload'"
        >
            <i class="fad fa-upload mr-2"></i>
            Hochladen
        </button>
    </div>
</div>

                
                <div x-show="mode === 'draw'" x-cloak class="space-y-3">
                    <div class="border bg-gray-50 p-2 rounded w-full">
                        {{-- keine feste Höhe, Breite läuft über JS --}}
                        <div class="aspect-video w-full relative"
                            x-init="
                                    // Wenn 'open' von Livewire auf true springt und wir im draw-Modus sind -> Canvas initialisieren
                                    setTimeout(() => {
                                        initCanvas();
                                    }, 200);
                                    $watch('openState', value => {
                                        if (value && mode === 'draw') {
                                            initCanvas();
                                        }
                                    });
                                ">
                            <canvas x-ref="c" class="bg-white rounded w-full" wire:ignore></canvas>
                        </div>
                    </div>
                </div>

<div x-show="mode === 'upload'" x-cloak class="space-y-3">

    <x-ui.filepool.drop-zone
        :model="'upload'"
        mode="single"
        acceptedFiles="image/*"
        :maxFilesize="4" 
    />

    <div wire:loading wire:target="upload" class="text-xs mt-1 text-gray-500">
        Upload läuft…
    </div>
</div>


                @if($errorMsg)
                    <p class="text-sm text-red-600">{{ $errorMsg }}</p>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex justify-between items-center w-full gap-3">
                <div></div>

                <div class="flex gap-2">
                    {{-- Buttons für Canvas-Modus --}}
                    <template x-if="mode === 'draw'">
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="px-2 py-1.5 rounded border text-sm"
                                @click="clear()"
                            >
                                Leeren
                            </button>

                            <button
                                type="button"
                                class="px-3 py-1.5 rounded bg-blue-600 text-white text-sm hover:bg-blue-700"
                                @click="
                                    if(!hasInk()){
                                        clear();
                                        $wire.set('errorMsg','Bitte unterschreiben Sie im Feld.');
                                        return;
                                    }
                                    $wire.set('errorMsg', null);
                                    $wire.set('upload', null);
                                    $wire.set('signatureDataUrl', data());
                                    $wire.save();
                                "
                            >
                                Speichern
                            </button>

                            <button
                                type="button"
                                class="px-3 py-1.5 rounded border text-sm"
                                wire:click="cancel"
                            >
                                Abbrechen
                            </button>
                        </div>
                    </template>

                    {{-- Buttons für Upload-Modus --}}
                    <template x-if="mode === 'upload'">
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="px-3 py-1.5 rounded bg-blue-600 text-white text-sm hover:bg-blue-700"
                                wire:click="save"
                            >
                                Speichern
                            </button>

                            <button
                                type="button"
                                class="px-3 py-1.5 rounded border text-sm"
                                wire:click="cancel"
                            >
                                Abbrechen
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </x-slot>
    </x-dialog-modal>
</div>
