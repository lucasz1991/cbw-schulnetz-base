@php
    use Illuminate\Support\Carbon;
    use App\Models\CourseDay;

    $isToday = $selectedDay?->date ? Carbon::parse($selectedDay->date)->isToday() : false;
@endphp

<div class="transition-opacity duration-300" wire:key="doc-panel-{{ $selectedDayId }}">
  <div class="flex max-md:flex-wrap items-center space-x-3 justify-between mb-8">
    <div class="flex justify-between items-center space-x-3 w-full">
      <div class="flex items-center gap-2">
        {{-- Navigation Tag zurück / vor --}}
        <div class="flex items-stretch rounded-md border border-gray-200 shadow-sm overflow-hidden h-max w-max max-md:mb-4">
          @if($selectPreviousDayPossible)
            <button type="button" wire:click="selectPreviousDay"
                    class="px-4 py-2 text-sm text-white bg-blue-400 hover:bg-blue-700">
              <svg class="h-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 8 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 1 1.3 6.326a.91.91 0 0 0 0 1.348L7 13"/>
              </svg>
            </button>
          @endif

          <span class="bg-blue-200 text-blue-800 text-lg font-medium px-2.5 py-0.5">
            {{ $selectedDay?->date?->format('d.m.Y') }}
          </span>

          @if($selectNextDayPossible)
            <button type="button" wire:click="selectNextDay"
                    class="px-4 py-2 bg-blue-400 text-sm text-white hover:bg-blue-700">
              <svg class="h-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 8 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 13 5.7-5.326a.909.909 0 0 0 0-1.348L1 1"/>
              </svg>
            </button>
          @endif
        </div>

        @if($isToday)
          <span class="h-max rounded-lg bg-green-100 border border-green-700 text-green-700 text-xs px-1.5 py-0.5 shadow"
                title="Heutiger Tag">Heute</span>
        @endif
      </div>

      <div class="hidden md:flex items-center gap-3">
        {{-- Kalender-Toggle --}}
        <button type="button"
                @click="showSelectDayCalendar = !showSelectDayCalendar"
                class="inline-flex items-center gap-2 text-sm border rounded-md px-2 py-1 bg-white shadow-sm transition"
                :class="showSelectDayCalendar ? 'hover:bg-blue-100 hover:text-gray-600 border-blue-200' : 'hover:bg-blue-100 hover:text-blue-600 border-blue-200'">
          <svg class="h-5 w-5 text-gray-500" fill="currentColor" viewBox="0 0 640 640" aria-hidden="true">
            <path d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM160 304L160 336C160 344.8 167.2 352 176 352L208 352C216.8 352 224 344.8 224 336L224 304C224 295.2 216.8 288 208 288L176 288C167.2 288 160 295.2 160 304zM288 304L288 336C288 344.8 295.2 352 304 352L336 352C344.8 352 352 344.8 352 336L352 304C352 295.2 344.8 288 336 288L304 288C295.2 288 288 295.2 288 304zM432 288C423.2 288 416 295.2 416 304L416 336C416 344.8 423.2 352 432 352L464 352C472.8 352 480 344.8 480 336L480 304C480 295.2 472.8 288 464 288L432 288zM160 432L160 464C160 472.8 167.2 480 176 480L208 480C216.8 480 224 472.8 224 464L224 432C224 423.2 216.8 416 208 416L176 416C167.2 416 160 423.2 160 432zM304 416C295.2 416 288 423.2 288 432L288 464C288 472.8 295.2 480 304 480L336 480C344.8 480 352 472.8 352 464L352 432C352 423.2 344.8 416 336 416L304 416zM416 432L416 464C416 472.8 423.2 480 432 480L464 480C472.8 480 480 472.8 480 464L480 432C480 423.2 472.8 416 464 416L432 416C423.2 416 416 423.2 416 432z"/>
          </svg>
          <span class="relative w-9 h-5 rounded-full transition-colors"
                :class="showSelectDayCalendar ? 'bg-blue-600' : 'bg-gray-200'">
            <span class="absolute top-[2px] left-[2px] h-4 w-4 rounded-full bg-white border border-gray-300 transition-transform"
                  :class="showSelectDayCalendar ? 'translate-x-4' : 'translate-x-0'"></span>
          </span>
        </button>
      </div>
    </div>
  </div>

  <div class=" mb-2">
    <div class="flex max-md:flex-wrap items-center space-x-3 justify-between">
      @php

          $ns = $selectedDay?->note_status ?? CourseDay::NOTE_STATUS_MISSING;
          $canFinalize =
              !$isDirty &&
              trim(strip_tags($dayNotes ?? '')) !== '' &&
              $ns !== CourseDay::NOTE_STATUS_COMPLETED;
      @endphp

      <div class="flex items-center gap-3">
        {{-- Aktionen: Speichern / Fertigstellen --}}
        @if($selectedDayId && $isDirty)
            {{-- Speichern-Button nur wenn dirty --}}
            <x-buttons.button-basic
                type="button"
                :size="'sm'"
                class="px-2"
                wire:click="saveNotes"
                wire:target="saveNotes"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-70 cursor-wait"
                title="Notizen speichern (Entwurf)"
            >
                <i class="fad fa-save text-[16px] h-[1.25rem] flex items-center sm:mr-1 text-amber-500"></i>
                <span class="hidden sm:inline">Speichern</span>
            </x-buttons.button-basic>
        @endif

        @if($selectedDayId && $canFinalize && !$isDirty)
            {{-- Fertigstellen-Button nur, wenn gespeichert & nicht dirty --}}
            <x-buttons.button-basic
                type="button"
                :size="'sm'"
                class="px-2"
                wire:click="finalizeDay"
                wire:target="finalizeDay"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-70 cursor-wait"
                title="Dokumentation fertigstellen und unterschreiben"
            >
                <i class="fad fa-check-circle text-[16px] h-[1.25rem] flex items-center sm:mr-1 text-green-600"></i>
                <span class="hidden sm:inline">Fertigstellen</span>
            </x-buttons.button-basic>
        @endif
      </div>
        <div class="flex items-center gap-3">
          {{-- Status-Badge --}}
          @php
              $ns = $selectedDay?->note_status ?? CourseDay::NOTE_STATUS_MISSING;
              [$statusLabel, $statusClasses] = match($ns) {
                  CourseDay::NOTE_STATUS_DRAFT     => ['Entwurf', 'bg-amber-50 text-amber-700 border-amber-200'],
                  CourseDay::NOTE_STATUS_COMPLETED => ['Fertig & unterschrieben', 'bg-green-50 text-green-700 border-green-200'],
                  default                          => ['Fehlend', 'bg-slate-50 text-slate-700 border-slate-200'],
              };
          @endphp
          <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $statusClasses }}">
              Status: {{ $statusLabel }}
          </span>
      </div>
    </div>
  </div>
  <div class="mt-6 border border-gray-300 shadow rounded-lg overflow-hidden">
    <x-ui.editor.toast
      wire:key="tui-editor-{{ $selectedDayId }}" 
      wireModel="dayNotes"
    />
  </div>
</div>
