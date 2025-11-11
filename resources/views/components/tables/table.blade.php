@props([
    'columns'   => [],
    'items'     => [],
    'empty'     => 'Keine Einträge gefunden.',
    'class'     => '',
    'sortBy'    => null,
    'sortDir'   => 'asc',
    'rowView'   => null,
    'actionsView' => null,
])

@php
    use Illuminate\Support\Str;

    // Columns normalisieren
    $columns = collect($columns)->map(function ($c) {
        if (is_string($c)) {
            return [
                'label'    => $c,
                'key'      => Str::slug($c, '_'),
                'width'    => '1fr',
                'sortable' => false,
                'hideOn'   => 'none',
            ];
        }
        $label    = $c['label'] ?? '';
        $key      = $c['key']   ?? $label;
        $width    = $c['width'] ?? '1fr';
        $sortable = (bool)($c['sortable'] ?? false);
        $hideOn   = $c['hideOn'] ?? 'none';
        return compact('label','key','width','sortable','hideOn');
    });

    // Mapping für hideOn -> Utility-Klassen
    $hideClass = function (string $hideOn) {
        return match ($hideOn) {
            'sm'  => 'hidden sm:block',
            'md'  => 'hidden md:block',
            'lg'  => 'hidden lg:block',
            'xl'  => 'hidden xl:block',
            default => '', // 'none'
        };
    };

    // Sichtbarkeit je Breakpoint (sm(0), md(1), lg(2), xl(3))
    $isVisibleAt = function (string $hideOn, string $bp) {
        $order = ['sm' => 0, 'md' => 1, 'lg' => 2, 'xl' => 3];
        if ($hideOn === 'none') return true;
        return $order[$bp] >= $order[$hideOn];
    };

    // Template-Builder pro Breakpoint -> nur sichtbare Spalten + optional Actions-Track
    $buildTemplate = function (string $bp) use ($columns, $isVisibleAt, $actionsView) {
        $tracks = [];
        foreach ($columns as $c) {
            if ($isVisibleAt($c['hideOn'], $bp)) {
                $tracks[] = $c['width'] ?: '1fr';
            }
        }
        if (empty($tracks)) $tracks[] = '1fr';
        if ($actionsView)   $tracks[] = 'min-content';
        return implode(' ', $tracks);
    };

    $templateMd = $buildTemplate('md'); // >=768
    $templateLg = $buildTemplate('lg'); // >=1024
    $templateXl = $buildTemplate('xl'); // >=1280

    // Sort-Pfeil
    $arrowFor = function ($colKey, $sortBy, $sortDir) {
        if ($sortBy !== $colKey) return '';
        return $sortDir === 'asc' ? '▲' : '▼';
    };
@endphp

<div
  {{ $attributes->merge(['class' => 'w-full mt-4 relative '.$class]) }}
  x-data="{
      colsMd: '{{ $templateMd }}',
      colsLg: '{{ $templateLg }}',
      colsXl: '{{ $templateXl }}',
      headerStyle: '',
      rowStyle: '',
      // Breakpoints wie Tailwind
      updateTemplates() {
          const w = window.innerWidth || document.documentElement.clientWidth;
          // < md: Header bleibt hidden, Rows sind gestackt (kein grid-template nötig)
          if (w >= 1280) {
              this.headerStyle = `grid-template-columns: ${this.colsXl};`;
              this.rowStyle    = `grid-template-columns: ${this.colsXl};`;
          } else if (w >= 1024) {
              this.headerStyle = `grid-template-columns: ${this.colsLg};`;
              this.rowStyle    = `grid-template-columns: ${this.colsLg};`;
          } else if (w >= 768) {
              this.headerStyle = `grid-template-columns: ${this.colsMd};`;
              this.rowStyle    = `grid-template-columns: ${this.colsMd};`;
          } else {
              this.headerStyle = '';
              this.rowStyle    = '';
          }
      }
  }"
  x-init="
      updateTemplates();
      // Resize-Listener (passiv)
      window.addEventListener('resize', () => updateTemplates(), { passive: true });
  "
>
    {{-- Header (nur md+) --}}
    <div
      class="hidden md:grid bg-gray-100 p-2 font-semibold text-gray-700 border-b text-left text-sm pr-8"
      :style="headerStyle"
    >
        @foreach($columns as $col)
            @php $hidden = $hideClass($col['hideOn']); @endphp

            @if($col['sortable'])
                <button
                    type="button"
                    class="px-2 py-2 text-left flex items-center gap-1 {{ $hidden }}"
                    @click="$dispatch('table-sort', {
                        key: '{{ $col['key'] }}',
                        dir: '{{ ($sortBy == $col['key'] && $sortDir == 'asc') ? 'desc' : 'asc' }}'
                    })"
                >
                    <span>{{ $col['label'] }}</span>
                    <span class="text-[10px] opacity-70">{{ $arrowFor($col['key'], $sortBy, $sortDir) }}</span>
                </button>
            @else
                <div class="px-2 py-2 {{ $hidden }}">{{ $col['label'] }}</div>
            @endif
        @endforeach

        @if($actionsView)
            <div class="px-2 py-2 text-right"></div>
        @endif
    </div>

    {{-- Rows --}}
    @forelse($items as $item)
        <div class="relative border-b py-2 text-sm hover:bg-blue-50">
            <div class="grid items-center pr-8" :style="rowStyle">
                {{-- Zellen --}}
                @if($rowView)
                    @include($rowView, ['item' => $item, 'columnsMeta' => $columns, 'hideClass' => $hideClass])
                @else
                    @foreach($columns as $col)
                        <div class="px-2 py-2 {{ $hideClass($col['hideOn']) }}">—</div>
                    @endforeach
                @endif

            </div>
            {{-- Actions rechts --}}
            @if($actionsView)
                <div class=" absolute top-1 md:top-0 md:bottom-0 right-1 flex items-center">
                    @include($actionsView, ['item' => $item])
                </div>
            @endif
        </div>
    @empty
        <div class="p-4 text-sm text-gray-500">{{ $empty }}</div>
    @endforelse
</div>
