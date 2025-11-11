@props([
  'label' => null,
  'class' => '',
])

@php
  $base = 'inline-flex items-stretch overflow-hidden rounded-md border border-gray-200 bg-white';
  // Bis md: vertikal + divide-y, ab md: horizontal + divide-x
  $orientation = 'flex-col divide-y md:flex-row md:divide-y-0 md:divide-x';
@endphp

<div {{ $attributes->merge([
  'class' => "$base $orientation $class",
  'role' => 'radiogroup',
  'aria-label' => $label ?? 'Button group',
]) }}>
  {{ $slot }}
</div>
