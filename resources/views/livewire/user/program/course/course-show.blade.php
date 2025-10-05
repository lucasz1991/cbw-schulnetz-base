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
    
                    </div>
                </div>
                <div class="container mx-auto px-5" >
                    <div x-cloak x-show="selectedTab === 'basic'" x-collapse id="tabpanelbasic" role="tabpanel" aria-label="basic">
                        <div class="py-4">
                            <h2 class="text-lg font-semibold">Übersicht</h2>
                            <p class="mt-2">Hier finden Sie eine Übersicht über die wichtigsten Informationen.</p>
                            Kurs {{ $courseId }} .
                        </div>
                    </div>
                </div>
            </div>
    </div>      
</div>
