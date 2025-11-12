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
               <!-- Datenschutz -->
               <div class="col-span-6">
                    <label for="terms" class="inline-flex items-center mb-5 cursor-pointer">
                       <input wire:model="terms" id="terms" name="terms" type="checkbox" value="" class="sr-only peer">
                       <div class="relative w-9 min-w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                       <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">
                            Mit der Erstellung eines Kontos stimme ich den
                            <a href="/termsandconditions" wire:navigate class="text-gray-700 underline">Allgemeinen Geschäftsbedingungen</a>
                            und der
                            <a href="/privacypolicy" wire:navigate class="text-gray-700 underline">Datenschutzerklärung</a> zu.
                       </span>
                    </label> 
               </div>
            <!-- Buttons -->
            <div class="col-span-6 sm:flex sm:items-center sm:gap-4">
                <x-button wire:click="register"  wire:loading.class="cursor-progress pointer-events-none " wire:navigate >
                    Registrieren
                </x-button>
                <p class="mt-4 text-sm text-gray-500 sm:mt-0">
                       Du hast schon ein Konto?
                       <a href="/login" wire:navigate  class="text-gray-700 underline">Einloggen</a>.
                </p>
            </div>
        </div>
    </x-slot>
</x-layouts.auth-layout>