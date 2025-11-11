@props([
  'name'      => null,   // z.B. 'wiederholung'
  'value'     => null,   // z.B. 'wiederholung_1'
  'label'     => '',
  'icon'      => null,   // 'fa-redo'
  'iconStyle' => 'fas',  // 'fas'|'far'
  'size'      => 'md',   // 'sm'|'md'|'lg'
  'disabled'  => false,
  'checked'   => false,
  'class'     => '',
])

@php
  if ($name === null || $value === null) {
    throw new InvalidArgumentException('radio-btn-group-item: "name" und "value" sind erforderlich.');
  }

  $id = $name.'__'.\Illuminate\Support\Str::slug($value, '_');

  $sizeMap = [
    'sm' => 'px-2 py-1 text-xs',
    'md' => 'px-3 py-2 text-sm',
    'lg' => 'px-4 py-2.5 text-base',
  ];

  $labelBase = implode(' ', [
    'inline-flex items-center gap-2 select-none',
    'text-sm font-medium text-gray-700 bg-white',
    'hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500',
    'transition-colors',
    // Rundungen funktionieren sowohl vertikal als auch ab md horizontal
    'first:rounded-t-md last:rounded-b-md md:first:rounded-t-none md:last:rounded-b-none md:first:rounded-l-md md:last:rounded-r-md',
    $sizeMap[$size] ?? $sizeMap['md'],
    $class,
  ]);
@endphp

<div class="contents">
  <input
    id="{{ $id }}"
    type="radio"
    name="{{ $name }}"
    value="{{ $value }}"
    class="sr-only peer"
    {{ $disabled ? 'disabled' : '' }}
    {{ $checked ? 'checked' : '' }}
    {{ $attributes->whereStartsWith('wire:model') }} {{-- z.B. wire:model="wiederholung" --}}
  />
  <label
    for="{{ $id }}"
    class="{{ $labelBase }} peer-checked:bg-blue-600 peer-checked:text-white peer-checked:hover:bg-blue-700"
  >
    @if($icon)
      <i class="{{ $iconStyle }} {{ $icon }}"></i>
    @endif
    <span>{{ $label }}</span>
  </label>
</div>
