@props([
  'wireModel' => null,           // z. B. "notes" oder "form.notes"
  'value' => '',                 // Initialwert als Fallback (falls wireModel null ist)
  'height' => '38rem',
  'previewStyle' => 'tab',
  'initialEditType' => 'wysiwyg',
  'disableImages' => true,
  'placeholder' => 'Schreibe hier deine Notizen…',
])

@php
  $safeWireKey = str_replace(['.',':'], '-', $wireModel ?? 'notes');
  $id = "tui-editor-{$safeWireKey}";
@endphp

{{-- Alpine-Factory einmalig bereitstellen (ohne Mehrfachdefinition) --}}
<script>
  window.tuiEditorFactory = window.tuiEditorFactory || function (opts) {
    return {
      opts,
      editor: null,
      internalChange: false,

      waitFor(cond, t = 4000, every = 25) {
        return new Promise((resolve) => {
          const start = Date.now();
          (function tick() {
            if (cond()) return resolve();
            if (Date.now() - start >= t) return resolve();
            setTimeout(tick, every);
          })();
        });
      },

      async initOnce() {
        await this.waitFor(() => window.toastui && window.toastui.Editor);

        // Editor initialisieren
        this.editor = new toastui.Editor({
          el: this.$refs.holder,
          height: this.opts.height,
          initialEditType: this.opts.initialEditType,
          previewStyle: this.opts.previewStyle,
          placeholder: this.opts.placeholder,
          initialValue: (this.opts.model ?? this.opts.initialValue ?? ''),
          usageStatistics: false,
          toolbarItems: [
            ['heading','bold','italic','strike'],
            ['hr','quote'],
            ['ul','ol','task'],
            ['table','link']
          ],
          hooks: {
            addImageBlobHook: (blob, cb) => {
              if (this.opts.disableImages) return; // Upload unterbinden, wenn deaktiviert
              // Hier könntest du optional deinen Upload implementieren und cb(url, 'alt') aufrufen
            }
          }
        });

        // Editor -> Livewire/Alpine (über entangled model)
        this.editor.on('change', () => {
          this.internalChange = true;
          const html = this.editor.getHTML() || '';
          if (this.opts.model !== html) this.opts.model = html;
          this.$nextTick(() => (this.internalChange = false));
        });

        // Livewire/Server -> Editor (reagiert auf Server-Updates)
        this.$watch('opts.model', (nv) => {
          if (this.internalChange || !this.editor) return;
          const next = nv || '';
          const cur  = this.editor.getHTML() || '';
          if (next !== cur) {
            const sel = this.editor.getSelection?.();
            this.editor.setHTML(next);
            if (sel) this.editor.setSelection(sel[0], sel[1]);
          }
        });
      },
    };
  };
</script>

<div
  x-data="tuiEditorFactory({
    // Wenn wireModel vorhanden, binde bidirektional; sonst lokale Einbahnstraße mit initialValue
    model: @if($wireModel) @entangle($wireModel).live @else null @endif,
    height: @js($height),
    previewStyle: @js($previewStyle),
    initialEditType: @js($initialEditType),
    disableImages: @js($disableImages),
    placeholder: @js($placeholder),
    initialValue: @js($value ?? ''),
  })"
  x-init="initOnce()"
  class="tui-editor-wrapper  text-base"
>
  <style>.toastui-editor-mode-switch{display:none!important}</style>

  {{-- Editor-Container (Livewire darf hier nicht reinfunken) --}}
  <div
    id="{{ $id }}"
    x-ref="holder"
    class="border rounded-md overflow-hidden min-h-36 text-base"
    wire:ignore
  ></div>
</div>
