@props([
  'label' => null,
  'class' => '',
  // nur: 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  'breakpoint' => 'md',
])

@php
  // validieren
  $valid = ['sm','md','lg','xl','2xl'];
  $bp = in_array($breakpoint, $valid, true) ? $breakpoint : 'md';

  // rein statische Maps -> purge-sicher
  $map = [
    'sm'  => 'flex-col divide-y sm:flex-row sm:divide-y-0 sm:divide-x',
    'md'  => 'flex-col divide-y md:flex-row md:divide-y-0 md:divide-x',
    'lg'  => 'flex-col divide-y lg:flex-row lg:divide-y-0 lg:divide-x',
    'xl'  => 'flex-col divide-y xl:flex-row xl:divide-y-0 xl:divide-x',
    '2xl' => 'flex-col divide-y 2xl:flex-row 2xl:divide-y-0 2xl:divide-x',
  ];
  $responsive = $map[$bp];

  $base = 'inline-flex items-stretch overflow-hidden rounded-md border border-gray-200 bg-white';
@endphp

<div {{ $attributes->merge([
  'class' => "$base $responsive $class",
  'role' => 'group',
  'aria-label' => $label ?? 'Button group',
]) }}>
  {{ $slot }}
</div>
