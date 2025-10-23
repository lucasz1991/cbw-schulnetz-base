<div class="mt-6">
  @if($this->alreadyAcknowledged)
    <div class="rounded border border-emerald-300 bg-emerald-50 p-3 text-emerald-800 text-sm">
      ✅ Bereitstellung der Kursmaterialien wurde bestätigt.
    </div>
  @else
    <div class="rounded border p-4 bg-white">
      <p class="text-sm text-gray-700 mb-3">
        Ich bestätige den Erhalt der oben aufgeführten Bildungsmittel zu diesem Kurs.
      </p>

      <button type="button"
              class="px-3 py-1.5 rounded bg-blue-600 text-white text-sm hover:bg-blue-700"
              @click="$wire.set('open', true)">
        Jetzt bestätigen & unterschreiben
      </button>
    </div>
  @endif

  <x-dialog-modal wire:model="open" :maxWidth="'4xl'">
    <x-slot name="title">Bereitstellung bestätigen</x-slot>
<x-slot name="content">
  <div
    x-data="{
      ctx: null,
      drawing: false,
      ratio: Math.max(window.devicePixelRatio || 1, 1),
      ro: null,
      bound: false,

      syncCanvasSize() {
        const c = this.$refs.canvas;
        const rect = c.getBoundingClientRect();

        // CSS-Größe explizit
        c.style.width  = rect.width  + 'px';
        c.style.height = rect.height + 'px';

        // interne Gerätepixel
        c.width  = Math.max(1, Math.round(rect.width  * this.ratio));
        c.height = Math.max(1, Math.round(rect.height * this.ratio));

        // Kontext + DPI-Transform → Zeichnen in CSS-Pixeln
        this.ctx = c.getContext('2d');
        this.ctx.setTransform(this.ratio, 0, 0, this.ratio, 0, 0);
        this.ctx.lineWidth = 2;
        this.ctx.lineCap   = 'round';
      },

      setupCanvas() {
        this.$nextTick(() => {
          this.syncCanvasSize();

          if (!this.bound) {
            this.bindEvents();

            // Reagiert auf Größenänderungen (Modal-Open, Resize, etc.)
            this.ro = new ResizeObserver(() => {
              const snap = this.toDataURL();
              this.syncCanvasSize();
              this.$nextTick(() => {
                const img = new Image();
                img.onload = () => {
                  const c = this.$refs.canvas;
                  this.ctx.drawImage(img, 0, 0, c.width / this.ratio, c.height / this.ratio);
                };
                img.src = snap;
              });
            });
            this.ro.observe(this.$refs.canvas);
          }
        });
      },

      _pos(e) {
        const c = this.$refs.canvas;
        const rect = c.getBoundingClientRect();
        const t = e.touches?.[0] ?? e;
        return [ t.clientX - rect.left, t.clientY - rect.top ];
      },

      bindEvents() {
        const c = this.$refs.canvas;
        const start = (e) => { this.drawing = true; this.ctx.beginPath(); this.ctx.moveTo(...this._pos(e)); };
        const move  = (e) => { if (!this.drawing) return; this.ctx.lineTo(...this._pos(e)); this.ctx.stroke(); };
        const end   = () => { this.drawing = false; };

        // Maus
        c.addEventListener('mousedown', start);
        c.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);

        // Touch
        c.style.touchAction = 'none';
        c.addEventListener('touchstart', (e)=>{ e.preventDefault(); start(e); }, { passive:false });
        c.addEventListener('touchmove',  (e)=>{ e.preventDefault(); move(e);  }, { passive:false });
        window.addEventListener('touchend', end);

        // Cleanup
        this.$cleanup?.(() => {
          window.removeEventListener('mouseup', end);
          window.removeEventListener('touchend', end);
          if (this.ro) this.ro.disconnect();
        });

        this.bound = true;
      },

      clear() {
        const c = this.$refs.canvas;
        this.ctx.save();
        this.ctx.setTransform(1,0,0,1,0,0);
        this.ctx.clearRect(0, 0, c.width, c.height);
        this.ctx.restore();
      },

      toDataURL() { return this.$refs.canvas.toDataURL('image/png'); },

      hasInk() { // simple Alpha-Check, optional
        const c = this.$refs.canvas;
        const { data } = this.ctx.getImageData(0, 0, c.width, c.height);
        for (let i = 3; i < data.length; i += 4*64) { if (data[i] !== 0) return true; }
        return false;
      }
    }"
    x-init="setupCanvas(); setTimeout(() => setupCanvas(), 60)"  {{-- zweiter Sync nach Modal-Transition --}}
    class="space-y-3"
  >
    <p class="text-sm text-gray-700">Bitte unterschreiben Sie im Feld.</p>

    <div class="border rounded bg-gray-50 p-2">
      <canvas x-ref="canvas" class="block bg-white rounded w-full" style="height:12rem;"></canvas>
    </div>

    <div class="flex items-center gap-2">
      <button type="button" class="px-2 py-1.5 rounded border" @click="clear()">Leeren</button>

      {{-- Bestätigen hier im Content (Footer bleibt leer) --}}
      <button type="button"
              class="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700"
              @click="
                if (!hasInk()) { $wire.set('errorMsg','Bitte unterschreiben Sie im Feld.'); return; }
                $wire.set('errorMsg', null);
                $wire.set('signatureDataUrl', toDataURL());
                $wire.save();
              ">
        Bestätigen
      </button>

      <button type="button"
              class="px-3 py-1.5 rounded border"
              @click="$wire.set('open', false)">
        Abbrechen
      </button>
    </div>

    @if($errorMsg)
      <p class="text-sm text-red-600">{{ $errorMsg }}</p>
    @endif
  </div>
</x-slot>





    <x-slot name="footer">
    </x-slot>
  </x-dialog-modal>
</div>
