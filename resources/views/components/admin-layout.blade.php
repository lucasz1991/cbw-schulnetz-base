<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title') | Admin {{ config('app.name', 'MiniFinds') }}</title>
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('site-images/favicon/apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site-images/favicon/favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('site-images/favicon/favicon-16x16.png') }}">
        
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head> 
    <body id="bodyid" class="font-sans antialiased bg-gray-100">
        <!-- PHP-basierte Sidebar-Entscheidung -->
        @php
            $sidebarOpen = session('opensidebar', true);
        @endphp
            <!-- Sidebar Wrapper -->
            <div id="main" x-data="{ 
                opensidebar: (localStorage.getItem('opensidebar') === null) 
                    ? {{ session('opensidebar', true) ? 'true' : 'false' }}
                    : JSON.parse(localStorage.getItem('opensidebar')),
                toggleSidebar() {
                    this.opensidebar = !this.opensidebar;
                }
            }" 
            x-init="
                $watch('opensidebar', value => {
                    localStorage.setItem('opensidebar', value);
                    fetch('/set-sidebar-state', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ opensidebar: value })
                    });
                })" 
            class="min-h-screen flex">

            
            <!-- Admin Side Navigation -->
            <div class="admin-side-navigation fixed top-0 h-screen transition-all duration-300 py-6 px-4 shadow-xl bg-white overflow-auto"
                :class="{ 'sidebar-closed': !opensidebar, 'sidebar-open': opensidebar }">
                @livewire('admin-navigation')
            </div>
             
            <!-- Content Wrapper -->
            <div :class="{ 'content-margin-closed': !opensidebar, 'content-margin-open': opensidebar }" 
            class="content-wrapper w-full {{ session('opensidebar', true) ? 'content-margin-open' : 'content-margin-closed' }}">

            
                <main  class="max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-[95vh]  w-full relative">
                    <!-- Top Navigation -->
                    @livewire('admin-navigation-top-bar')
                    
                    @livewire('user-alert')
                    <!-- Page Content -->

                    <div class="max-w-7xl mx-auto pb-10 pt-6" >
                        <div class="p-6 bg-white shadow-md rounded-lg">
                            {{ $slot }}
                        </div>
                    </div>

                    <!-- Application Version -->
                    <div class="w-full text-center mt-3 text-sm text-gray-500 mt-6 relative bottom-0">
                        <a onclick="Livewire.dispatch('showAlert', ['In Entwicklung bei LMZ Media. Applikationsversion: 1.1.3'])">Applikationsversion: 1.1.3</a> 
                    </div>
                    
                </main>

            
            </div>
            
        </div>
        
        @stack('modals')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        @livewireScripts
    </body>
</html>
