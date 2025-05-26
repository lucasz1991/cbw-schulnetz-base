<div x-data="{ 
    selectedSubscriptionPlan: @entangle('selectedSubscriptionPlan'),
    confirmingSubscriptionPlanModal: @entangle('confirmingSubscriptionPlanModal'),
    subscriptionPlans: @entangle('subscriptionPlans'),
    }">
    <section class="text-gray-600 body-font overflow-hidden">
        <div class="container px-5 py-24 mx-auto">
            @if (session()->has('success'))
                <div class="mb-4 text-green-500 text-sm flex items-center justify-center">
                    <div class="flex items-center p-4 bg-green-50 rounded justify-center space-x-3 w-max">
                        <div class="mx-auto shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10 mr-4">
                            <svg class="h-6 w-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="text-sm text-green-600 pl-4">

                        {{ session('success') }}
                        </div>
                    </div>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="mb-4 text-red-500 text-sm flex items-center justify-center">
                    <div class="flex items-center p-4 bg-red-50 rounded justify-center space-x-3 w-max">
                        <div class="mx-auto shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10 mr-4">
                            <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="text-sm text-red-600 pl-4">

                            {{ session('error') }}
                        </div>
                    </div>
                </div>
            @endif
            <div class="flex flex-col text-center w-full mb-20">
                <h1 class="sm:text-4xl text-3xl font-medium title-font mb-2 text-gray-900">Unsere Abonnement-Modelle</h1>
                <p class="lg:w-2/3 mx-auto leading-relaxed text-base text-gray-500">
                    Wähle das passende Modell für deine Bedürfnisse – von grundlegenden Funktionen bis hin zu exklusiven Vorteilen.
                </p>
            </div>

            <div class="flex flex-wrap justify-evenly -m-4">
                @foreach($subscriptionPlans as $index => $subscriptionPlan)
                    <div class="p-4 xl:w-1/4 md:w-1/2 w-full">
                        <div class="h-full p-6 rounded-lg border-2 {{ $index === 1 ? 'border-secondary-800' : 'border-gray-300' }} flex flex-col relative overflow-hidden">
                            @if($index === 1)
                                <span class="bg-secondary-800 text-white px-3 py-1 tracking-widest text-xs absolute right-0 top-0 rounded-bl">BELIEBT</span>
                            @endif
                            <h2 class="text-sm tracking-widest title-font mb-1 font-medium">{{ strtoupper($subscriptionPlan->name) }}</h2>
                            <h1 class="text-5xl text-gray-900 pb-4 mb-4 border-b border-gray-200 leading-none">{{ $subscriptionPlan->price_monthly }}/Monat</h1>
                            @foreach($subscriptionPlan->permissions as $permission)
                                <p class="flex items-center text-gray-600 mb-2">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ $permission }}
                                </p>
                            @endforeach
                            <button wire:click="$set('selectedSubscriptionPlan',{{ $subscriptionPlan }})"  wire:loading.attr="disabled" class="flex items-center mt-auto text-white {{ $index === 1 ? 'bg-secondary-800 hover:bg-secondary-900' : 'bg-gray-400 hover:bg-gray-500' }} border-0 py-2 px-4 w-full focus:outline-none rounded">
                                Modell anzeigen
                            </button>
                            <p class="text-xs text-gray-500 mt-3">Modell „{{ $subscriptionPlan->name }}“ basiert auf echten Nutzererfahrungen.</p>
                        </div>
                    </div>
                    @endforeach
                    <x-confirmation-modal wire:model="selectedSubscriptionPlan">
                        <x-slot name="title">
                            {{ __('Abonnement Bestätigen') }}
                        </x-slot>
    
                        <x-slot name="content">
                            <div class="mt-4 text-sm text-gray-600">
                                Möchtest du das Abonnement-Modell abonnieren?
                            </div>
                            <div class="mt-4 text-sm text-gray-600">
                                {{ __('Bitte beachte, dass du jederzeit kündigen kannst.') }}
                            </div>
                            <div class="mt-4 text-sm text-gray-600">
                                {{ __('Das Abonnement wird automatisch verlängert, es sei denn, du kündigst es.') }}
                            </div>
                            <div class="mt-4 text-sm text-gray-600">
                                {{ __('Möchtest du fortfahren?') }}
                            </div>
                        </x-slot>
    
                        <x-slot name="footer">
                            <x-secondary-button wire:click="$set('selectedSubscriptionPlan',null)" wire:loading.attr="disabled">
                                Abbrechen
                            </x-secondary-button>
    
                            <x-danger-button class="ms-3" wire:click="subscribe" wire:loading.attr="disabled">
                                {{ __('Abonnieren') }}
                            </x-danger-button>
                        </x-slot>
                    </x-confirmation-modal>
            </div>
        </div>
    </section>
</div>
