@props([
    'align'           => 'right',   // 'left' | 'right' | 'top' | 'none'/'false'
    'width'           => '48',      // 'auto' | 'min' | 'max' | '48'
    'contentClasses'  => 'py-1 bg-white',
    'dropdownClasses' => '',
    'offset'          => 0,         // Abstand zum Trigger in px (Anchor .offset)
])

@php
// Breitenklassen wie gehabt
switch ($width) {
    case 'auto': $widthClass = 'w-auto'; break;
    case 'min':  $widthClass = 'w-min';  break;
    case 'max':  $widthClass = 'w-max';  break;
    case '48':
    default:     $widthClass = 'w-48';   break;
}

// Anchor-Position aus align ableiten
// bottom-start/bottom-end flippen automatisch auf top, wenn kein Platz (via .flip)
// .shift sorgt fürs Reinschieben vom Rand
$anchorPosition = match ($align) {
    'left'   => 'bottom-start',
    'top'    => 'top-end',
    'none', 'false' => 'bottom-end',
    default  => 'bottom-end', // 'right'
};
@endphp

<div
  class="relative"
  x-data="{ open: false }"
  x-cloak
  @keydown.escape.window="open = false"
  @close.window.stop="open = false"
>
    <div x-ref="trigger" @click="open = !open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"

        x-anchor.{{ $anchorPosition }}.offset.{{ $offset }}.flip.shift="$refs.trigger"

        class="z-50 mt-2 {{ $widthClass }} rounded-md shadow-lg {{ $dropdownClasses }}"

        style="display:none; max-width:calc(100vw - 16px); max-height:calc(100vh - 16px);"

        @click.outside="open = false"
    >
        <div
          class="rounded-md ring-1 ring-black ring-opacity-5 overflow-auto {{ $contentClasses }}"
        >
            {{ $content }}
        </div>
    </div>
</div>
