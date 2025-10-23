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
      bound: false,
      ratio: Math.max(window.devicePixelRatio || 1, 1),

      setupCanvas() {
        const c = this.$refs.canvas;

        // Stelle sicher, dass das Canvas ein Layout hat (Modal kann animiert sein)
        // Warte bis zum nächsten Frame, damit getBoundingClientRect() echte Werte liefert
        this.$nextTick(() => {
          const rect = c.getBoundingClientRect();

          // CSS-Größe (sichtbar)
          const cssW = rect.width || c.clientWidth || 600;
          const cssH = rect.height || c.clientHeight || 192;

          // Physische Pixelgröße
          c.width  = Math.round(cssW * this.ratio);
          c.height = Math.round(cssH * this.ratio);

          // Kontext zurücksetzen + DPI-Transform setzen
          this.ctx = c.getContext('2d');
          this.ctx.setTransform(this.ratio, 0, 0, this.ratio, 0, 0); // statt ctx.scale(...)

          // Zeichenstil
          this.ctx.lineWidth = 2;     // in CSS-px (weil Transform aktiv ist)
          this.ctx.lineCap   = 'round';

          if (!this.bound) this.bindEvents();
        });
      },

      // Koordinaten in CSS-px (nicht multiplyen!), da der Kontext bereits transformiert ist
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

        // Touch (+ Scroll unterbinden, sonst Versatz auf mobilen Geräten)
        c.style.touchAction = 'none';
        c.addEventListener('touchstart', (e)=>{ e.preventDefault(); start(e); }, { passive:false });
        c.addEventListener('touchmove',  (e)=>{ e.preventDefault(); move(e);  }, { passive:false });
        window.addEventListener('touchend', end);

        // Resize: Inhalt behalten → erst Daten sichern, dann neu setup, dann skalierte Wiedergabe
        const onResize = () => {
          const data = this.toDataURL();        // Hi-DPI PNG
          this.setupCanvas();                   // setzt Größe/Transform neu
          // nach einem Tick zurückzeichnen, wenn ctx steht
          this.$nextTick(() => {
            const img = new Image();
            img.onload = () => {
              // Kontext ist transformiert (ratio aktiv), daher in CSS-px zurückzeichnen
              const cssW = this.$refs.canvas.width  / this.ratio;
              const cssH = this.$refs.canvas.height / this.ratio;
              this.ctx.drawImage(img, 0, 0, cssW, cssH);
            };
            img.src = data;
          });
        };
        window.addEventListener('resize', onResize);

        // Cleanup
        this.$cleanup?.(() => {
          window.removeEventListener('mouseup', end);
          window.removeEventListener('touchend', end);
          window.removeEventListener('resize', onResize);
        });

        this.bound = true;
      },

      clear() {
        const c = this.$refs.canvas;
        if (this.ctx) this.ctx.clearRect(0, 0, c.width / this.ratio, c.height / this.ratio);
      },

      toDataURL() {
        return this.$refs.canvas.toDataURL('image/png');
      }
    }"
    x-init="$nextTick(() => setupCanvas())"
    x-effect="if ($wire.get('open')) { $nextTick(() => setupCanvas()) }"
    class="space-y-3"
  >
    <p class="text-sm text-gray-700">Bitte unterschreiben Sie im Feld.</p>

    <div class="border rounded bg-gray-50 p-2">
      <!-- keine Border auf dem Canvas selbst, um Offsets zu vermeiden -->
      <canvas x-ref="canvas" class="w-full h-48 bg-white rounded block"></canvas>
    </div>

    <div class="flex items-center gap-2">
      <button type="button" class="px-2 py-1.5 rounded border" @click="clear()">Leeren</button>
      <button type="button" class="px-3 py-1.5 rounded bg-blue-600 text-white"
              @click="$wire.set('signatureDataUrl', toDataURL())">
        Unterschrift übernehmen
      </button>
    </div>

    @if($errorMsg)
      <p class="text-sm text-red-600">{{ $errorMsg }}</p>
    @endif
  </div>
</x-slot>


    <x-slot name="footer">
      <div class="flex items-center gap-2">
        <x-button wire:click="save" >Bestätigen</x-button>
        <x-button wire:click="$set('open', false)">Abbrechen</x-button>
      </div>
    </x-slot>
  </x-dialog-modal>
</div>
