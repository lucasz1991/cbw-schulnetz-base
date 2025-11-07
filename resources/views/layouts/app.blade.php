<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        <x-meta-page-header />
        <title>@yield('title') | {{ config('app.name') }}</title>
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('site-images/favicon/favicon.jpg') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site-images/favicon/favicon.jpg') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('site-images/favicon/favicon.jpg') }}">

        <link rel="stylesheet" href="/adminresources/css/swiper-bundle.min.css">
        <script src="/adminresources/js/swiper-bundle.min.js"></script>
        <link href="{{ URL::asset('adminresources/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ URL::asset('adminresources/choices.js/public/assets/styles/choices.min.css') }}" rel="stylesheet" type="text/css" />
        <script src="{{ URL::asset('adminresources/choices.js/public/assets/scripts/choices.min.js') }}"></script>
        <script src="{{ URL::asset('adminresources/flatpickr/flatpickr.min.js') }}"></script>
        <script src="{{ URL::asset('adminresources/flatpickr/l10n/de.js') }}"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
        <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
        <link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" type="text/css" />
        <script src="{{ URL::asset('adminresources/apexcharts/apexcharts.min.js') }}"></script>
        <link rel="stylesheet" href="{{ asset('adminresources/fontawesome6/css/all.min.css') }}">

        
        <!-- Styles -->
        @vite(['resources/css/app.css'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class=" antialiased ">
        <div id="main" class="snap-y">
            @livewire('user-alert')
            @if(Auth::check())
                <header class="snap-start">
                    @livewire('user-navigation-menu')
                </header>
            @endif
            <x-page-header />
            <x-pagebuilder-module :position="'top_banner'"/>
            <x-pagebuilder-module :position="'banner'"/>
            <x-pagebuilder-module :position="'bottom_banner'"/>
            <main  class="snap-start z-0">
                <x-pagebuilder-module/>
                <x-pagebuilder-module :position="'above_content'"/>
                {{ $slot }}
                <x-pagebuilder-module :position="'content'"/>
            </main>
        </div>
        @if(Auth::check())
            <x-pagebuilder-module :position="'footer'"/>
            @livewire('footer')
            @livewire('tools.chatbot')
            <livewire:tools.file-pools.file-preview-modal />
            @stack('modals')
        @endif
        
        
        <!-- Scripts -->
        @vite(['resources/js/app.js'])
        @livewireScripts
        @yield('js')
    </body>
</html>
