@props([
  'align'           => 'right',
  'width'           => '48',
  'contentClasses'  => 'py-1 bg-white',
  'dropdownClasses' => 'mx-4',
  'offset'          => 0,
  'overlay'         => false,
  'trap'            => false,

  // NEU:
  'scrollOnOpen'    => false, // beim Öffnen zum Trigger scrollen?
  'headerOffset'    => 0,     // z.B. Sticky-Header-Abstand
])

@php
  $widthClass = match($width){ 'auto'=>'w-auto','min'=>'w-min','max'=>'w-max', default=>'w-48' };
  $anchorPos  = match($align){ 'left'=>'bottom-start','top'=>'top-end','none','false'=>'bottom-end', default=>'bottom-end' };
@endphp

<div
  class="relative"
  x-data="{ 
    open:false, 
    scrollOnOpen: @js((bool)$scrollOnOpen), 
    headerOffset: @js((int)$headerOffset),
    scrollToTrigger(){
      const t = $refs.trigger;
      if(!t) return;
      const y = t.getBoundingClientRect().top + window.scrollY - this.headerOffset;
      window.scrollTo({ top: y, behavior: 'smooth' });
    },
  }"
  x-init="
    $watch('open', (v) => {
      if(v && scrollOnOpen){
        $nextTick(() => {
          scrollToTrigger();
          if($refs.panelScroll){ $refs.panelScroll.scrollTo({ top: 0, behavior: 'auto' }); }
        });
      }
    })
  "
  x-cloak
  @keydown.escape.window="open=false"
  @close.window.stop="open=false"
>
  {{-- Trigger --}}
  <div x-ref="trigger" @click="open=!open; if(open){ $nextTick(() => $dispatch('dropdown-open')) }">
    {{ $trigger }}
  </div>

  {{-- Optionaler Overlay --}}
  @if($overlay)
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-black/40" @click="open=false" style="display:none;"></div>
  @endif

  {{-- Panel --}}
  <div
    x-show="open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="transform opacity-0 scale-95"
    x-transition:enter-end="transform opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="transform opacity-100 scale-100"
    x-transition:leave-end="transform opacity-0 scale-95"
    x-anchor.{{ $anchorPos }}.offset.{{ $offset }}.flip.shift="$refs.trigger"
    class="z-50 {{ $widthClass }} rounded-md shadow-lg {{ $dropdownClasses }}"
    style="display:none; max-width:calc(100vw - 16px); max-height:calc(100vh - 16px);"
    @click.outside="open=false"
    @if($trap) x-trap.inert.noscroll="open" @endif
  >
    {{-- WICHTIG: eigener Container mit panelScroll-Ref --}}
    <div x-ref="panelScroll" class="rounded-md ring-1 ring-black ring-opacity-5 overflow-auto {{ $contentClasses }}">
      {{ $content }}
    </div>
  </div>
</div>
