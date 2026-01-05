@props([
    'mode' => 'basic',
    'size' => 'md',
])

@php
    $modeClasses = match ($mode) {

        'primary' => '
            text-white
            bg-primary-600 hover:bg-primary-700
            border-primary-700
            focus:ring-primary-300
            active:bg-primary-800
            shadow-sm hover:shadow
        ',

        'secondary' => '
            text-white
            bg-secondary-400 hover:bg-secondary-500
            border-secondary-400
            focus:ring-secondary-300
            active:bg-secondary-800
            shadow-sm hover:shadow
        ',

        // Standard-States
        'success' => '
            text-white bg-green-600 hover:bg-green-700
            border-green-700 focus:ring-green-300
            active:bg-green-800 shadow-sm hover:shadow
        ',
        'danger' => '
            text-white bg-red-600 hover:bg-red-700
            border-red-700 focus:ring-red-300
            active:bg-red-800 shadow-sm hover:shadow
        ',
        'warning' => '
            text-white bg-yellow-600 hover:bg-yellow-700
            border-yellow-700 focus:ring-yellow-300
            active:bg-yellow-800 shadow-sm hover:shadow
        ',
        'info' => '
            text-white bg-teal-600 hover:bg-teal-700
            border-teal-700 focus:ring-teal-300
            active:bg-teal-800 shadow-sm hover:shadow
        ',

        // Neutrale Varianten
        'light' => '
            text-gray-800 bg-gray-100 hover:bg-gray-200
            border-gray-200 focus:ring-gray-300
        ',
        'dark' => '
            text-white bg-gray-800 hover:bg-gray-900
            border-gray-900 focus:ring-gray-700
        ',
        'link' => '
            text-secondary-700 bg-transparent hover:bg-secondary-50
            border-transparent focus:ring-secondary-200
        ',
        'basic' => '
            text-gray-900 bg-white hover:bg-gray-100
            border-gray-300 focus:ring-gray-200
        ',
    };

    $sizeClasses = match ($size) {
        'sm'  => 'px-2.5 py-1.5 text-sm',
        'md'  => 'px-4 py-2 text-sm md:text-base',
        'lg'  => 'px-5 py-2.5 text-base',
        'xl'  => 'px-6 py-3 text-lg',
        '2xl' => 'px-7 py-3.5 text-xl',
    };

    $baseClasses = '
        inline-flex items-center justify-center text-center
        border rounded-lg
        transition-all duration-150
        focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white
        disabled:opacity-60 disabled:cursor-not-allowed
        select-none
    ';

    $classes = trim($modeClasses . ' ' . $sizeClasses . ' ' . $baseClasses);
@endphp

@if (isset($attributes['href']))
    <a
        {!! $attributes->merge(['class' => $classes]) !!}
        x-data="{ pressed: false }"
        @click="pressed = true; setTimeout(() => pressed = false, 120)"
        :class="pressed ? 'scale-[0.97]' : 'scale-100'"
    >
        {{ $slot }}
    </a>
@else
    <button
        {!! $attributes->merge(['class' => $classes]) !!}
        x-data="{ pressed: false }"
        @click="pressed = true; setTimeout(() => pressed = false, 120)"
        :class="pressed ? 'scale-[0.97]' : 'scale-100'"
    >
        {{ $slot }}
    </button>
@endif
