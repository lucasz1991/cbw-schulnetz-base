@php
    $calendar = \App\Services\Coaching\ScheduleReview::calendar($items, $calendarMonth, $calendarDate);
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
    <div><h3 class="font-semibold text-gray-800">Terminvorschläge</h3><span class="text-xs text-gray-500" data-slot-count>{{ count($items) }} Termine · gesamter Plan</span></div>
    <div class="inline-flex rounded-lg border border-gray-300 bg-gray-50 p-1 gap-1" role="group" aria-label="Terminansicht">
        @foreach(['list' => ['Liste', 'fa-list'], 'calendar' => ['Kalender', 'fa-calendar-alt']] as $view => [$label, $icon])
        <button type="button" wire:click="changePlanView('{{ $view }}')" data-plan-view="{{ $view }}" aria-pressed="{{ $planView === $view ? 'true' : 'false' }}" @class(['inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-200', 'bg-white text-blue-700 shadow-sm font-semibold' => $planView === $view, 'text-gray-500' => $planView !== $view])><i class="fas {{ $icon }}" aria-hidden="true"></i>{{ $label }}</button>
        @endforeach
    </div>
</div>
<div data-review-layout @class(['grid gap-4 items-start', 'md:grid-cols-[280px_minmax(0,1fr)]' => $planView === 'calendar'])>
<div data-plan-calendar @class(['space-y-3', 'hidden' => $planView !== 'calendar'])>
    <div class="flex items-center justify-between gap-2">
        <h4 class="font-semibold text-gray-800" data-calendar-title>{{ $calendar['title'] }}</h4>
        <div class="flex gap-1"><button type="button" wire:click="movePlanMonth(-1)" aria-label="Vorheriger Monat" class="rounded-md border px-3 py-2 text-gray-600 hover:bg-gray-50"><i class="fas fa-chevron-left" aria-hidden="true"></i></button><button type="button" wire:click="movePlanMonth(1)" aria-label="Nächster Monat" class="rounded-md border px-3 py-2 text-gray-600 hover:bg-gray-50"><i class="fas fa-chevron-right" aria-hidden="true"></i></button></div>
    </div>
    <div class="grid grid-cols-7 text-center text-xs text-gray-500" aria-hidden="true">@foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $day)<span class="py-1">{{ $day }}</span>@endforeach</div>
    <div class="grid grid-cols-7 gap-1" data-calendar-grid role="group" aria-label="Tage im Monat">
        @foreach($calendar['days'] as $day)
        <button type="button" wire:click="selectPlanDate('{{ $day['date'] }}')" data-calendar-date="{{ $day['date'] }}" aria-label="{{ $day['label'] }}" aria-pressed="{{ $day['selected'] ? 'true' : 'false' }}" @class(['min-w-0 h-10 rounded-md border p-1 text-center items-center flex flex-col focus:ring-2 focus:ring-blue-300', 'bg-blue-50 border-blue-400 text-blue-800' => $day['selected'], 'bg-white border-gray-200 text-gray-700' => !$day['selected'] && $day['current'], 'bg-gray-50 border-gray-100 text-gray-400' => !$day['selected'] && !$day['current']])>
            <span class="text-xs font-medium" data-day-number>{{ $day['number'] }}</span>
            <span data-day-events class="hidden">{{ $day['events'] ? ($day['events'][0]['start'] ?? '') . ' Uhr' : '' }}{{ count($day['events']) > 1 ? ' +'.(count($day['events']) - 1) : '' }}</span>
            <span data-day-count @class(['inline-flex items-center gap-1 text-xs text-blue-700 mt-auto', 'hidden' => !$day['events']])><span class="w-1 h-1 rounded-full bg-blue-600" aria-hidden="true"></span><span data-day-count-value>{{ count($day['events']) }}</span></span>
        </button>
        @endforeach
    </div>
</div>
<div class="min-w-0 space-y-2">
<p data-selected-date @class(['text-xs font-semibold text-gray-600 pb-1', 'hidden' => $planView !== 'calendar'])>{{ $calendar['selectedLabel'] }}</p>
<div class="space-y-2" data-coaching-items>
    @foreach($items as $i => $item)
    @php
        $card = \App\Services\Coaching\ScheduleReview::card($item, $contract->unit_minutes);
        $shortDate = \App\Services\Coaching\ScheduleReview::date($item['date'] ?? '')?->translatedFormat('l') ?? 'Datum festlegen';
        $expanded = $expandedSlotId === $item['id'];
        $visible = $planView === 'list' || ($item['date'] ?? '') === $calendar['selected'] || !\App\Services\Coaching\ScheduleReview::date($item['date'] ?? '');
    @endphp
    <article wire:key="slot-{{ $item['id'] }}" data-coaching-slot @class(['border border-gray-200 rounded-lg bg-white', 'hidden' => !$visible])>
        <x-ui.dropdown.anchor-dropdown align="right" width="auto" :offset="6" :trap="true"
            teleportTo="#coaching-slot-editor-portal" selectionModel="expandedSlotId" :selectionValue="$item['id']"
            dropdownClasses="w-[min(420px,calc(100vw-48px))]" contentClasses="bg-white" data-slot-dropdown>
        <x-slot name="trigger">
        <button type="button" data-slot-toggle :aria-expanded="open" aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="slot-fields-{{ $item['id'] }}" aria-haspopup="dialog" class="w-full flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 text-left hover:bg-gray-50 rounded-lg focus:ring-2 focus:ring-inset focus:ring-blue-200" aria-label="Termin {{ $i + 1 }} bearbeiten">
            <span class="flex flex-col items-center justify-center rounded-md bg-gray-50 border border-gray-100 w-11 sm:w-12 h-12 sm:h-14 shrink-0" aria-hidden="true"><span class="text-base sm:text-lg font-semibold leading-none text-gray-800" data-slot-day>{{ $card['day'] }}</span><span class="mt-1 text-[11px] sm:text-xs text-gray-500" data-slot-month>{{ $card['month'] }}</span></span>
            <span class="flex-1 min-w-0"><span class="block text-[11px] sm:text-xs text-gray-500 truncate" data-slot-date><span class="sm:hidden">{{ $shortDate }}</span><span class="hidden sm:inline">{{ $card['date'] }}</span></span><span class="block font-semibold text-sm text-gray-800" data-slot-time>{{ $card['time'] }}</span><span class="block text-[11px] sm:text-xs text-gray-500 truncate" data-slot-duration>{{ $card['duration'] }}</span><span class="block text-[11px] sm:text-xs text-gray-600 truncate"><span data-slot-topic>{{ $item['topic'] ?: 'Thema festlegen' }}</span> · <span data-slot-format>{{ ($item['format'] ?? '') === 'presence' ? 'Präsenz' : 'Online' }}</span></span></span>
            <span class="inline-flex items-center gap-2 text-xs text-blue-700 shrink-0"><span class="hidden sm:inline" data-slot-edit-label>Bearbeiten</span><i data-slot-chevron class="fas fa-chevron-down" :class="{ 'rotate-180': open }" aria-hidden="true"></i></span>
        </button>
        </x-slot>
        <x-slot name="content">
        <div id="slot-fields-{{ $item['id'] }}" data-slot-fields role="dialog" aria-label="Termin {{ $i + 1 }} bearbeiten" class="flex flex-col" style="height:min(280px, calc(100dvh - 80px))" @keydown.enter.prevent="open=false; $refs.trigger.querySelector('button')?.focus()">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 shrink-0"><div><p class="text-[11px] font-semibold uppercase tracking-wider text-secondary-600">Termineinstellungen</p><h4 class="mt-0.5 text-sm font-semibold text-gray-800" data-slot-editor-title>Termin {{ $i + 1 }} bearbeiten</h4></div><button type="button" data-slot-done @click.stop="open=false; $refs.trigger.querySelector('button')?.focus()" aria-label="Termineinstellungen schließen" class="text-gray-500 px-2 py-1 rounded hover:bg-gray-100"><i class="fas fa-times" aria-hidden="true"></i></button></div>
            <div class="grid grid-cols-2 gap-3 p-4 overflow-y-auto min-h-0 flex-1">
                @if($errors->has('items.'.$i.'.*'))
                <div role="alert" class="col-span-2 text-xs text-red-700">@foreach(\Illuminate\Support\Arr::flatten($errors->get('items.'.$i.'.*')) as $error)<p>{{ $error }}</p>@endforeach</div>
                @endif
                <x-ui.forms.date-input class="col-span-2" :id="'coaching-date-'.$item['id']" :model="'items.'.$i.'.date'" :timeModel="'items.'.$i.'.start'" label="Datum & Beginn" :enableTime="true" :min="$contract->valid_from?->toDateString()" :max="$contract->valid_until?->toDateString()" />
                <x-ui.forms.date-input class="col-span-2" :id="'coaching-end-'.$item['id']" :model="'items.'.$i.'.end'" label="Ende" :timeOnly="true" />
                <label class="text-xs col-span-2">Thema / Inhalt<input wire:model.blur="items.{{ $i }}.topic" maxlength="255" class="block w-full text-sm rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"></label>
                <label class="text-xs">Durchführung<select wire:model.blur="items.{{ $i }}.format" class="block w-full text-sm rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"><option value="online">Online</option><option value="presence">Präsenz</option></select></label>
                <label class="text-xs">Ort / Besprechungslink<input wire:model.blur="items.{{ $i }}.location" maxlength="255" class="block w-full text-sm rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-200"></label>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 bg-gray-50 shrink-0"><button type="button" wire:click="remove({{ $i }})" data-slot-remove class="text-xs text-red-700 hover:underline">Entfernen</button><x-buttons.button-basic type="button" mode="secondary" size="sm" data-slot-done @click.stop="open=false; $refs.trigger.querySelector('button')?.focus()">Fertig</x-buttons.button-basic></div>
        </div>
        </x-slot>
        </x-ui.dropdown.anchor-dropdown>
    </article>
    @endforeach
</div>
<p data-empty-date @class(['text-sm text-gray-500 py-3', 'hidden' => count(array_filter($items, fn ($item) => $planView === 'list' || ($item['date'] ?? '') === $calendar['selected'] || !\App\Services\Coaching\ScheduleReview::date($item['date'] ?? ''))) > 0])>{{ $planView === 'calendar' ? 'Keine Termine an diesem Tag.' : 'Noch keine Termine geplant.' }}</p>
</div>
</div>
