@props([
    'type' => 'fade-up',
    'delay' => 0,
    'duration' => 500,
    'once' => false,
])

@php
    $presets = [
        'fade-up'    => ['opacity-0 translate-y-4',  'opacity-100 translate-y-0'],
        'fade-down'  => ['opacity-0 -translate-y-4', 'opacity-100 translate-y-0'],
        'fade-left'  => ['opacity-0 -translate-x-4', 'opacity-100 translate-x-0'],
        'fade-right' => ['opacity-0 translate-x-4',  'opacity-100 translate-x-0'],
        'zoom-in'    => ['opacity-0 scale-95',       'opacity-100 scale-100'],
        'zoom-out'   => ['opacity-0 scale-105',      'opacity-100 scale-100'],
    ];

    [$from, $to] = $presets[$type] ?? $presets['fade-up'];

    $transitionStyle = sprintf(
        'transition-property: opacity, transform; transition-timing-function: cubic-bezier(0.4,0,0.2,1); transition-duration:%dms; transition-delay:%dms;',
        (int) $duration,
        (int) $delay
    );
@endphp

<div
    x-data="{ shown: false }"
    @if($once)
        x-intersect.once="shown = true"
    @else
        x-intersect:enter="shown = true"
        x-intersect:leave="shown = false"
    @endif
    {{ $attributes->merge(['class' => ' w-full ']) }}
>
    <div
        x-cloak
        :class="shown ? '{{ $to }}' : '{{ $from }}'"
        style="{{ $transitionStyle }}"
    >
        {{ $slot }}
    </div>
</div>
