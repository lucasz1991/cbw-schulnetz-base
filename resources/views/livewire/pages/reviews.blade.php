<div>

    <div class=" bg-gray-50">
        <div class="container mx-auto px-4 py-8">
            {{-- Zusammenfassung --}}
            <div class="bg-white shadow rounded-lg p-6 mb-8">

                <p class="text-xl mb-3">Ø durschnitts Bewertung:             
                    </p>
                    <x-insurance.insurance-rating-stars :score="$average" />
                <p class="text-gray-600">{{ $totalCount }} Bewertungen insgesamt</p>
            </div>
        </div>
        <div class="container mx-auto ">
            {{-- Einzelbewertungen --}}
                <x-filter.filter-container>
                    <x-slot name="filters">
                        <div class="p-2">
                            <x-filter.filter-search-field wire:model.live="search" />
                        </div>
                        <div class="p-2">
                            <label class=" text-sm text-gray-600 mb-1 flex justify-left space-x-2 align-middle content-center">
                                <svg class="w-4 h-4" viewBox="0 0 20 20">
                                    <path fill="#fbbf24" stroke="1px" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.364 1.118l1.286 3.957c.3.921-.755 1.688-1.54 1.118l-3.37-2.448a1 1 0 00-1.176 0l-3.37 2.448c-.784.57-1.838-.197-1.54-1.118l1.286-3.957a1 1 0 00-.364-1.118L2.049 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/>
                                </svg>
                                <span>
                                    Mind. Ø Sterne
                                </span>
                            </label>
                            <select wire:model.live="minAvgScore"
                                    class="w-full px-3 py-2 border rounded-md text-sm border-blue-200">
                                <option value="">Keine Auswahl</option>
                                <option value=".1">0,5+</option>
                                <option value=".2">1+</option>
                                <option value=".4">2+</option>
                                <option value=".6">3+</option>
                                <option value=".8">4+</option>
                                <option value=".9">4,5+</option>
                            </select>
                        </div>
                        <div class="p-2">
                            <label class="text-sm text-gray-600 mb-1 flex justify-left space-x-2 align-middle content-center">
                                <svg class="w-4 h-4" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40.2299 34.1955"><polygon  points="8.046 6.034 8.046 34.195 4.023 34.195 4.023 6.034 0 6.034 6.034 0 12.069 6.034 8.046 6.034"/><path  d="M14.0804,4.023V0H40.2299V4.023Zm0,10.0575v-4.023h20.115v4.023Zm0,20.115v-4.023h8.046v4.023Zm0-10.0575V20.115H28.1609v4.023Z"/></svg>
                                <span>Sortierung</span>
                            </label>
                            <select wire:model.live="sort"
                                    class="w-full px-3 py-2 pr-8 border rounded-md text-sm border-blue-200">
                                    <option value="created_at_desc">Datum ↓</option>
                                    <option value="created_at_asc">Datum ↑</option>
                                <option value="score_desc">Ø Sterne ↓</option>
                                <option value="score_asc">
                                    Ø Sterne ↑
                                </option>
                                <option value="ratings_desc">Bewertungen ↓</option>
                                <option value="ratings_asc">Bewertungen ↑</option>
                            </select>
                        </div>
                        <div class="p-2">
                            <x-buttons.button-basic wire:click="resetFilters" class="mt-4 text-sm text-blue-600 w-full">
                                Filter zurücksetzen
                            </x-buttons.button-basic>
                        </div>
                    </x-slot>
                    <x-slot name="listContent">
                            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                                @foreach ($ratings as $rating)
                                    <div class="swiper-slide h-full"  wire:key="rating-{{ $rating->id }}">
                                        <x-claim-rating.claim-rating-card :rating="$rating" />
                                    </div>
                                @endforeach
                            </div>
                    </x-slot>
                </x-filter.filter-container>
        </div>

    </div>
</div>
