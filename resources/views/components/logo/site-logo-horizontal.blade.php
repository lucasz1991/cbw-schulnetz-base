@php
  $classes = trim('inline-block max-h-14 h-14 w-auto object-contain ' . ($class ?? ''));
@endphp

@if($src)
  @if($linkToHome)
    <a href="{{ url('/') }}" class="inline-block" aria-label="Startseite">
      <img src="{{ $src }}" alt="{{ $alt }}" class="{{ $classes }}" loading="lazy" decoding="async">
    </a>
  @else
    <img src="{{ $src }}" alt="{{ $alt }}" class="{{ $classes }}" loading="lazy" decoding="async">
  @endif
@else
  @if($linkToHome)
    <a href="{{ url('/') }}" class="inline-flex items-center font-semibold text-lg">
      {{ $alt }}
    </a>
  @else
    <span class="inline-flex items-center font-semibold text-lg">{{ $alt }}</span>
  @endif
@endif
