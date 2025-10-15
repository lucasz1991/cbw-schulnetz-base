@props([
  'wireModel' => null,
  'value' => '',     
  'height' => '24rem',
  'previewStyle' => 'tab',
  'initialEditType' => 'wysiwyg',
  'disableImages' => true,
  'placeholder' => 'Schreibe hier deine Notizen…',
])

@php
  $safeWireKey = str_replace(['.',':'], '-', $wireModel ?? 'notes');
  $id = "tui-editor-{$safeWireKey}";
  $inputId = "tui-input-{$safeWireKey}";
@endphp

<div
  x-data="{
    opts: {
      height: @js($height),
      previewStyle: @js($previewStyle),
      initialEditType: @js($initialEditType),
      disableImages: @js($disableImages),
      initialValue: @js($value ?? ''),     // ← Initialwert vom Parent
      placeholder: @js($placeholder),
    },
    editor: null,
    internalChange: false,

    waitFor(cond, t=4000, every=25){
      return new Promise(r=>{
        const start=Date.now();
        (function tick(){
          if (cond()) return r();
          if (Date.now()-start>=t) return r();
          setTimeout(tick, every);
        })();
      });
    },

    async initOnce(){
      await this.waitFor(() => window.toastui && window.toastui.Editor);

      // Hidden initial befüllen (damit Livewire sofort den Wert hat)
      if (!this.$refs.hidden.value && this.opts.initialValue) {
        this.$refs.hidden.value = this.opts.initialValue;
        this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
      }

      // Editor nur einmal beim Mount bauen (dieser Block remountet ohnehin per wire:key)
      this.editor = new toastui.Editor({
        el: this.$refs.holder,
        height: this.opts.height,
        initialEditType: this.opts.initialEditType,
        previewStyle: this.opts.previewStyle,
        placeholder: this.opts.placeholder,
        initialValue: this.$refs.hidden.value || this.opts.initialValue || '',
        usageStatistics: false,
        toolbarItems: [
          ['heading','bold','italic','strike'],
          ['hr','quote'],
          ['ul','ol','task'],
          ['table','link']
        ],
        hooks: {
          addImageBlobHook: (blob, cb) => {
            if (this.opts.disableImages) return;
          }
        }
      });

      // Editor -> Hidden
      this.editor.on('change', () => {
        this.internalChange = true;
        const html = this.editor.getHTML() || '';
        if (this.$refs.hidden.value !== html) {
          this.$refs.hidden.value = html;
          this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }
        this.$nextTick(() => this.internalChange = false);
      });

      // Hidden -> Editor (falls Livewire serverseitig setzt, z. B. Autosave)
      this.$watch(() => this.$refs.hidden.value, (nv) => {
        if (this.internalChange || !this.editor) return;
        const next = nv || '';
        const cur  = this.editor.getHTML() || '';
        if (next !== cur) {
          const sel = this.editor.getSelection();
          this.editor.setHTML(next);
          if (sel) this.editor.setSelection(sel[0], sel[1]);
        }
      });
    },
  }"
  x-init="initOnce()"
  class="tui-editor-wrapper"
>
  <style>.toastui-editor-mode-switch{display:none!important}</style>

  {{-- Hidden bleibt Livewire-gebunden --}}
  <input
    type="hidden"
    id="{{ $inputId }}"
    x-ref="hidden"
    @if($wireModel) wire:model.live.debounce.500ms="{{ $wireModel }}" @endif
  >

  {{-- Holder darf Livewire NICHT anfassen --}}
  <div
    id="{{ $id }}"
    x-ref="holder"
    class="border rounded-md overflow-hidden min-h-36"
    wire:ignore
  ></div>
</div>
