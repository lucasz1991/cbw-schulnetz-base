@props(['type' => 'info'])

@php
    $styles = [
        'info' => [
            'wrap' => 'border-blue-100 bg-gradient-to-r from-blue-50 to-sky-50 text-blue-900',
            'icon' => 'bg-blue-100 text-blue-700',
            'sr'   => 'Info',
            'path' => 'M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z',
        ],
        'success' => [
            'wrap' => 'border-emerald-100 bg-gradient-to-r from-emerald-50 to-green-50 text-emerald-900',
            'icon' => 'bg-emerald-100 text-emerald-700',
            'sr'   => 'Erfolg',
            'path' => 'M10 20a10 10 0 1 1 0-20 10 10 0 0 1 0 20Zm-1-5 7-7-1.4-1.4L9 12.2 6.4 9.6 5 11l4 4Z',
        ],
        'warning' => [
            'wrap' => 'border-amber-200 bg-gradient-to-r from-amber-50 to-yellow-50 text-amber-900',
            'icon' => 'bg-amber-100 text-amber-700',
            'sr'   => 'Warnung',
            'path' => 'M10 1.7 1.4 17h17.2L10 1.7Zm0 11.8a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Zm-1-6h2v5H9v-5Z',
        ],
        'error' => [
            'wrap' => 'border-rose-200 bg-gradient-to-r from-rose-50 to-red-50 text-rose-900',
            'icon' => 'bg-rose-100 text-rose-700',
            'sr'   => 'Fehler',
            'path' => 'M10 20a10 10 0 1 1 0-20 10 10 0 0 1 0 20Zm3.5-13.5L12 5l-2 2-2-2L6.5 6.5l2 2-2 2L8 12l2-2 2 2 1.5-1.5-2-2 2-2Z',
        ],
    ];
    $variant = $styles[$type] ?? $styles['info'];
@endphp

<div {{ $attributes->merge(['class' => 'mb-4 flex max-w-full items-start gap-3 rounded-xl border p-4 text-sm shadow-sm '.$variant['wrap']]) }} role="alert">
    <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $variant['icon'] }}">
        <svg class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path d="{{ $variant['path'] }}"/>
        </svg>
    </span>
    <span class="sr-only">{{ $variant['sr'] }}</span>
    <div class="leading-relaxed">
        {{ $slot }}
    </div>
</div>
