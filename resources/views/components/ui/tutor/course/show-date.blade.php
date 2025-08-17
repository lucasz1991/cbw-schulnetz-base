<div>
    <div class="flex  justify-between mb-4">
        <div class="flex   items-stretch rounded-md border border-gray-200 shadow-sm overflow-hidden h-max w-max">
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
        @if($selectedDay && $selectedDay->getSessions()->count() > 0)
            <livewire:tutor.courses.manage-attendance :selected-day-id="$selectedDay?->id" />
        @endif
    </div>    
    @if($selectedDay && $selectedDay->getSessions()->count() > 0)

        <div class="flex rounded-md border border-gray-300 shadow-sm overflow-hidden w-max divide-x">
            @foreach($selectedDay->getSessions() as $session)
                <button
                    type="button"
                    wire:click="$set('selectedDaySessionId', '{{ $session->id }}')"
                    class="px-2 py-1 text-sm font-medium
                        @if($session->id === $selectedDaySessionId)
                            bg-blue-400 text-white 
                        @else
                            bg-white text-gray-700 hover:bg-gray-100
                        @endif"
                >
                    {{ $session->start }} – {{ $session->end }}
                </button>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500">Keine Sessions für diesen Tag vorhanden.</p>
    @endif

    <div class="mt-8"
        x-data="{
            editTopic: false,
            hover: false,
            value: @entangle('selectedDaySessionTopic').live
        }"
        @click.outside="editTopic = false"
        @keydown.escape="editTopic = false"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
    >
        <div class="flex items-center space-x-2 mb-2">
            <h3 class="font-medium text-gray-700">Thema</h3>
            <button type="button"
                    @click.stop="editTopic = !editTopic"
                    class="text-sm text-gray-500 border rounded-md p-1 grayscale hover:grayscale-0 bg-white transition duration-200"
                    :class="{ 'opacity-100': hover || editTopic, 'opacity-0': !hover && !editTopic }">
                <span x-text="editTopic ? '💾' : '✏️'"></span>
            </button>
        </div>

        <!-- Readonly -->
        <div x-show="!editTopic" x-collapse x-cloak @dblclick="editTopic = true">
            <p x-text="value || 'Thema eintragen ...'" class="whitespace-pre-wrap text-lg text-gray-700 bg-white rounded-md p-3 border border-gray-200"
                :class="{ 'opacity-30': !value, 'opacity-100': value }"></p>
        </div>

        <!-- Editor -->
        <div x-show="editTopic" x-collapse x-cloak>
            <input type="text"
                x-model.debounce.300ms="value"
                class="border rounded-md px-3 py-2 w-full text-sm"
                placeholder="Thema eintragen ..."
                @keydown.enter.prevent="editTopic = false" />
            <p class="mt-1 text-xs text-gray-400">Enter speichert & schließt, Esc verwirft/Schließen.</p>
        </div>
    </div>


<div class="mt-6"
     x-data="{
        editNotes: false,
        value: @entangle('selectedDaySessionNotes').live,
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
      <h3 class="font-medium text-gray-700">Notizen</h3>
      <button  type="button"
              @click="editNotes = !editNotes"
              class="text-sm text-gray-500 border rounded-md p-1 grayscale hover:grayscale-0 bg-white transition duration-200 "
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
                color: black;
            }
        </style>
      <input id="notesInput" type="hidden" x-ref="notesInput" x-model="value">
      <trix-editor
        input="notesInput"
        x-ref="trix"
        class="trix-content"
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
      <div class="mt-1 text-sm text-gray-600 prose max-w-full bg-white rounded-md p-4 border border-gray-200">
          {!! $selectedDay?->getSessionNotes($selectedDaySessionId == null ? 'noch keine Notizen vorhanden' : $selectedDaySessionId) !!}
      </div>
  </div>
</div>



</div>
