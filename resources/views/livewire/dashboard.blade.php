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
                                <p class="text-3xl font-bold text-gray-500">{{ $ratingsCount }}</p>
                            </div>
                            <div class="bg-white shadow-lg rounded-lg p-5">
                                <h2 class="text-lg font-semibold text-gray-700">Verifiziert</h2>
                                <p class="text-3xl font-bold text-green-600">{{ $verifiedRatingsCount }}</p>
                            </div>
                            <div class="bg-white shadow-lg rounded-lg p-5">
                                <h2 class="text-lg font-semibold text-gray-700">In Prüfung</h2>
                                <p class="text-3xl font-bold text-yellow-600">{{ $pendingRatingsCount }}</p>
                            </div>
                            <div class="bg-white shadow-lg rounded-lg p-5">
                                <h2 class="text-lg font-semibold text-gray-700">Durchschnittliche Bewertung</h2>
                                <p class="text-3xl font-bold text-indigo-500">{{ number_format($averageScore, 1) }}/5</p>
                            </div>
                        </div>
                    
                        <!-- Bewertungen -->

                        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Deine letzten Bewertungen</h2>
                        <div class="grid grid-cols-1 gap-6">
                            @forelse ($claimRatings as $rating)
                                <div class="bg-white shadow rounded-lg p-5" wire:key="rating-{{ $rating->id }}" x-data="{ open: false }">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800">
                                                {{ $rating->insurance->name ?? 'Unbekannte Versicherung' }}
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                Eingereicht am {{ $rating->created_at->format('d.m.Y') }}
                                            </p>
                                            <p class="text-sm mt-2">
                                                Status:
                                                @switch($rating->status)
                                                    @case('open')
                                                        <span class="text-blue-600 font-medium">Offen</span>
                                                        @break
                                                    @case('pending')
                                                        <span class="text-yellow-600 font-medium">In Prüfung</span>
                                                        @break
                                                    @case('verified')
                                                        <span class="text-green-600 font-medium">Verifiziert</span>
                                                        @break
                                                    @case('rejected')
                                                        <span class="text-red-600 font-medium">Abgelehnt</span>
                                                        @break
                                                    @default
                                                        <span class="text-gray-600 font-medium">Unbekannt</span>
                                                @endswitch
                                            </p>
                    
                                        </div>
                    
                                        <button @click="open = !open" class="text-blue-500 hover:underline">Optionen</button>
                                    </div>
                    
                                    <div x-show="open" x-collapse x-cloak class="mt-4 border-t pt-3 space-y-2 text-sm text-gray-700">
                                        @if (!$rating->user_id)
                                            <p class="text-yellow-600">Noch nicht verifiziert – <a href="{{ route('login') }}" class="text-blue-600 underline">Jetzt verifizieren</a></p>
                                        @endif
                    
                                        <a href=""
                                           class="inline-block text-sm text-blue-600 hover:underline">Details ansehen</a>
                    
                                        <a href="#" wire:click.prevent="delete({{ $rating->id }})"
                                           class="inline-block text-sm text-red-500 hover:underline">Löschen</a>
                                    </div>
                                </div>
                            @empty
                                <div class="relative w-full overflow-hidden rounded-md border border-sky-500 bg-sky-50 text-on-surface dark:bg-surface-dark dark:text-on-surface-dark" role="alert">
                                    <div class="flex w-full items-center gap-2 bg-info/10 p-4">
                                        <div class="bg-sky-500/15 text-sky-500 rounded-full p-1" aria-hidden="true">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-6" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-2">
                                            <p class="text-xs font-medium sm:text-sm">
                                                <span class="block sm:inline">Du hast noch keine Bewertungen abgegeben.</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforelse
                    
                            @if ($claimRatings->hasPages())
                                <div class="mt-6">
                                    {{ $claimRatings->links('vendor.pagination.tailwind') }}
                                </div>
                            @endif
                        </div>
                        
                    </div>
                    <div x-cloak x-show="selectedTab === 'abos'" id="tabpanelLikes" role="tabpanel" aria-label="likes"><b><a href="#" class="underline">abos</a></b> tab is selected</div>
                    <div x-cloak x-show="selectedTab === 'verification'" id="tabpanelComments" role="tabpanel" aria-label="verification"><b><a href="#" class="underline">verification</a></b> tab is selected</div>
                </div>
            </div>
    </div>      
</div>
