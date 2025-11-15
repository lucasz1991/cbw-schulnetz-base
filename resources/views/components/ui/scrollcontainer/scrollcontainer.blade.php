@props([
  'snap'           => 'start',        // 'none' | 'start' | 'center' | 'end' | 'always'
  'snapMode'       => 'mandatory',    // 'mandatory' | 'proximity'
  'containerClass' => '',
  'maxHeightClass' => null,           // z.B. 'max-h-[60vh]' (wird ignoriert, wenn visibleRows gesetzt ist)
  'visibleRows'    => null,           // Anzahl sichtbarer Items
  'itemSelector'   => null,           // optional: CSS-Selector (z. B. '.sc-item' oder '[data-sc-item]')
  'extra'          => 0,              // Zusatzhöhe in px
  'role'           => 'list',
  'ariaLabel'      => null,
])

@php
  $snapClass = $snap === 'none' ? 'snap-none' : 'snap-'.$snapMode.' snap-'.$snap;
  $maxH      = $maxHeightClass ?? '';
@endphp

<div
  role="{{ $role }}" @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
  class="overflow-y-auto scroll-container overflow-x-hidden scroll-smooth {{ $snapClass }} {{ $containerClass }} {{ $maxH }}"
  x-data="{
    rows: {{ $visibleRows ? (int)$visibleRows : 'null' }},
    itemSelector: @js($itemSelector),
    extra: @js((int)$extra),
    setMax() {
      if (!this.rows) return; // nur aktiv, wenn visibleRows gesetzt ist

      const box = $el;
      let item = null;

      if (this.itemSelector) {
        // expliziter Selector
        item = box.querySelector(this.itemSelector);
      } else {
        // kein Selector: nimm erstes direkte Kind im Slot
        const inner = box.firstElementChild;
        if (inner) item = inner.firstElementChild || inner;
      }

      if (!item) return;

      const cs = getComputedStyle(item);
      const h = item.offsetHeight
              + parseFloat(cs.marginTop || 0)
              + parseFloat(cs.marginBottom || 0);

      box.style.maxHeight = Math.round(h * this.rows + this.extra) + 'px';
    }
  }"
  x-init="
    setMax();
    const update = () => setMax();
    window.addEventListener('resize', update, { passive: true });

    const ro = new ResizeObserver(update);
    ro.observe($el);

    const mo = new MutationObserver(update);
    mo.observe($el, { childList: true, subtree: true });
  "
>
  <div class="flex flex-col w-full">
    {{ $slot }}
  </div>
</div>
