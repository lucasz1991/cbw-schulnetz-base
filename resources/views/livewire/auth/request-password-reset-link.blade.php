<x-layouts.auth-layout>
    <x-slot name="title">
    Passwort vergessen
    </x-slot>
    <x-slot name="description">
    Passwort vergessen? Kein Problem! Gib einfach deine E-Mail-Adresse ein, und wir senden dir einen Link zum Zurücksetzen deines Passworts. So kannst du schnell wieder auf deine Bewertungen zugreifen und weiterhin die Regulierungserfahrungen mit deiner Versicherung verfolgen oder aktualisieren.
    </x-slot>
    <x-slot name="form">
             <div  class="mt-8 w-xl shrink-0">
                                <x-validation-errors class="mb-4" />

                   

                @if (session()->has('success'))
                    <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
                        {{ session('error') }}
                    </div>
                @endif
                <form wire:submit.prevent="sendResetLink" class="space-y-4">
                    <div>
                                        <x-label for="email" value="E-Mail" />
                                        <x-input id="email" wire:model="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <x-button class="">
                        Link anfordern
                    </x-button>
                </form>
            </div>
    </x-slot>
</x-layouts.auth-layout>
