<div class="w-full relative bg-cover bg-center bg-gray-100 pb-20 pt-8" wire:loading.class="cursor-wait">
    <div class="container mx-auto px-5" >
            <div x-data="{ selectedTab: 'basic' }" class="w-full">
                <div x-on:keydown.right.prevent="$focus.wrap().next()" x-on:keydown.left.prevent="$focus.wrap().previous()" class="flex gap-2 overflow-x-auto border-b border-outline dark:border-outline-dark" role="tablist" aria-label="tab options">
                    <button x-on:click="selectedTab = 'basic'" x-bind:aria-selected="selectedTab === 'basic'" x-bind:tabindex="selectedTab === 'basic' ? '0' : '-1'" x-bind:class="selectedTab === 'basic' ? 'bg-white rounded-t-lg shadow font-bold text-primary border-b-2 border-secondary dark:border-primary-dark dark:text-primary-dark' : 'text-on-surface font-medium dark:text-on-surface-dark dark:hover:border-b-outline-dark-strong dark:hover:text-on-surface-dark-strong hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm" type="button" role="tab" aria-controls="tabpanelBasic" >Allgemein</button>
                    <button x-on:click="selectedTab = 'abos'" x-bind:aria-selected="selectedTab === 'abos'" x-bind:tabindex="selectedTab === 'abos' ? '0' : '-1'" x-bind:class="selectedTab === 'abos' ? 'bg-white rounded-t-lg font-bold text-primary border-b-2 border-secondary dark:border-primary-dark dark:text-primary-dark' : 'text-on-surface font-medium dark:text-on-surface-dark dark:hover:border-b-outline-dark-strong dark:hover:text-on-surface-dark-strong hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm" type="button" role="tab" aria-controls="tabpanelAbos" >Abo's</button>
                    <button x-on:click="selectedTab = 'verification'" x-bind:aria-selected="selectedTab === 'verification'" x-bind:tabindex="selectedTab === 'verification' ? '0' : '-1'" x-bind:class="selectedTab === 'verification' ? 'bg-white rounded-t-lg font-bold text-primary border-b-2 border-secondary dark:border-primary-dark dark:text-primary-dark' : 'text-on-surface font-medium dark:text-on-surface-dark dark:hover:border-b-outline-dark-strong dark:hover:text-on-surface-dark-strong hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm" type="button" role="tab" aria-controls="tabpanelVerification" >Verifiziert</button>
                </div>
                <div class="px-2 py-20 text-on-surface dark:text-on-surface-dark">
                    <div x-cloak x-show="selectedTab === 'basic'" id="tabpanelGroups" role="tabpanel" aria-label="basic">
                        <div class="mr-auto font-semibold text-2xl place-self-center">
                            <h1 class="max-w-2xl mb-4 font-bold tracking-tight leading-none text-2xl xl:text-3xl">
                                Willkommen {{ $userData->name }},
                            </h1>
                            <p class="max-w-2xl mb-6 text-gray-500 md:text-lg lg:text-xl">
                                Deine Bewertungen und dein Versicherungsprofil im Überblick
                            </p>
                        </div>
                    
                        <!-- Statistiken -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                            <div class="bg-white shadow-lg rounded-lg p-5">
                                <h2 class="text-lg font-semibold text-gray-700">Abgegebene Bewertungen</h2>
                                <p class="text-3xl font-bold text-gray-500"></p>
                            </div>
                            <div class="bg-white shadow-lg rounded-lg p-5">
                                <h2 class="text-lg font-semibold text-gray-700">Verifiziert</h2>
                                <p class="text-3xl font-bold text-green-600"></p>
                            </div>
                            <div class="bg-white shadow-lg rounded-lg p-5">
                                <h2 class="text-lg font-semibold text-gray-700">In Prüfung</h2>
                                <p class="text-3xl font-bold text-yellow-600"></p>
                            </div>
                            <div class="bg-white shadow-lg rounded-lg p-5">
                                <h2 class="text-lg font-semibold text-gray-700">Durchschnittliche Bewertung</h2>
                                <p class="text-3xl font-bold text-indigo-500"></p>
                            </div>
                        </div>
                    
               
                        
                    </div>
                    <div x-cloak x-show="selectedTab === 'abos'" id="tabpanelLikes" role="tabpanel" aria-label="likes"><b><a href="#" class="underline">abos</a></b> tab is selected</div>
                    <div x-cloak x-show="selectedTab === 'verification'" id="tabpanelComments" role="tabpanel" aria-label="verification"><b><a href="#" class="underline">verification</a></b> tab is selected</div>
                </div>
            </div>
    </div>      
</div>
