@props([
  'name',
  'class' => '',
  'collapse' => true,
])

<div
  x-cloak
  x-show="selectedTab === @js($name)"
  {{ $collapse ? 'x-collapse' : '' }}
  id="tabpanel-{{ $name }}"
  role="tabpanel"
  aria-label="{{ $name }}"
  {{ $attributes->merge(['class' => $class]) }}
>
  {{ $slot }}
</div>
