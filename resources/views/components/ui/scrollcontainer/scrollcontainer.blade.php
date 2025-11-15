@props([
  'snap'           => 'start',        // 'none' | 'start' | 'center' | 'end' | 'always'  (Hinweis: Alignment gehört eigentl. an die Items)
  'snapMode'       => 'mandatory',    // 'mandatory' | 'proximity'
  'containerClass' => '',
  'maxHeightClass' => null,           // ignoriert, falls visibleRows gesetzt
  'visibleRows'    => null,           // Anzahl sichtbarer Items (-> misst 1 Item x rows)
  'itemSelector'   => null,           // z.B. '.sc-item' oder '[data-sc-item]'
  'extra'          => 0,              // px-Puffer
  'role'           => 'list',
  'ariaLabel'      => null,
])

@php
  // Snap-Klassen NUR für den Container; Alignment (start/center/end/always) gehört auf die CHILD-Items
  $snapContainer = $snap === 'none' ? 'snap-none' : 'snap-y snap-'.$snapMode;
  $maxH          = $maxHeightClass ?? '';
@endphp

<div
  x-data="{
    rows: {{ $visibleRows ? (int)$visibleRows : 'null' }},
    itemSelector: @js($itemSelector),
    extra: @js((int)$extra),
    setMax() {
      if (!this.rows) return;

      const box = $refs.box;
      if (!box) return;

      let item = null;
      if (this.itemSelector) {
        item = box.querySelector(this.itemSelector);
      } else {
        // Items sind direkte Children des Scroll-Containers
        item = box.firstElementChild;
      }
      if (!item) return;

      const cs = getComputedStyle(item);
      const h  = item.offsetHeight
               + parseFloat(cs.marginTop || 0)
               + parseFloat(cs.marginBottom || 0);

      box.style.maxHeight = Math.round(h * this.rows + this.extra) + 'px';
    }
  }"
  x-init="
    setMax();
    const update = () => setMax();
    window.addEventListener('resize', update, { passive:true });
    const ro = new ResizeObserver(update); ro.observe($refs.box);
    const mo = new MutationObserver(update); mo.observe($refs.box, { childList:true, subtree:true });
  "
>
  <!-- echter Scroll-Container -->
  <div
    x-ref="box"
    role="{{ $role }}" @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
    class="overflow-y-auto overflow-x-hidden scroll-container scroll-smooth {{ $snapContainer }} {{ $containerClass }} {{ $maxH }}"
  >
    {{ $slot }}
  </div>
</div>
