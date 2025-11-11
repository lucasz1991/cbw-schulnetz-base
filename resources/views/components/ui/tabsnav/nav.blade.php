@props([
  // 1) Liste: [ ['id'=>'basic','label'=>'…','icon'=>'fad fa-home'], ... ]
  // 2) Map :  ['basic'=>['label'=>'…','icon'=>'fad fa-home'], ... ]
  'tabs' => [],

  // Original-Layout (beibehalten!)
  'class' => 'flex items-end gap-2 overflow-x-auto transform -translate-y-[100%] -mb-6',

  // Button-Design (beibehalten!)
  'buttonClass' => 'inline-flex items-center justify-center min-h-[38px] px-4 py-2 text-sm rounded-t-lg border-b-2 border-t border-x border-x-gray-300 border-t-gray-300 bg-white',

  // Unter welchem BP einklappen (aktiv + Dropdown)
  'collapseAt' => 'md',
])

@php
    use Illuminate\Support\Str;

    $items = [];
    if (array_is_list($tabs)) {
        foreach ($tabs as $t) {
            $id    = $t['id'] ?? Str::slug($t['label'] ?? 'tab', '_');
            $label = $t['label'] ?? Str::title($id);
            $icon  = $t['icon']  ?? null;
            $items[] = compact('id','label','icon');
        }
    } else {
        foreach ($tabs as $key => $t) {
            $items[] = is_array($t)
                ? ['id'=>$key, 'label'=>$t['label'] ?? Str::title($key), 'icon'=>$t['icon'] ?? null]
                : ['id'=>$key, 'label'=>(string)$t, 'icon'=>null];
        }
    }
@endphp

<div
  x-data="{
    items: @js($items),
    get active(){ return this.items.find(i => i.id === this.selectedTab) ?? this.items[0] ?? null; },
    get others(){ return this.items.filter(i => i.id !== (this.active?.id ?? '')); },
    collapsed:false, mq:null,
    setupMQ(bp){
      if(!bp) { this.collapsed=false; return; }
      const map={sm:640, md:768, lg:1024, xl:1280, '2xl':1536};
      const px = map[bp]; if(!px) return;
      this.mq = window.matchMedia(`(min-width:${px}px)`);
      const update = () => { this.collapsed = !this.mq.matches; };
      this.mq.addEventListener?.('change', update); update();
    }
  }"
  x-init="setupMQ(@js($collapseAt))"
  x-on:keydown.right.prevent="$focus.wrap().next()"
  x-on:keydown.left.prevent="$focus.wrap().previous()"
  role="tablist"
  aria-label="tab options"
  {{ $attributes->merge(['class' => $class]) }}
>
  <!-- Ab collapseAt: alle Tabs (Design unverändert) -->
  <template x-if="!collapsed">
    <template x-for="(t,idx) in items" :key="t.id">
      <button
        type="button"
        role="tab"
        :aria-selected="selectedTab === t.id"
        :tabindex="selectedTab === t.id ? '0' : '-1'"
        x-on:click="selectedTab = t.id"
        x-bind:class="selectedTab === t.id
          ? 'shadow font-semibold text-primary border-b-2 border-b-secondary !bg-blue-50'
          : 'bg-white text-on-surface font-medium border-b-white hover:border-b-blue-400 hover:border-b-outline-strong hover:text-on-surface-strong'"
        class="{{ $buttonClass }}"
        :aria-controls="`tabpanel-${t.id}`"
        :title="t.label"
      >
        <template x-if="t.icon">
          <i :class="t.icon + ' mr-1 max-md:mr-2 text-[14px]'" aria-hidden="true"></i>
        </template>
        <span class="sm:hidden" x-show="selectedTab === t.id" x-text="t.label"></span>
        <span class="hidden sm:inline" x-text="t.label"></span>
      </button>
    </template>
  </template>

  <!-- Unter collapseAt: aktiver Tab + Dropdown (mit Alpine Anchor) -->
  <template x-if="collapsed">
    <div class="contents" x-data="{ open:false }">
      <button
        type="button"
        class="{{ $buttonClass }} shadow font-semibold text-primary border-b-2 border-b-secondary !bg-blue-50"
        role="tab" aria-selected="true" tabindex="0"
        :title="active?.label ?? ''"
      >
        <template x-if="active?.icon">
          <i :class="active.icon + ' mr-1 max-md:mr-2 text-[14px]'" aria-hidden="true"></i>
        </template>
        <span x-text="active?.label ?? ''"></span>
      </button>

      <button
        x-ref="moreBtn"
        type="button"
        @click="open=!open"
        @keydown.escape.window="open=false"
        class="{{ $buttonClass }} bg-white text-on-surface font-medium border-b-white hover:border-b-blue-400 hover:border-b-outline-strong hover:text-on-surface-strong"
        :aria-expanded="open.toString()" aria-haspopup="menu" title="Weitere Tabs"
      >
        <i class="fad fa-bars mr-1 text-[14px]" aria-hidden="true"></i>
        <span class="whitespace-nowrap">Mehr</span>
      </button>

      <!-- Teleport + Anchor: Menü hängt an moreBtn; keine manuelle Positionierung nötig -->
      <template x-teleport="body">
        <div
          x-cloak
          x-show="open"
          x-transition.opacity.duration.120ms
          x-on:click.away="open=false"
          x-anchor.bottom-start.offset.6="$refs.moreBtn"
          class="rounded-md border border-gray-200 bg-white shadow z-50"
          role="menu"
        >
          <ul class="py-1 max-h-[60vh] overflow-auto">
            <template x-for="t in others" :key="t.id">
              <li>
                <button
                  type="button"
                  class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 inline-flex items-center gap-2"
                  role="menuitem"
                  @click="open=false; $nextTick(()=>selectedTab = t.id)"
                >
                  <template x-if="t.icon">
                    <i :class="t.icon + ' text-[14px]'" aria-hidden="true"></i>
                  </template>
                  <span x-text="t.label"></span>
                </button>
              </li>
            </template>
          </ul>
        </div>
      </template>
    </div>
  </template>
</div>
