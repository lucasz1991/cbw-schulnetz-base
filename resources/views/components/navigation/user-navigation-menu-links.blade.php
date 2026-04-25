<!-- Gäste-Spezifische Navigation -->
    @auth
            <!-- Kunden-Spezifische Navigation -->
    @if (optional(Auth::user())->role === 'guest' || optional(Auth::user())->role === 'admin')
        <x-nav-link href="/user/dashboard" wire:navigate  :active="request()->is('user/dashboard')">
            <i class="fad fa-user-circle max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/dashboard') ? 'text-primary-500' : '' }}" aria-hidden="true"></i>
            {{ __('Konto') }}
        </x-nav-link>
        @if(Auth::user()?->person?->isEducation())
            <x-nav-link href="{{ route('reportbook') }}" wire:navigate  :active="request()->is('user/reportbook')">
                <i class="fad fa-book-open max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/reportbook') ? 'text-primary-500' : '' }}" aria-hidden="true"></i>
                {{ __('Berichtsheft') }} 
            </x-nav-link>
        @endif
        <x-nav-link href="{{ route('requests') }}" wire:navigate  :active="request()->is('user/user-requests')">
            <i class="fad fa-file-invoice max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/user-requests') ? 'text-primary-500' : '' }}" aria-hidden="true"></i>
            {{ __('Anträge') }}
        </x-nav-link>
        @if(false)
        <x-nav-link href="{{ route('contact') }}" wire:navigate  :active="request()->is('user/contact')">
            <i class="fad fa-envelope max-md:min-w-6 mr-2 max-md:mr-2 {{ request()->is('user/contact') ? 'text-primary-500' : '' }}" aria-hidden="true"></i>
            {{ __('Kontakt') }}
        </x-nav-link>
        @endif
    @endif
    @endauth    


