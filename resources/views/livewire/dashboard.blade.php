<div class="w-full relative border-t border-t-gray-300 bg-cover bg-center bg-[#eeeeeebd] pb-20" wire:loading.class="cursor-wait">
    <div class="container mx-auto px-5" >
            <div x-data="{ selectedTab: $persist('basic') }" class="w-full">
                <div x-on:keydown.right.prevent="$focus.wrap().next()" x-on:keydown.left.prevent="$focus.wrap().previous()" class="flex gap-2 overflow-x-auto transform -translate-y-[100%] -mb-6" role="tablist" aria-label="tab options">
                    <button x-on:click="selectedTab = 'basic'" x-bind:aria-selected="selectedTab === 'basic'" x-bind:tabindex="selectedTab === 'basic' ? '0' : '-1'" x-bind:class="selectedTab === 'basic' ? ' shadow font-semibold text-primary border-b-2 border-b-secondary bg-blue-50' : 'bg-white text-on-surface font-medium  hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm  rounded-t-lg border-t border-x border-x-gray-300 border-t-gray-300" type="button" role="tab" aria-controls="tabpanelBasic" >Quali.Programm</button>
                    <button x-on:click="selectedTab = 'media'" x-bind:aria-selected="selectedTab === 'media'" x-bind:tabindex="selectedTab === 'media' ? '0' : '-1'" x-bind:class="selectedTab === 'media' ? ' shadow font-semibold text-primary border-b-2 border-b-secondary bg-blue-50' : 'bg-white text-on-surface font-medium  hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm rounded-t-lg border-t border-x border-x-gray-300 border-t-gray-300" type="button" role="tab" aria-controls="tabpanelmedia" >Medien</button>
                    <button x-on:click="selectedTab = 'claims'" x-bind:aria-selected="selectedTab === 'claims'" x-bind:tabindex="selectedTab === 'claims' ? '0' : '-1'" x-bind:class="selectedTab === 'claims' ? ' shadow font-semibold text-primary border-b-2 border-b-secondary bg-blue-50' : 'bg-white text-on-surface font-medium  hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm rounded-t-lg border-t border-x border-x-gray-300 border-t-gray-300" type="button" role="tab" aria-controls="tabpanelclaims" >Fehlzeiten</button>
                    <button x-on:click="selectedTab = 'tests'" x-bind:aria-selected="selectedTab === 'tests'" x-bind:tabindex="selectedTab === 'tests' ? '0' : '-1'" x-bind:class="selectedTab === 'tests' ? ' shadow font-semibold text-primary border-b-2 border-b-secondary bg-blue-50' : 'bg-white text-on-surface font-medium  hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm rounded-t-lg border-t border-x border-x-gray-300 border-t-gray-300" type="button" role="tab" aria-controls="tabpaneltests" >Prüfungen</button>
                </div>
                <div class="" >
                    <div x-cloak x-show="selectedTab === 'basic'" x-collapse id="tabpanelbasic" role="tabpanel" aria-label="basic">
                        <livewire:user.program-show lazy />
                    </div>
                    <div x-cloak x-show="selectedTab === 'media'"  x-collapse id="tabpanelmedia" role="tabpanel" aria-label="media">
                        <livewire:user.media-pool lazy />
                    </div>
                    <div x-cloak x-show="selectedTab === 'claims'" x-collapse id="tabpanelclaims" role="tabpanel" aria-label="claims">
                        <livewire:user.absences lazy />
                    </div>
                    <div x-cloak x-show="selectedTab === 'tests'" x-collapse id="tabpaneltests" role="tabpanel" aria-label="tests">
                        <livewire:user.makeup-exam-registration lazy />
                    </div>
                </div>
            </div>
    </div>      
</div>
