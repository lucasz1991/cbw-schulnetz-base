@props([
  'name',        // z.B. 'basic'
  'class' => '', // optionale Panel-Klassen
  'collapse' => true, // x-collapse wie bei dir
])

@php
  $collapseAttrs = $collapse ? 'x-collapse' : '';
@endphp

<div x-cloak
     x-show="selectedTab === @js($name)"
     {{ $collapse ? 'x-collapse' : '' }}
     id="tabpanel-{{ $name }}"
     role="tabpanel"
     aria-label="{{ $name }}"
     {{ $attributes->merge(['class' => $class]) }}>
  {{ $slot }}
</div>
