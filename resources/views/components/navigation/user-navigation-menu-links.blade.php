    <!-- Gäste-Spezifische Navigation -->
    @auth
            <!-- Kunden-Spezifische Navigation -->
    @if (optional(Auth::user())->role === 'guest' || optional(Auth::user())->role === 'admin')
        <x-nav-link href="/user/dashboard" wire:navigate  :active="request()->is('user/dashboard')">
            <svg class="w-5 max-md:w-6 aspect-square mr-1 max-md:mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 4h3a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3m0 3h6m-3 5h3m-6 0h.01M12 16h3m-6 0h.01M10 3v4h4V3h-4Z"/>
                </svg>
            {{ __('Mein Konto') }}
        </x-nav-link>
    @endif  

        
@php
  $isActive = request()->is('aboutus', 'user/faqs', 'howto', 'user/contact');
@endphp

<div x-data="{ openaboutus:false }"
     @click.outside="openaboutus=false"
     class="relative md:px-1 pt-1 border-b text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out
            {{ $isActive ? 'md:border-primary-500 md:text-gray-900 max-md:text-lg max-md:w-full max-md:rounded-lg  max-md:py-2 max-md:bg-gray-100' : 'text-gray-500 hover:text-gray-700 border-transparent' }}">

  <!-- Trigger -->
  <button type="button"
          class="flex items-center cursor-pointer max-md:text-lg max-md:px-3"
          @click="openaboutus = !openaboutus"
          :aria-expanded="openaboutus ? 'true' : 'false'"
          aria-controls="aboutus-submenu">
<svg class="w-5 max-md:w-6 aspect-square mr-1 max-md:mr-2 " aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"> <path stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M16 19h4a1 1 0 0 0 1-1v-1a3 3 0 0 0-3-3h-2m-2.236-4a3 3 0 1 0 0-4M3 18v-1a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Zm8-10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/> </svg>    {{ __('Hilfe') }}
<svg class="w-4 h-4 ml-2 transition-all ease-in duration-200" :class="openaboutus ? 'transform rotate-180' : ''" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"> <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m19 9-7 7-7-7"/> </svg>
  </button>

  <!-- Submenü -->
  <div id="aboutus-submenu"
       x-cloak
       x-show="openaboutus"
       x-transition.opacity.duration.150ms
       class="md:border-b md:border-gray-200 md:bg-white md:shadow-lg max-md:mt-3">
    <ul class="max-md:space-y-4 max-md:pt-4 text-sm text-gray-500 hover:text-gray-700
               md:py-4 md:container md:mx-auto md:flex md:flex-row md:space-x-8 max-md:pl-4">
      <li>
        <a href="{{ route('faqs') }}" wire:navigate
           class="max-md:text-lg max-md:px-3 max-md:rounded-lg flex items-center md:px-4 py-2 hover:bg-gray-100">
<svg class="w-5 max-md:w-6 aspect-square mr-1 max-md:mr-2 " aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"> <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.529 9.988a2.502 2.502 0 1 1 5 .191A2.441 2.441 0 0 1 12 12.582V14m-.01 3.008H12M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/> </svg>          FAQ's
        </a>
      </li>
      <li>
        <a href="{{ route('contact') }}" wire:navigate
           class="max-md:text-lg max-md:px-3 max-md:rounded-lg flex items-center md:px-4 py-2 hover:bg-gray-100">
<svg class="w-5 max-md:w-6 aspect-square mr-1 max-md:mr-2 " aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"> <path stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="m3.5 5.5 7.893 6.036a1 1 0 0 0 1.214 0L20.5 5.5M4 19h16a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z"/> </svg>          Kontakt
        </a>
      </li>
    </ul>
  </div>
</div>

    @endauth    


