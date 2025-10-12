@props([
    'tabs' => [],
    'default' => null,
    'persistKey' => null,   // <— optional von außen setzen
])

@php
    use Illuminate\Support\Str;

    $firstKey   = array_key_first($tabs);
    $initial    = $default ?? $firstKey ?? 'tab-1';

    // Stabiler Fallback-Key: route + tabs-signatur
    $routeName  = optional(request()->route())->getName() ?? request()->path();
    $tabsSig    = implode(',', array_keys($tabs));
    $autoKey    = 'tabs:' . $routeName.$tabsSig;

    $key = $persistKey ?: $autoKey;
@endphp

<div
    x-data="{ openTab: $persist('{{ $initial }}').as('{{ $key }}') }"
    class="w-full"
    role="tablist"
     wire:key="tutor-course-tabs"
     wire:ignore
>
    <div class="flex -mb-[1px] space-x-2">
        @foreach($tabs as $k => $label)
            <button
                type="button"
                @click.prevent="openTab = '{{ $k }}'"
                :class="openTab === '{{ $k }}'
                    ? 'text-blue-600 text-semibold border-blue-300 bg-white border-b-0'
                    : 'text-gray-700 bg-gray-100 border-gray-300 border-b-blue-300'"
                class="px-4 py-2 text-sm font-medium transition-all border border-blue-300 border-b-blue-300 rounded-t-lg"
                role="tab"
                :aria-selected="openTab === '{{ $k }}'"
                :tabindex="openTab === '{{ $k }}' ? 0 : -1"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div>
        {{ $slot }}
    </div>
</div>
