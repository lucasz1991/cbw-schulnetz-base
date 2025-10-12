@props([
  // exakter Livewire-Pfad, z. B. "fileUploads.123" oder "roterFadenUpload"
  'model',

  // "multi" (Default) | "single"
  'mode' => 'multi',

  // optionale DZ-Optionen
  'label' => 'Dateien hier ablegen oder klicken.',
  'acceptedFiles' => null,   // z. B. ".pdf,.png,.jpg"
  'maxFiles' => null,        // z. B. 20  (wird bei single auf 1 gesetzt)
  'maxFilesize' => null,     // z. B. 50 (MB)
])

@php
  // robustes Single-Flag (erlaubt :mode="single" od. "single")
  $isSingle = ($mode === 'single' || $mode === true || $mode === 1 || $mode === '1');
  $dzMaxFiles = $isSingle ? 1 : ($maxFiles ?? 20);
  $dzMaxFilesize = $maxFilesize ?? 15;
  $dzAccepted = $acceptedFiles; // kann null sein
@endphp

<div
  x-data="{
    dz: null,
    single: @js($isSingle),
    opts: {
      maxFiles: @js($dzMaxFiles),
      maxFilesize: @js($dzMaxFilesize),
      acceptedFiles: @js($dzAccepted),
    },

    init() {
      // Nach Server-Save (optional) UI resetten
      window.addEventListener('filepool:saved', (e) => {
        if (e?.detail?.model === @js($model)) this.resetDZ();
      });

      // multiple-Attribut am Input entsprechend Modus setzen
      this.$nextTick(() => {
        const input = this.$refs.fileInput;
        if (!input) return;
        if (this.single) input.removeAttribute('multiple');
        else input.setAttribute('multiple', 'multiple');
      });

      this.$nextTick(() => this.mountDZ());
    },

    mountDZ() {
      if (this.dz) return;
      if (!window.Dropzone) { console.error('Dropzone fehlt im Layout'); return; }
      Dropzone.autoDiscover = false;

      const el = this.$refs.dzForm;
      const input = this.$refs.fileInput; // NICHT in wire:ignore
      if (!el || !input) return;

      const previews = el.querySelector('.dz-previews') || el;

      this.dz = new Dropzone(el, {
        url: '#',                   // nur UI
        autoProcessQueue: false,
        clickable: el,
        previewsContainer: previews,
        addRemoveLinks: true,
        maxFiles: this.opts.maxFiles,
        maxFilesize: this.opts.maxFilesize,
        acceptedFiles: this.opts.acceptedFiles ?? undefined,
        chunking: true,
        chunkSize: 1000000, // 1 MB pro Chunk
      });

      // SINGLE: wenn mehr als 1 Datei gewählt → neue ersetzt alte
      this.dz.on('maxfilesexceeded', (file) => {
        if (!this.single) return;
        this.dz.removeAllFiles();
        this.dz.addFile(file);
      });

      // Datei hinzugefügt → verstecktes Input aktualisieren
      this.dz.on('addedfile', (file) => {
        const dt = new DataTransfer();

        if (this.single) {
          // exakt 1 Datei im Input
          dt.items.add(file);
        } else {
          // Multi: bestehende + neue mergen
          for (const f of input.files) dt.items.add(f);
          dt.items.add(file);
        }

        input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true })); // wichtig für Livewire
      });

      // Datei entfernt → auch aus dem Input entfernen
      this.dz.on('removedfile', (file) => {
        const dt = new DataTransfer();

        if (this.single) {
          // alles leeren
          // (Dropzone hat bereits Preview entfernt, wir leeren Input)
        } else {
          for (const f of input.files) {
            const same = f.name === file.name && f.size === file.size && f.lastModified === file.lastModified;
            if (!same) dt.items.add(f);
          }
        }

        input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    },

    resetDZ() {
      // Dropzone leeren (inkl. Previews)
      if (this.dz) this.dz.removeAllFiles(true);

      // verstecktes Input leeren + CHANGE, damit Livewire den Reset merkt
      const input = this.$refs.fileInput;
      if (!input) return;

      const empty = new DataTransfer();
      input.files = empty.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    },
  }"
  x-init="init()"
>
  <!-- Dropzone-UI -->
  <form x-ref="dzForm" class="dropzone pointer-events-auto min-h-[140px] rounded-xl border-2 border-dashed border-gray-300 bg-gray-50" wire:ignore>
    <div class="dz-message needsclick">
      <h5 class="text-gray-600 dark:text-gray-100">{{ $label }}</h5>
      @if($isSingle)
        <p class="text-xs text-gray-400">Max. 1 Datei</p>
      @endif
    </div>
    <div class="dz-previews"></div>
  </form>

  <!-- Livewire-Input (Modus-abhängig multiple) -->
  <input
    x-ref="fileInput"
    type="file"
    @if(!$isSingle) multiple @endif
    class="hidden"
    wire:model="{{ $model }}"
  >

  @error($model)
    <span class="text-sm text-red-600">{{ $message }}</span>
  @enderror
</div>
