@props([
    'id' => null, 'model' => null, 'timeModel' => null, 'label' => null,
    'placeholder' => 'Datum wählen …', 'required' => false,
    'min' => null, 'max' => null, 'enableTime' => false, 'timeOnly' => false,
    'inline' => false, 'dateFormat' => null, 'altFormat' => null,
    'altInput' => true, 'mode' => 'single', 'disableWeekends' => false,
    'minuteIncrement' => 5, 'disabled' => false, 'readonly' => false,
    'name' => null, 'value' => '', 'timeValue' => '',
])
@php
    $inputId = $id ?: 'date-'.\Illuminate\Support\Str::uuid();
    $enableTime = $enableTime || $timeOnly || (bool) $timeModel;
    $dateFormat = $dateFormat ?? ($timeOnly ? 'H:i' : ($enableTime ? 'Y-m-d H:i' : 'Y-m-d'));
    $altFormat = $altFormat ?? ($timeOnly ? 'H:i' : ($enableTime ? 'd.m.Y · H:i' : 'd.m.Y'));
    $picker = compact('enableTime', 'timeOnly', 'inline', 'dateFormat', 'altFormat', 'altInput', 'mode', 'disableWeekends', 'min', 'max', 'required', 'disabled', 'readonly', 'minuteIncrement');
@endphp
<div {{ $attributes->class(['w-full']) }} wire:key="date-field-{{ $inputId }}-{{ md5(($model ?? '').'|'.($timeModel ?? '')) }}">
    <div class="lmz-date-picker" wire:ignore x-data="lmzDatePicker()"
         data-date-picker="{{ json_encode($picker) }}" data-inline="{{ $inline ? 'true' : 'false' }}" data-disabled="{{ $disabled ? 'true' : 'false' }}">
        <input type="hidden" data-date-value @if($model) data-date-model="{{ $model }}" @endif @if($name) name="{{ $name }}" @endif value="{{ $value }}">
        @if($timeModel)<input type="hidden" data-time-value data-date-model="{{ $timeModel }}" value="{{ $timeValue }}">@endif
        <div class="lmz-date-field" data-date-field>
            @if($label)<label for="{{ $inputId }}">{{ $label }}@if($required)<span aria-hidden="true"> *</span>@endif</label>@endif
            <input id="{{ $inputId }}" data-date-source type="text" class="lmz-date-display" placeholder="{{ $timeOnly && $placeholder === 'Datum wählen …' ? 'Uhrzeit wählen …' : $placeholder }}"
                @required($required) @disabled($disabled) @readonly($readonly) @if(!$label) aria-label="{{ $timeOnly ? 'Uhrzeit' : 'Datum' }}" @endif>
            <button type="button" data-date-trigger class="lmz-date-trigger" aria-label="{{ $timeOnly ? 'Uhrzeit wählen' : 'Kalender öffnen' }}" aria-expanded="{{ $inline ? 'true' : 'false' }}" @disabled($disabled || $readonly)>
                @if($timeOnly)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg>
                @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 10h18" stroke-linecap="round"/><path d="M7 14h2m2 0h2m2 0h2M7 17h2m2 0h2"/></svg>
                @endif
            </button>
        </div>
        @if($inline)<div data-date-inline></div>@endif
    </div>
    @if($model)<x-ui.forms.input-error :for="$model" />@endif
    @if($timeModel)<x-ui.forms.input-error :for="$timeModel" />@endif
</div>
