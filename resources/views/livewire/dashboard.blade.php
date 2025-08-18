<div class="w-full relative bg-cover bg-center bg-gray-100 pb-20 pt-8" wire:loading.class="cursor-wait">
    <div class="container mx-auto px-5" >
            <div x-data="{ selectedTab: 'basic' }" class="w-full">
                <div x-on:keydown.right.prevent="$focus.wrap().next()" x-on:keydown.left.prevent="$focus.wrap().previous()" class="flex gap-2 overflow-x-auto " role="tablist" aria-label="tab options">
                    <button x-on:click="selectedTab = 'basic'" x-bind:aria-selected="selectedTab === 'basic'" x-bind:tabindex="selectedTab === 'basic' ? '0' : '-1'" x-bind:class="selectedTab === 'basic' ? 'bg-white rounded-t-lg shadow font-bold text-primary border-b-2 border-secondary dark:border-primary-dark dark:text-primary-dark' : 'text-on-surface font-medium dark:text-on-surface-dark dark:hover:border-b-outline-dark-strong dark:hover:text-on-surface-dark-strong hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm" type="button" role="tab" aria-controls="tabpanelBasic" >Quali.Programm</button>
                    <button x-on:click="selectedTab = 'media'" x-bind:aria-selected="selectedTab === 'media'" x-bind:tabindex="selectedTab === 'media' ? '0' : '-1'" x-bind:class="selectedTab === 'media' ? 'bg-white rounded-t-lg font-bold text-primary border-b-2 border-secondary dark:border-primary-dark dark:text-primary-dark' : 'text-on-surface font-medium dark:text-on-surface-dark dark:hover:border-b-outline-dark-strong dark:hover:text-on-surface-dark-strong hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm" type="button" role="tab" aria-controls="tabpanelmedia" >Medien</button>
                    <button x-on:click="selectedTab = 'claims'" x-bind:aria-selected="selectedTab === 'claims'" x-bind:tabindex="selectedTab === 'claims' ? '0' : '-1'" x-bind:class="selectedTab === 'claims' ? 'bg-white rounded-t-lg font-bold text-primary border-b-2 border-secondary dark:border-primary-dark dark:text-primary-dark' : 'text-on-surface font-medium dark:text-on-surface-dark dark:hover:border-b-outline-dark-strong dark:hover:text-on-surface-dark-strong hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm" type="button" role="tab" aria-controls="tabpanelclaims" >Fehlzeiten</button>
                    <button x-on:click="selectedTab = 'tests'" x-bind:aria-selected="selectedTab === 'tests'" x-bind:tabindex="selectedTab === 'tests' ? '0' : '-1'" x-bind:class="selectedTab === 'tests' ? 'bg-white rounded-t-lg font-bold text-primary border-b-2 border-secondary dark:border-primary-dark dark:text-primary-dark' : 'text-on-surface font-medium dark:text-on-surface-dark dark:hover:border-b-outline-dark-strong dark:hover:text-on-surface-dark-strong hover:border-b-2 hover:border-b-outline-strong hover:text-on-surface-strong'" class="h-min px-4 py-2 text-sm" type="button" role="tab" aria-controls="tabpaneltests" >Prüfungen</button>
                </div>
                <div class="mt-6" >
                    <div x-cloak x-show="selectedTab === 'basic'" x-collapse id="tabpanelbasic" role="tabpanel" aria-label="basic">
                        <livewire:user.program-show lazy />
                    </div>
                    <div x-cloak x-show="selectedTab === 'media'"  id="tabpanelmedia" role="tabpanel" aria-label="media">
                        Hier werden Ihre vergangenen Meldungen von Fehlzeiten aufgelistet. Sie können hier nachvollziehen, welche Fehlzeiten Sie bereits gemeldet haben und den Status der jeweiligen Meldungen einsehen.
                    </div>
                    <div x-cloak x-show="selectedTab === 'claims'" id="tabpanelclaims" role="tabpanel" aria-label="claims">
                        <livewire:user.absences lazy />
                    </div>
                    <div x-cloak x-show="selectedTab === 'tests'" id="tabpaneltests" role="tabpanel" aria-label="tests">
                        <livewire:user.makeup-exam-registration lazy />
                    </div>
                </div>
            </div>
    </div>      
</div>
