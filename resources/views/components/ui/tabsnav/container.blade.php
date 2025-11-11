@props([
  'storageKey' => 'tabs',
  'default' => 'basic',
  'class' => '',
])

<div x-data="{ selectedTab: $persist(@js($default)).as(@js($storageKey)) }" {{ $attributes->merge(['class' => $class]) }}>
  {{ $slot }}
</div>
