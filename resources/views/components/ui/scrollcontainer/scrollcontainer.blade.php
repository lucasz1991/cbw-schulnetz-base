@props([
  'snap'                 => 'start',
  'snapMode'             => 'mandatory',
  'containerClass'       => '',
  'maxHeightClass'       => null,
  'visibleRows'          => null,
  'itemSelector'         => null,
  'extra'                => 0,
  'role'                 => 'list',
  'ariaLabel'            => null,

  // NEU: Active-Scroll
  'activeSelector'       => '.active',
  'activeAlign'          => 'start',   // 'start' | 'center' | 'end'
  'activeOffset'         => 0,         // px
  'scrollActiveOnInit'   => true,
  'scrollActiveOnChange' => true,
])

@php
  $snapContainer = $snap === 'none' ? 'snap-none' : 'snap-y snap-'.$snapMode;
  $maxH          = $maxHeightClass ?? '';
@endphp

<div
  x-data="{
    rows: {{ $visibleRows ? (int)$visibleRows : 'null' }},
    itemSelector: @js($itemSelector),
    extra: @js((int)$extra),

    // Active-Scroll Optionen
    activeSelector: @js($activeSelector),
    activeAlign: @js($activeAlign),
    activeOffset: @js((int)$activeOffset),
    scrollActiveOnInit: @js((bool)$scrollActiveOnInit),
    scrollActiveOnChange: @js((bool)$scrollActiveOnChange),

    setMax() {
      if (!this.rows) return;
      const box = $refs.box; if (!box) return;

      let item = null;
      if (this.itemSelector) item = box.querySelector(this.itemSelector);
      else item = box.firstElementChild;

      if (!item) return;

      const cs = getComputedStyle(item);
      const h  = item.offsetHeight
               + parseFloat(cs.marginTop || 0)
               + parseFloat(cs.marginBottom || 0);

      box.style.maxHeight = Math.round(h * this.rows + this.extra) + 'px';
    },

    _clamp(v, min, max){ return Math.max(min, Math.min(max, v)); },

    scrollActive(){
      const box = $refs.box; if (!box) return;
      if (!this.activeSelector) return;

      const el = box.querySelector(this.activeSelector);
      if (!el) return;

      // relative Top-Position des aktiven Elements im Container (in px)
      const boxRect = box.getBoundingClientRect();
      const elRect  = el.getBoundingClientRect();
      const elTopInBox = (elRect.top - boxRect.top) + box.scrollTop;

      let target = elTopInBox; // 'start'
      if (this.activeAlign === 'center') {
        target = elTopInBox - (box.clientHeight - el.offsetHeight)/2;
      } else if (this.activeAlign === 'end') {
        target = elTopInBox - (box.clientHeight - el.offsetHeight);
      }

      target = target - this.activeOffset;
      const maxScroll = box.scrollHeight - box.clientHeight;
      box.scrollTo({ top: this._clamp(target, 0, Math.max(0, maxScroll)), behavior: 'auto' });
    },

    _debounce(fn, wait=50){
      let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
    },
  }"
  x-init="
    const debouncedUpdate = _debounce(() => { setMax(); if (scrollActiveOnChange) scrollActive(); }, 60);

    setMax();
    if (scrollActiveOnInit) scrollActive();

    window.addEventListener('resize', debouncedUpdate, { passive:true });

    const ro = new ResizeObserver(debouncedUpdate);
    ro.observe($refs.box);

    const mo = new MutationObserver(debouncedUpdate);
    mo.observe($refs.box, { childList:true, subtree:true, attributes:true, attributeFilter:['class','style'] });
  "
>
  <div
    x-ref="box"
    role="{{ $role }}" @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
    class="overflow-y-auto overflow-x-hidden scroll-container scroll-smooth {{ $snapContainer }} {{ $containerClass }} {{ $maxH }}"
  >
    {{ $slot }}
  </div>
</div>
