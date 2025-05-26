<div  wire:loading.class="cursor-wait">
  <section class="relative" style="background-image: url('/site-images/background.jpg'); background-size: cover; background-position: 50% 80%;">
    <div class="absolute inset-0 bg-blue-50 opacity-80">
    </div>
    <div class="max-w-4xl mx-auto text-center  py-16 px-6 md:px-12 relative z-10">
      <h2 class="text-3xl md:text-4xl  text-gray-800 mb-4 mt-5">
        Vergleiche Versicherungen 
      </h2>
      <p class="text-lg md:text-xl text-gray-700 pb-8">
        Finde heraus, welche Versicherer wirklich überzeugen.<br>
        Unsere Auswertungen basieren auf echten Erfahrungen – anonym, unabhängig und verständlich aufbereitet.      
        </p>
      <div>
        <div class=" mb-4 w-full">
            <div class="grid grid-cols-12 ">
                <div class="col-span-6  rounded-md rounded-r-none bg-white  border border-transparent text-center text-sm text-white transition-all shadow-md hover:shadow-lg focus:bg-blue-200 focus:shadow-none active:bg-blue-200 hover:bg-blue-300 active:shadow-none disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none">
                    <select wire:model.live="insuranceType" placeholder="Typ auswählen"
                            class="appearance-none border-0 text-gray-600 w-full py-2 px-4 pr-8 bg-[#fff0] max-w-md">
                        <option value="">Alle Typen</option>
                        @foreach($insuranceTypes as $type)
                            <option class="max-w-md" value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-6 rounded-md rounded-l-none bg-white  border-l border-r  text-center text-sm text-white transition-all shadow-md hover:shadow-lg focus:bg-blue-200 focus:shadow-none active:bg-blue-200 hover:bg-blue-300  active:shadow-none disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none">
                    <select wire:model.live="insuranceSubType" placeholder="Art auswählen"
                            class="appearance-none border-0 text-gray-600 w-full py-2 px-4 pr-8 bg-[#fff0] max-w-md">
                        <option value="">Alle Arten</option>
                        @foreach($insuranceSubTypes as $SubType)
                            <option class="max-w-md" value="{{ $SubType->id }}">{{ $SubType->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
      </div>
    </div>
  </section>
    <section  class="bg-gray-100 pt-8">
        <x-filter.filter-container>
            <x-slot name="filters">
                <div class="p-2">
                    <x-filter.filter-search-field wire:model.live="search" />
                </div>
                <div class="p-2">
                    <label class="text-sm text-gray-600 mb-1 flex justify-left space-x-2 align-middle content-center">
                        <svg class="w-5" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 101.5483 81.854"><path  d="M12.5401,81.854a2.62509,2.62509,0,0,1-2.6233-2.6214V67.041H6.5149A6.52214,6.52214,0,0,1,0,60.5269V6.4994A6.50622,6.50622,0,0,1,6.4988,0H95.7456a5.80957,5.80957,0,0,1,5.8027,5.8034V60.5269a6.51332,6.51332,0,0,1-6.4987,6.5141H31.2L14.2168,81.2431A2.61867,2.61867,0,0,1,12.5401,81.854ZM6.4988,4.1437a2.358,2.358,0,0,0-2.355,2.3557V60.5269a2.37385,2.37385,0,0,0,2.3711,2.3706h5.4736a2.07185,2.07185,0,0,1,2.0719,2.0718v11.003L29.1188,63.3797a2.07035,2.07035,0,0,1,1.3286-.4822H95.0496a2.36537,2.36537,0,0,0,2.3552-2.3706V5.8034a1.66148,1.66148,0,0,0-1.6592-1.6597Z"/><path  d="M69.0928,47.8188a2.072,2.072,0,0,1-2.0422-2.4218l1.4635-8.5252-6.1952-6.038a2.07165,2.07165,0,0,1,1.1479-3.534l8.5598-1.2444,3.8291-7.757a2.0713,2.0713,0,0,1,3.7148,0l3.8294,7.757,8.5596,1.2444a2.07155,2.07155,0,0,1,1.1479,3.534l-6.1938,6.038,1.4054,8.1961a2.07468,2.07468,0,0,1-1.9706,2.7509h-.0148a2.07552,2.07552,0,0,1-.9645-.2379l-7.65588-4.025-7.65612,4.025A2.07443,2.07443,0,0,1,69.0928,47.8188Zm8.62042-8.6757a2.07318,2.07318,0,0,1,.96438.2382l4.9044,2.5789-.9362-5.462a2.0748,2.0748,0,0,1,.5962-1.8339l3.967-3.8678-5.4832-.797a2.07051,2.07051,0,0,1-1.5592-1.133l-2.45338-4.9698L75.2595,28.8665a2.07,2.07,0,0,1-1.5592,1.133l-5.483.797,3.9683,3.8678a2.07481,2.07481,0,0,1,.5963,1.8339l-.93768,5.4615,4.90448-2.5784A2.07362,2.07362,0,0,1,77.71322,39.1431Z"/><path  d="M55.305,44.8838a2.073,2.073,0,0,1-.9645-.2381l-5.7487-3.0219-5.7487,3.0219a2.07207,2.07207,0,0,1-3.0066-2.1844l1.0994-6.4015-4.6522-4.5335a2.07157,2.07157,0,0,1,1.1479-3.5339l6.42862-.934L46.7345,21.234a2.07107,2.07107,0,0,1,3.71472-.0006l2.87558,5.825,6.42742.934a2.0716,2.0716,0,0,1,1.14768,3.5339l-4.65068,4.5335,1.04128,6.0731a2.07467,2.07467,0,0,1-1.9585,2.7509C55.3238,44.8833,55.313,44.8838,55.305,44.8838Zm-6.7132-7.6727a2.07368,2.07368,0,0,1,.9645.238l2.9972,1.5761-.572-3.3384a2.07451,2.07451,0,0,1,.596-1.8337l2.4253-2.3638-3.3518-.487a2.06993,2.06993,0,0,1-1.5593-1.1331l-1.4999-3.0368-1.4985,3.0361a2.069,2.069,0,0,1-1.5592,1.1338l-3.3518.487,2.4251,2.3638a2.07487,2.07487,0,0,1,.5963,1.8344l-.5733,3.3371,2.9969-1.5755A2.074,2.074,0,0,1,48.5918,37.2111Z"/><path  d="M15.6777,44.8838a2.07182,2.07182,0,0,1-2.0422-2.4218l1.098-6.4022-4.6508-4.5335a2.0715,2.0715,0,0,1,1.1479-3.5339l6.427-.934,2.8758-5.825a2.06991,2.06991,0,0,1,1.8575-1.1545h0a2.06937,2.06937,0,0,1,1.85732,1.1551l2.87418,5.8244,6.4285.934a2.07157,2.07157,0,0,1,1.1479,3.5339l-4.6521,4.5335,1.0427,6.0731a2.07467,2.07467,0,0,1-1.9585,2.7509c-.008-.0005-.0188,0-.027,0a2.07163,2.07163,0,0,1-.9643-.2381l-5.7487-3.0219-5.7488,3.0219A2.07256,2.07256,0,0,1,15.6777,44.8838Zm.3022-13.3945,2.425,2.3638a2.07348,2.07348,0,0,1,.5962,1.8337l-.5718,3.3384,2.9971-1.5761a2.07489,2.07489,0,0,1,1.92882,0l2.99708,1.5755-.5733-3.3371a2.075,2.075,0,0,1,.59622-1.8344l2.42518-2.3638-3.3519-.487a2.0692,2.0692,0,0,1-1.5592-1.1338l-1.4984-3.0361-1.5,3.0368a2.06975,2.06975,0,0,1-1.5594,1.1331Z"/></svg>
                        <span>Mind. Bewertungen</span>
                    </label>
                    <input type="number" wire:model.debounce.500ms="minRatingCount"
                        min="1"
                        class="w-full px-3 py-2 border rounded-md text-sm border-blue-200" />
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
                        <option value="name_asc">Name A–Z</option>
                        <option value="name_desc">Name Z–A</option>
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
                    @if($insurances->count())
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach($insurances as $insurance)
                            <div>
                                <x-insurance.insurance-card :insurance="$insurance" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-10 text-gray-500">
                        Keine Versicherungen gefunden.
                    </div>
                @endif
                @if($insurances->count() >= $perPage * $pages)
                    <div class="mt-6 text-center">
                        <x-buttons.button-basic wire:click="loadMore">
                            Mehr laden
                        </x-buttons.button-basic>
                    </div>
                @endif
            </x-slot>
        </x-filter.filter-container>
    
    </section>
</div>
