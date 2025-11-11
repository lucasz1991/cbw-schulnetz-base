@props([
  'label' => null,
  'class' => '',
])

@php
  $base = 'inline-flex items-stretch overflow-hidden rounded-md border border-gray-200 bg-white';
  // Bis md: horizontal + divide-x, ab md: vertikal + divide-y
  $orientation = 'flex-row divide-x md:flex-col md:divide-x-0 md:divide-y';
@endphp

<div {{ $attributes->merge([
  'class' => "$base $orientation $class",
  'role' => 'radiogroup',
  'aria-label' => $label ?? 'Button group',
]) }}>
  {{ $slot }}
</div>
