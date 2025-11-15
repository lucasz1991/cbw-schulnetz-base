@props([
  'axis' => 'y',              // 'y' | 'x'
  'snap' => 'start',          // 'none' | 'start' | 'center' | 'end' | 'always'
  'snapMode' => 'mandatory',  // 'mandatory' | 'proximity'
  'containerClass' => '',
  'maxHeightClass' => null,   // z.B. 'max-h-[60vh]' (nur y)
  'minItemWidthClass' => null,// z.B. 'min-w-[280px]' (nur x)
  'role' => 'list',
  'ariaLabel' => null,
])

@php
  $isY      = $axis === 'y';
  $overflow = $isY ? 'overflow-y-auto overflow-x-hidden' : 'overflow-x-auto overflow-y-hidden';
  $flow     = $isY ? 'flex flex-col' : 'flex flex-row';
  $gap      = $isY ? '' : '';
  $maxH     = $isY ? ($maxHeightClass ?? '') : '';
@endphp

<div class="{{ $overflow }} {{ $snap === 'none' ? 'snap-none' : 'snap-'.$snapMode }} scroll-smooth {{ $containerClass }} {{ $maxH }}"
     role="{{ $role }}" @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
     x-data
>
  <div class="{{ $flow }} {{ $gap }} w-full">
    {{ $slot }}
  </div>
</div>
