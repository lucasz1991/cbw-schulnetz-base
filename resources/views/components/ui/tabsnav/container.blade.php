@props([
    'storageKey'  => 'tabs',
    'default'     => 'basic',
    'class'       => '',
    'persistKey'  => true,
])
 
@php
    $usePersist = filter_var($persistKey, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($usePersist === null) {
        $usePersist = (bool) $persistKey;
    }
@endphp

@if($usePersist)
    <div x-data="{ selectedTab: $persist(@js($default)).as(@js($storageKey)) }"
         {{ $attributes->merge(['class' => $class]) }}>
        {{ $slot }}
    </div>
@else
    <div x-data="{ selectedTab: @js($default) }"
         {{ $attributes->merge(['class' => $class]) }}>
        {{ $slot }}
    </div>
@endif
