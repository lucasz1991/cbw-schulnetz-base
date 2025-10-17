@props([
  'tabs' => [],
  'class' => 'flex items-end gap-2 overflow-x-auto transform -translate-y-[100%] -mb-6',
  'buttonClass' => 'inline-flex items-center justify-center min-h-[38px] px-4 py-2 text-sm rounded-t-lg border-b-2 border-t border-x border-x-gray-300 border-t-gray-300 bg-white',
])

<div x-on:keydown.right.prevent="$focus.wrap().next()"
     x-on:keydown.left.prevent="$focus.wrap().previous()"
     role="tablist"
     aria-label="tab options"
     {{ $attributes->merge(['class' => $class]) }}>
  @foreach($tabs as $tab)
    @php
      $id = $tab['id'];
      $label = $tab['label'] ?? Str::title($id);
      $icon  = $tab['icon'] ?? null; // darf ein gerendertes Blade/HtmlString sein
    @endphp
<button
  type="button"
  role="tab"
  :aria-selected="selectedTab === @js($id)"
  :tabindex="selectedTab === @js($id) ? '0' : '-1'"
  x-on:click="selectedTab = @js($id)"
  x-bind:class="selectedTab === @js($id)
    ? 'shadow font-semibold text-primary border-b-2 border-b-secondary !bg-blue-50'
    : 'bg-white text-on-surface font-medium border-b-white hover:border-b-blue-400 hover:border-b-outline-strong hover:text-on-surface-strong'"
  class="{{ $buttonClass }} {{ $loop->first ? 'max-md:ml-5' : '' }} {{ $loop->last ? 'max-md:mr-5' : '' }}"
  aria-controls="tabpanel-{{ $id }}"
  aria-label="{{ $label }}"   {{-- A11y: Name immer vorhanden --}}
  title="{{ $label }}"        {{-- optional: Tooltip --}}
>
  {{-- Icon --}}
  @if($icon)
    <span class="mr-1 max-md:mr-2">{!! $icon !!}</span>
  @endif

  {{-- Label nur auf kleinen Screens, nur wenn aktiv --}}
  <span class="sm:hidden" x-show="selectedTab === @js($id)">
    {{ $label }}
  </span>

  {{-- Label ab sm: immer sichtbar --}}
  <span class="hidden sm:inline">
    {{ $label }}
  </span>
</button>
  @endforeach
</div>
