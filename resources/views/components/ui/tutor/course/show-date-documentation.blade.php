<div class="transition-opacity duration-300" wire:loading.class="opacity-30">
    <div class="flex  max-md:flex-wrap  justify-between mb-4">
        <div class="flex space-x-3">
          <div class="flex   items-stretch rounded-md border border-gray-200 shadow-sm overflow-hidden h-max w-max max-md:mb-4">
              <!-- zurück (minus) -->
               @if($selectPreviousDayPossible)
              <button
                  type="button"
                  wire:click="selectPreviousDay"
                  class="px-4 py-2  text-sm text-white   bg-blue-400 hover:bg-blue-700 "
              >
                  <svg class="h-3 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 8 14">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 1 1.3 6.326a.91.91 0 0 0 0 1.348L7 13"></path>
                  </svg>
              </button>
              @endif
              <span class="bg-blue-200 text-blue-800 text-lg font-medium px-2.5 py-0.5 ">
                  {{ $selectedDay?->date?->format('d.m.Y') }}
              </span>
  
              <!-- vorwärts (plus) -->
              @if($selectNextDayPossible)
              <button
                  type="button"
                  wire:click="selectNextDay"
                  class="px-4 py-2 bg-blue-400 text-sm text-white hover:bg-blue-700"
              >
                  <svg class="h-3 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 8 14">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 13 5.7-5.326a.909.909 0 0 0 0-1.348L1 1"></path>
                  </svg>
              </button>
              @endif
          </div>
          <div>
            <button
                type="button"
                class="text-sm text-gray-500 border rounded-md px-1 py-1 bg-white  shadow-sm"
                :class="{
                    'hover:bg-green-100 hover:text-green-300': !showSelectDayCalendar,
                    'hover:bg-red-100 hover:text-red-300': showSelectDayCalendar
                }"
                @click="showSelectDayCalendar = !showSelectDayCalendar"
            >
            <svg class="h-6 w-6 inline-block" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM160 304L160 336C160 344.8 167.2 352 176 352L208 352C216.8 352 224 344.8 224 336L224 304C224 295.2 216.8 288 208 288L176 288C167.2 288 160 295.2 160 304zM288 304L288 336C288 344.8 295.2 352 304 352L336 352C344.8 352 352 344.8 352 336L352 304C352 295.2 344.8 288 336 288L304 288C295.2 288 288 295.2 288 304zM432 288C423.2 288 416 295.2 416 304L416 336C416 344.8 423.2 352 432 352L464 352C472.8 352 480 344.8 480 336L480 304C480 295.2 472.8 288 464 288L432 288zM160 432L160 464C160 472.8 167.2 480 176 480L208 480C216.8 480 224 472.8 224 464L224 432C224 423.2 216.8 416 208 416L176 416C167.2 416 160 423.2 160 432zM304 416C295.2 416 288 423.2 288 432L288 464C288 472.8 295.2 480 304 480L336 480C344.8 480 352 472.8 352 464L352 432C352 423.2 344.8 416 336 416L304 416zM416 432L416 464C416 472.8 423.2 480 432 480L464 480C472.8 480 480 472.8 480 464L480 432C480 423.2 472.8 416 464 416L432 416C423.2 416 416 423.2 416 432z"/></svg>
            </button>
          </div>
        </div>
    </div>    





<div class="mt-6"
     x-data="{
        editNotes: false,
        value: @entangle('dayNotes').live,
        hover: false,
        // Lokaler Edit-Guard & gespeicherte Selection
        isLocal: false,
        savedSel: [0,0],

        init(){
          // Externe Änderungen (z. B. Session-Wechsel) -> in Editor spiegeln
          this.$watch('value', () => this.syncFromWire())

          // Uploads unterbinden (optional)
          this.$nextTick(() => {
            this.$refs.wrap.addEventListener('trix-file-accept', e => e.preventDefault())
          })

          // Beim Öffnen Editor mit aktuellem Wert füllen + Fokus
          this.$watch('editNotes', (on) => {
            if(on){
              this.$nextTick(() => {
                const html = this.value ?? ''
                this.pushHidden(html)
                this.loadIntoEditor(html, {keepCursorEnd: true})
                this.$refs.trix.focus()
              })
            }
          })
        },

        // --- Helpers ---
        currentText(){
          // Text-Repräsentation ohne Markup, für Minimal-Diff-Vergleich
          return this.$refs.trix?.editor?.getDocument().toString() ?? ''
        },
        pushHidden(html){
          if (this.$refs.notesInput.value !== html) {
            this.$refs.notesInput.value = html
            this.$refs.notesInput.dispatchEvent(new Event('input', {bubbles:true}))
          }
        },
        saveSelection(){
          if(!this.$refs.trix) return
          this.savedSel = this.$refs.trix.editor.getSelectedRange()
        },
        restoreSelection(){
          if(!this.$refs.trix) return
          this.$refs.trix.editor.setSelectedRange(this.savedSel)
        },
        loadIntoEditor(html, {keepCursorEnd = false} = {}){
          if(!this.$refs.trix) return
          // Auswahl sichern, außer bei erstem Öffnen wo wir ans Ende wollen
          if(!keepCursorEnd) this.saveSelection()
          this.$refs.trix.editor.loadHTML(html)
          // Auswahl wiederherstellen
          if(keepCursorEnd){
            const len = this.$refs.trix.editor.getDocument().toString().length
            this.$refs.trix.editor.setSelectedRange(len)
          }else{
            this.restoreSelection()
          }
        },

        // Nur externe Änderungen syncen (nicht während lokale Eingaben laufen)
        syncFromWire(){
          if(this.isLocal || !this.$refs.trix) return
          const html = this.value ?? ''
          // minimaler Vergleich: Nur neu laden, wenn Inhalt tatsächlich anders
          const incomingText = (new DOMParser().parseFromString(html || '', 'text/html').body.textContent) || ''
          if (this.currentText() !== incomingText){
            this.pushHidden(html)
            this.loadIntoEditor(html) // Auswahl wird gesichert & wiederhergestellt
          }
        }
     }"
    @click.away="editNotes = false"
    @keydown.escape="editNotes = false"
    @mouseenter="hover = true" 
    @mouseleave="hover = false"
>
  <div class="flex items-center space-x-2 mb-2" x-ref="wrap">
      <button  type="button"
              @click="editNotes = !editNotes"
              class="text-sm text-gray-700 border rounded-md p-1 grayscale hover:grayscale-0 bg-white transition duration-200 "
              :class="{'opacity-100 ': hover || editNotes, 'opacity-0': !hover && !editNotes }"
              >
          <span x-text="editNotes ? '💾' : '✏️'"></span>
      </button>
  </div>

  {{-- Editor --}}
  <div x-show="editNotes" x-collapse x-cloak wire:ignore>
        <style>
          /* Trix Editor Styles */
            .trix-content {
                background-color: #fff;
            }
            trix-toolbar .trix-button{
                background: #fff;
                color: #ddd;
                font-size: 14px;
                padding: 0.4rem 0.6rem;
            }
            trix-toolbar .trix-button-group {
                border-radius: 0.375rem;
                border: 1px solid #eee;
                overflow: hidden;
            }
            trix-toolbar .trix-button-group.trix-button-group--file-tools {
                display: none; /* Hide file tools */
            }
        </style>
      <input id="notesInput" type="hidden" x-ref="notesInput" x-model="value">
      <trix-editor
        input="notesInput"
        x-ref="trix"
        class="trix-content min-h-96 "
        placeholder=""
        @trix-change="
          // Lokales Tippen -> Livewire updaten, aber SyncFromWire blocken
          isLocal = true;
          value = $event.target.value;
          // kurz später wieder freigeben (nächster Tick)
          $nextTick(() => { isLocal = false })
        "
      ></trix-editor>
  </div>

  {{-- Readonly --}}
  <div x-show="!editNotes" x-collapse x-cloak @dblclick="editNotes = true">
      <div class="mt-1 text-sm text-gray-600 prose max-w-full bg-white rounded-md p-4 border border-gray-200 min-h-96 @if($selectedDay?->notes == '') opacity-50 @endif">
          {!! $selectedDay?->notes != '' ? $selectedDay?->notes : 'noch keine Notizen vorhanden' !!}
      </div>
  </div>
</div>



</div>
