<div class="w-full relative border-t border-t-gray-300 bg-cover bg-center bg-[#eeeeeebd] pb-20" wire:loading.class="cursor-wait">
    <div class="" >
            <div x-data="{ selectedTab: $persist('basic') }" class="w-full">
                <div class="container mx-auto md:px-5 ">
                    <div x-on:keydown.right.prevent="$focus.wrap().next()" x-on:keydown.left.prevent="$focus.wrap().previous()" class="flex gap-2 overflow-x-auto transform -translate-y-[100%] -mb-6" role="tablist" aria-label="tab options">
                        <button x-on:click="selectedTab = 'basic'" 
                            x-bind:aria-selected="selectedTab === 'basic'" 
                            x-bind:tabindex="selectedTab === 'basic' ? '0' : '-1'" 
                            x-bind:class="selectedTab === 'basic' ? ' shadow font-semibold text-primary border-b-2 border-b-secondary !bg-blue-50' : 'bg-white text-on-surface font-medium border-b-white hover:border-b-blue-400 hover:border-b-outline-strong hover:text-on-surface-strong'" 
                            class="inline-flex items-center h-min px-4 py-2 text-sm  rounded-t-lg border-b-2 border-t border-x border-x-gray-300 border-t-gray-300 bg-white max-md:ml-5" 
                            type="button" 
                            role="tab" 
                            aria-controls="tabpanelBasic" 
                            >
                            <svg class="w-5   mr-1 max-md:mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"  fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 4h3a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3m0 3h6m-3 5h3m-6 0h.01M12 16h3m-6 0h.01M10 3v4h4V3h-4Z"></path>
                            </svg>
                           Übersicht
                        </button>
                        <button x-on:click="selectedTab = 'tests'" 
                            x-bind:aria-selected="selectedTab === 'tests'" 
                            x-bind:tabindex="selectedTab === 'tests' ? '0' : '-1'" 
                            x-bind:class="selectedTab === 'tests' ? ' shadow font-semibold text-primary border-b-2 border-b-secondary bg-blue-50' : 'bg-white text-on-surface font-medium border-b-white hover:border-b-blue-400 hover:border-b-outline-strong hover:text-on-surface-strong'" 
                            class="inline-flex items-center h-min px-4 py-2 text-sm rounded-t-lg border-b-2 border-t border-x border-x-gray-300 border-t-gray-300 bg-white" 
                            type="button" 
                            role="tab" 
                            aria-controls="tabpaneltests" 
                            >
                            <svg xmlns="http://www.w3.org/2000/svg"  class="w-4   mr-1 max-md:mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Termine
                        </button>
                        <button x-on:click="selectedTab = 'claims'" 
                            x-bind:aria-selected="selectedTab === 'claims'" 
                            x-bind:tabindex="selectedTab === 'claims' ? '0' : '-1'" 
                            x-bind:class="selectedTab === 'claims' ? ' shadow font-semibold text-primary border-b-2 border-b-secondary bg-blue-50' : 'bg-white text-on-surface font-medium border-b-white hover:border-b-blue-400 hover:border-b-outline-strong hover:text-on-surface-strong'" 
                            class="inline-flex items-center h-min px-4 py-2 text-sm rounded-t-lg border-b-2 border-t border-x border-x-gray-300 border-t-gray-300 bg-white max-md:mr-5" 
                            type="button" 
                            role="tab" 
                            aria-controls="tabpanelclaims" 
                            >
                            <svg xmlns="http://www.w3.org/2000/svg"  class="w-4   mr-1 max-md:mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>                        
                            Medien
                        </button>
                    </div>
                </div>
                <div class="container mx-auto px-5" >
                    <div x-cloak x-show="selectedTab === 'basic'" x-collapse id="tabpanelbasic" role="tabpanel" aria-label="basic">
<div class="py-6 space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $course['baustein'] ?? '—' }}</h1>
            <p class="text-gray-600">
                {{ $course['kurzbez'] ?? '—' }} · {{ $course['zeitraum_fmt'] ?? '—' }}
            </p>
            <p class="mt-1 text-sm">
                Status: <span class="font-medium">{{ $course['status'] ?? '—' }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if($prev)
                <a href="{{ route('user.program.course.show', ['courseId' => $prev['baustein_id'] ?? $prev['slug']]) }}"
                   class="bg-white px-3 py-2 rounded border hover:bg-gray-50">← Vorheriger</a>
            @endif
            @if($next)
                <a href="{{ route('user.program.course.show', ['courseId' => $next['baustein_id'] ?? $next['slug']]) }}"
                   class="bg-white px-3 py-2 rounded border hover:bg-gray-50">Nächster →</a>
            @endif
        </div>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg border shadow p-4">
            <h3 class="font-semibold mb-2">Leistung</h3>
            <dl class="text-sm grid grid-cols-2 gap-y-1">
                <dt class="text-gray-500">Punkte (TN)</dt><dd class="text-right">{{ $course['punkte'] ?? '—' }}</dd>
                <dt class="text-gray-500">Schnitt (TN)</dt><dd class="text-right">{{ $course['schnitt'] ?? '—' }}</dd>
                <dt class="text-gray-500">Klassenschnitt</dt><dd class="text-right">{{ $course['klassenschnitt'] ?? '—' }}</dd>
                <dt class="text-gray-500">Fehltage</dt><dd class="text-right">{{ $course['fehltage'] ?? '—' }}</dd>
            </dl>
        </div>
        <div class="bg-white rounded-lg border shadow p-4">
            <h3 class="font-semibold mb-2">Organisation</h3>
            <dl class="text-sm grid grid-cols-2 gap-y-1">
                <dt class="text-gray-500">Tage</dt><dd class="text-right">{{ $course['tage'] ?? '—' }}</dd>
                <dt class="text-gray-500">Unterrichtsklasse</dt><dd class="text-right">{{ $course['unterrichtsklasse'] ?? '—' }}</dd>
                <dt class="text-gray-500">Beginn</dt><dd class="text-right">{{ $course['beginn'] ?? '—' }}</dd>
                <dt class="text-gray-500">Ende</dt><dd class="text-right">{{ $course['ende'] ?? '—' }}</dd>
            </dl>
        </div>
        <div class="bg-white rounded-lg border shadow p-4">
            <h3 class="font-semibold mb-2">Meta</h3>
            <dl class="text-sm grid grid-cols-2 gap-y-1">
                <dt class="text-gray-500">Baustein-ID</dt><dd class="text-right">{{ $course['baustein_id'] ?? '—' }}</dd>
                <dt class="text-gray-500">Kurzbez.</dt><dd class="text-right">{{ $course['kurzbez'] ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    {{-- Optional: Seitenleiste mit allen Bausteinen --}}
    {{-- <div class="bg-white rounded-lg border shadow p-4">
        <h3 class="font-semibold mb-3">Alle Bausteine</h3>
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-2">
            @foreach($bausteine as $b)
                @php $id = $b['baustein_id'] ?? $b['slug']; @endphp
                <a href="{{ route('user.program.course.show', ['courseId' => $id]) }}"
                   class="px-3 py-2 rounded border text-sm hover:bg-gray-50 {{ ($course['baustein_id'] ?? $course['slug']) === $id ? 'bg-gray-100' : '' }}">
                    {{ $b['baustein'] }}
                </a>
            @endforeach
        </div>
    </div> --}}
</div>

                    </div>
                </div>
            </div>
    </div>      
</div>
