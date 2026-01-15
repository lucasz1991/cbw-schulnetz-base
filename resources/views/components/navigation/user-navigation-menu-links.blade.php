<!-- Gäste-Spezifische Navigation -->
@auth
            <!-- Kunden-Spezifische Navigation -->
    @if (optional(Auth::user())->role === 'guest' || optional(Auth::user())->role === 'admin')
        <x-nav-link href="/user/dashboard" wire:navigate  :active="request()->is('user/dashboard')">
            <i class="fad fa-user-circle max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/dashboard') ? 'text-primary-500' : '' }}" aria-hidden="true"></i>
            {{ __('Konto') }}
        </x-nav-link>
        <x-nav-link href="{{ route('reportbook') }}" wire:navigate  :active="request()->is('user/reportbook')">
            <i class="fad fa-book-open max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/reportbook') ? 'text-primary-500' : '' }}" aria-hidden="true"></i>
            {{ __('Berichtsheft') }}
        </x-nav-link>
        <x-nav-link href="{{ route('requests') }}" wire:navigate  :active="request()->is('user/user-requests')">
            <i class="fad fa-file-invoice max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/user-requests') ? 'text-primary-500' : '' }}" aria-hidden="true"></i>
            {{ __('Anträge') }}
        </x-nav-link>
    @endif
    @php
        $isActive = request()->is( 'user/faqs', 'user/contact', 'user/onboarding');
    @endphp
    <div x-data="{ openaboutus: false }" @click.away="openaboutus = false"   class="relative md:px-1 pt-1 border-b  text-sm font-medium leading-5  focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out {{ $isActive ? 'md:border-primary-500 text-secondary' : 'text-gray-500 hover:text-gray-700 border-transparent' }}" >
        <div class="flex items-center cursor-pointer max-md:text-lg max-md:px-3" @click="openaboutus = !openaboutus">
            <i class="fad fa-user-circle max-md:min-w-6 mr-2 max-md:mr-2  {{ $isActive ? 'text-primary-500' : '' }}" aria-hidden="true"></i>
                {{ __('Hilfe') }}
            <i class="fas fa-chevron-down ml-2 transition-all ease-in duration-200" :class="openaboutus ? 'transform rotate-180' : ''" aria-hidden="true"></i>
        </div>
        <div x-show="openaboutus" x-transition 
            x-cloak 
            class=" md:border-b md:border-gray-200 md:bg-white md:shadow-lg  max-md:mt-3 " 
            :class="isMobile ? 'relative   z-30' : 'fixed w-screen  z-10 overflow-hidden left-0 right-0 -top-[200%] opacity-0 transition-all duration-300 ease-in-out '"
            :style="!isMobile && openaboutus ? 'top: ' + navHeight + 'px; opacity:1;' : ''" 
            >
            <ul class=" max-md:space-y-4 max-md:pt-4 text-sm text-gray-500 hover:text-gray-700" :class="isMobile ? '' : 'py-4 container mx-auto flex flex-col md:justify-center md:flex-row md:space-x-8'">
                <li >
                    <a  href="{{ route('faqs') }}" wire:navigate  class="max-md:text-lg max-md:px-3 max-md:rounded-lg flex items-center md:px-4 py-2 hover:bg-gray-100 {{ request()->is('user/faqs') ? 'text-secondary' : '' }}">
                        <i class="fad fa-question-circle max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/faqs') ? 'text-primary-500' : '' }}"></i>
                    FAQ's
                    </a>
                </li>
                <!-- <li>
                    <a  href="{{ route('user.onboarding') }}" wire:navigate  class="max-md:text-lg max-md:px-3 max-md:rounded-lg flex items-center md:px-4 py-2 hover:bg-gray-100 {{ request()->is('user/onboarding') ? 'text-secondary' : '' }}">
                        <i class="fad fa-rocket-launch max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/onboarding') ? 'text-primary-500' : '' }}"></i>
                    Onboarding
                    </a>
                </li> -->
                <li>
                    <a  href="{{ route('contact') }}" wire:navigate  class="max-md:text-lg max-md:px-3 max-md:rounded-lg flex items-center md:px-4 py-2 hover:bg-gray-100 {{ request()->is('user/contact') ? 'text-secondary' : '' }}">
                        <i class="fad fa-envelope max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/contact') ? 'text-primary-500' : '' }}"></i>
                    Kontakt
                    </a>
                </li>
            </ul>
        </div>
    </div>
@endauth    


