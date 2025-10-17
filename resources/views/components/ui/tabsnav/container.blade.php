@props([
  'storageKey' => 'tabs',
  'default' => 'basic',
  // optional: zusätzlich Klassen für Außen-Wrapper
  'class' => '',
])
<div x-data="{ selectedTab: $persist(@js($default)).as(@js($storageKey)) }" {{ $attributes->merge(['class' => $class]) }}>
  {{ $slot }}
</div>
