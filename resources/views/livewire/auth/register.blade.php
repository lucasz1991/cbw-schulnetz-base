<x-layouts.auth-layout>
    <x-slot name="title">
        CBW Schulnetz
    </x-slot>
    <x-slot name="description">
        Hier finden Sie Infos zu Ihrem Qualiprogramm und Kontaktmöglichkeit zur CBW-Verwaltung
    </x-slot>
    <x-slot name="form">
        <div  class="mt-8 grid grid-cols-6 gap-6"  wire:loading.class="cursor-wait opacity-50 animate-pulse"> 
            <x-alert class="col-span-6 mb-4" type="info">
                <h3 class="text-lg font-medium text-gray-900">Willkommen im CBW Schulnetz!</h3>
                <p class="mt-2 text-sm text-gray-700">
                    Bitte registriere dich mit der E-Mail-Adresse, die du bei deiner Anmeldung zum Qualiprogramm angegeben hast. 
                    Du erhältst dann eine E-Mail, um dein Passwort zu setzen und dein Konto zu aktivieren.
                    Falls du keine E-Mail erhalten hast, überprüfe bitte auch deinen Spam-Ordner.
                    Solltest du weiterhin Probleme haben, kontaktiere bitte unseren 
                    <a href="mailto:support@cbw-schulnetz.de">Support</a>.
                </p>
            </x-alert>
            <!-- E-Mail und Benutzername -->
            <div class="col-span-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- E-Mail -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">E-Mail</label>
                       <input 
                           type="email" 
                           id="email" 
                           name="email" 
                           wire:model="email"
                           value="{{ old('email') }}" 
                           class="w-full rounded-lg border-gray-300 p-3 mt-1 text-sm"
                           placeholder="E-Mail-Adresse" 
                        
                       />
                       <x-input-error for="email" class="mt-2" />
                   </div>
               </div>

            <!-- Buttons -->
            <div class="col-span-6 sm:flex sm:items-center sm:gap-4">
                <x-buttons.button-basic :size="'md'" wire:click="register"  wire:loading.class="cursor-progress pointer-events-none " wire:navigate >
                    <i class="fa fa-user-plus mr-2 text-gray-600"></i>
                    Registrieren
                </x-buttons.button-basic>
                <p class="mt-4 text-sm text-gray-500 sm:mt-0">
                       Du hast schon ein Konto?
                       <a href="/login" wire:navigate  class="text-gray-700 underline">Einloggen</a>.
                </p>
            </div>
        </div>
    </x-slot>
</x-layouts.auth-layout>