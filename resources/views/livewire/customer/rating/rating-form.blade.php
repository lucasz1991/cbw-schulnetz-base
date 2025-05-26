<div class=""
    x-data="{ 
     step: @entangle('step'),
     showFormModal: @entangle('showFormModal'),
     insuranceSubTypeId: @entangle('insuranceSubTypeId'),
     modalIsOpen: false,
    }"
    x-init="() => {
        $watch('step', () => { 
            $nextTick(() => {
                const el = $refs.scrollTarget;
                const container = $refs.scrollcontainer;
                if (el) {
                    const top = el.getBoundingClientRect().top + window.scrollY - 100; // 100px Offset
                    container.scrollTo({ top, behavior: 'smooth' });
                }
            });
        });
    }"
>
    <x-buttons.button-basic  x-on:click="modalIsOpen = true" :mode="'primary'">
        Jetzt bewerten
    </x-buttons.button-basic>
 
    <template x-teleport="body">
        <div x-cloak x-show="modalIsOpen"  x-ref="scrollcontainer" x-transition.opacity.duration.200ms x-trap.inert.noscroll="modalIsOpen" x-on:keydown.esc.window="modalIsOpen = false" x-on:click.self="modalIsOpen = false" class="fixed inset-0 z-40  bg-black/20 px-4 pb-8 pt-14 backdrop-blur-md sm:items-center lg:p-8 overflow-y-auto content-center" role="dialog" aria-modal="true" aria-labelledby="defaultModalTitle">
            <!-- Modal Dialog -->
            <div x-show="modalIsOpen"  x-ref="scrollTarget"  x-transition:enter="transition ease-out duration-200 delay-100 motion-reduce:transition-opacity" x-transition:enter-start="opacity-0 scale-50" x-transition:enter-end="opacity-100 scale-100" class="flex flex-col gap-4 relative text-center mx-auto rounded-lg  shadow-xl transform transition-all container max-w-4xl border border-outline bg-gray-50  w-full  px-6 py-4" role="dialog" aria-modal="true" aria-labelledby="defaultModalTitle">
                <div class="ratingform " >
                    {{-- Step 0: Versicherungs typ --}}
                    <div x-show="step == 0"  x-cloak >
                        <h2 class="text-lg font-bold mb-4">Jetzt Fall melden</h2>
                        <x-alert class="w-max mx-auto" role="alert">
                                <span> Bitte wähle die passende Versicherungskategorie aus, um mit der Fallmeldung zu starten.<br>Beispiel: Die Krankenversicherung findest du unter "Personenversicherungen".</span>
                        </x-alert>
                        <h2 class="text-lg mb-12">Versicherungskategorie auswählen</h2>
                        <style>
                            .ratingform .swiper {
                                padding: 0px 0px 60px 0px;
                            }
                            .swiper-pagination.swiper-pagination-clickable.swiper-pagination-bullets {
                                
                            }
                        </style>
                        <div x-data="{
                                    insuranceTypeId: null
                                }">
                            
                            <div x-data="{
                                    swiper: null,
                                    initSwiper() {
                                        this.swiper = new Swiper(this.$refs.swiper, {
                                            effect: 'coverflow',
                                            autoplay: {
                                                delay: 1800,
                                            },
                                            disableOnInteraction: true,
                                            speed: 1000,
                                            loop: true,
                                            centeredSlides: true,
                                            slidesPerView: '2',
                                            disableOnMobile: true,
                                            breakpoints: {
                                                640: {
                                                    slidesPerView: 2,
                                                    spaceBetween: 20,
                                                },
                                                768: {
                                                    slidesPerView: 3,
                                                    spaceBetween: 40,
                                                },
                                            },
                                            coverflowEffect: {
                                                rotate: 50,
                                                stretch: 0,
                                                depth: 100,
                                                modifier: 1,
                                                slideShadows: false,
                                            },
                                            pagination: {
                                                el: '.swiper-pagination',
                                                clickable: true,
                                            },
                                        });
                                        this.swiper.slideNext();
                                    },
                                    stopSwiper() {
                                        this.swiper.autoplay.stop();
                                    }
                                }"
                                x-init="initSwiper() "
                                x-on:click="stopSwiper()"
                                class=" relative w-full"
                                wire:ignore
                            >
                                {{-- Navigation links/rechts außerhalb --}}
                                
                                <div class="swiper w-full" x-ref="swiper" >
                                    <div class="swiper-wrapper pointer-events-none">
                                        @foreach ($types as $type)
                                            <div class="swiper-slide h-full " wire:key="type-{{ $type->id }}"
                                                    wire:click="$set('insuranceTypeId', {{ $type->id }})"
                                                    @click="insuranceTypeId = {{ $type->id }}">
                                                <div 
                                                    :class="insuranceTypeId == {{ $type->id }} 
                                                        ? 'bg-blue-100 border-blue-500' 
                                                        : 'hover:bg-gray-100 bg-slate-100'"
                                                    class="border rounded p-2 text-center cursor-pointer w-[95%] h-24 flex justify-center items-center shadow-md transition duration-300 ease-in-out pointer-events-auto">
                                                    <h3 class="font-semibold max-w-full overflow-hidden text-xs md:text-base h-auto truncate text-ellipsis">{{ $type->name }}</h3>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <!-- If we need pagination -->
                                    <div class="swiper-pagination"></div>
                
                
                                </div>
                            </div>
                            @error('insuranceTypeId')
                                <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                            @enderror
                            <div x-show="insuranceTypeId != null"  x-cloak  >
                                <div class="flex justify-center mt-12">
                                    <x-buttons.furtherbutton wire:click="nextStep" />
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Step 1: Versicherungs SubType --}}
                    <div x-show="step == 1"  x-cloak  >
                        <style>
                            .choices__list.choices__list--dropdown .choices__list{
                                margin-top:25px;
                                padding: 10px 15px;
                                background-color:#fff;
                                border: 1px solid #cfcfcf !important;
                                box-shadow: inset 0 2px 2px 0 rgba(0,0,0,.14), inset 0 3px 1px -2px rgba(0,0,0,.2), inset 0 1px 5px 0 rgba(0,0,0,.12);
                                border-radius: .5em;
                                overflow:hidden ;
                                overflow-y: auto;
                            }
                            .choices[data-type*=select-one] .choices__input {
                                border:1px solid #ddd;
                                border-bottom:1px solid #ddd!important;
                                background-color:#fff;
                                box-shadow: inset 0 2px 2px 0 rgba(0,0,0,.14), inset 0 3px 1px -2px rgba(0,0,0,.2), inset 0 1px 5px 0 rgba(0,0,0,.12);

                            }
                        </style>
                        <div >
                            <h2 class="text-lg mb-6">Versicherungsart auswählen</h2>
                            <x-alert class="w-max mx-auto mb-6" role="alert">
                                <span>
                                    Bitte wähle die konkrete Versicherungsart aus, die zu deinem Fall passt. Die Auswahl hilft uns, die nachfolgenden Fragen optimal auf deinen Fall abzustimmen.
                                </span>
                            </x-alert>
                            <div class="max-w-md mx-auto" :class="{ 'selected': insuranceSubTypeId != null }">
                                <select wire:model.live="insuranceSubTypeId" class="w-full border rounded px-4 py-2" id="positionSelect" 
                                    x-init="
                                            let choices = new Choices($el, {
                                                removeItemButton: false, // ✅ EINZELAUSWAHL
                                                shouldSort: false,
                                                searchEnabled: true,
                                                placeholder: true,
                                                searchChoices: true,
                                                position: 'bottom',
                                                itemSelectText: '',
                                                searchPlaceholderValue: 'Suchen...',
                                            });
                                            $el.addEventListener('change', (e) => {
                                                insuranceSubTypeId = e.target.value;
                                            });
                                            $el.addEventListener('showDropdown', (e) => {
                                                const dropdownHeight = document.querySelector('.choices__list.choices__list--dropdown.is-active')?.offsetHeight || 1;
                                                console.log(dropdownHeight);
                                                document.getElementById('spacerInsuranceSubTypeId').style.height = `${dropdownHeight}px`;
                                            });
                                            $el.addEventListener('hideDropdown', (e) => {
                                                document.getElementById('spacerInsuranceSubTypeId').style.height = '0px';
                                            });
                                            $nextTick(() => {
                                                if (insuranceSubTypeId > 0) {
                                                    choices.setChoiceByValue(insuranceSubTypeId);
                                                }
                                            });
                                        "
                                        wire:ignore
                                    >
                                    <option value="">Bitte auswählen</option>
                                    @foreach ($insuranceSubTypes as $insuranceSubType)
                                        <option value="{{ $insuranceSubType->id }}" :class="{ 'selected': insuranceSubTypeId != null }" class="truncate text-ellipsis">{{ $insuranceSubType->name }}</option>
                                    @endforeach
                                </select>
                                <div id="spacerInsuranceSubTypeId" class="transform transition-all" ></div>

                            </div>
                            @error('insuranceSubTypeId')
                                <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-center space-x-4 mt-12">
                            <x-buttons.backbutton wire:click="previousStep" />
                            @if ($insuranceSubTypeId)
                                <x-buttons.furtherbutton wire:click="nextStep" />
                            @endif
                        </div>
                    </div>
                    {{-- Step 2: Konkrete Versicherung auswählen --}}
                    <div x-show="step == 2"  x-data="{ insuranceId: @entangle('insuranceId') }" x-cloak>
                        <h2 class="text-lg font-bold mb-6">Welche Versicherungsgesellschaft?</h2>
                        <x-alert class="w-max mx-auto mb-6" role="alert">
                                <span>
                                    Bitte wähle die konkrete Versicherungsgesellschaft aus, bei der du deinen Fall melden möchtest. Diese Information ist wichtig, um den Fall korrekt zuzuordnen und die richtigen Ansprechpartner zu finden.
                                </span>
                            </x-alert>
                        <div class="max-w-md mx-auto " :class="{ 'selected': insuranceId != null }">
                            <select wire:model.live="insuranceId" 
                                    class="border rounded px-4 py-2"
                                    x-init="
                                            let choices = new Choices($el, {
                                                removeItemButton: false, // ✅ EINZELAUSWAHL
                                                shouldSort: false,
                                                searchEnabled: true,
                                                placeholder: true,
                                                searchChoices: true,
                                                position: 'bottom',
                                                itemSelectText: '',
                                                searchPlaceholderValue: 'Suchen...',
                                                
                                            });
                                            $el.addEventListener('change', (e) => {
                                                insuranceId = e.target.value;
                                            });
                                            $el.addEventListener('showDropdown', (e) => {
                                                const dropdownHeight = document.querySelector('.choices__list.choices__list--dropdown.is-active')?.offsetHeight || 1;
                                                console.log(dropdownHeight);
                                                document.getElementById('spacerInsuranceId').style.height = `${dropdownHeight}px`;
                                            });
                                            $el.addEventListener('hideDropdown', (e) => {
                                                document.getElementById('spacerInsuranceId').style.height = '0px';
                                            });
                                            $nextTick(() => {
                                                if (insuranceId > 0) {
                                                    choices.setChoiceByValue(insuranceId);
                                                }
                                            });
                                        "
                                        wire:ignore>
                                <option value="">Bitte auswählen</option>
                                @foreach ($insurances ?? [] as $insurance)
                                    <option value="{{ $insurance->id }}" :class="{ 'selected': insuranceSubTypeId != null }" class="truncate text-ellipsis">{{ Str::limit($insurance->name, 25) }}</option>
                                @endforeach
                            </select>
                            <div id="spacerInsuranceId" class="transform transition-all" ></div>
                        </div>
                        @error('insuranceId')
                            <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                        @enderror
                        <div class="flex justify-center space-x-4 mt-12">
                            <x-buttons.backbutton wire:click="previousStep" />
                            @if ($insuranceId)
                            <x-buttons.furtherbutton wire:click="nextStep" />
        
                            @endif
                        </div>
                    </div>
                    {{-- Step 3: Fallstatus --}}
                    <div x-show="step == 3"  x-cloak  >
                        <h2 class="text-lg font-bold mb-6">Wie wurde der Schaden reguliert?</h2>
                        <x-alert class="w-max mx-auto mb-6" role="alert">
                                <span>
                                    Bitte wähle den aktuellen Status der Schadensregulierung aus. Diese Information hilft uns, den Fall besser zu verstehen und die nächsten Schritte zu planen.
                                </span>
                            </x-alert>
                        <div class="flex justify-center items-center">
                            <div x-data="{ regulationType: @entangle('regulationType') }" class=" grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4  gap-4 w-max justify-center">
                                <style>
                                    svg.bicolor .a{
                                        fill:#13ae13 !important;
                                        opacity: .4 !important;
                                        mix-blend-mode: normal !important;
                                    } 
                                    svg.bicolor .b{
                                        fill:#bb2929 !important;
                                        opacity: .4 !important;
                                        mix-blend-mode: normal !important;
                                    }
                                    svg.bicolor .c{
                                        fill:#6a6a6a !important;
                                        opacity: .6 !important;
                                        mix-blend-mode: normal !important;
                                    }
                                </style>
                                {{-- Karte: Vollzahlung --}}
                                <div 
                                    wire:click="$set('regulationType', 'vollzahlung')"
                                    @click="regulationType = 'vollzahlung'"
                                    :class="{ 'bg-blue-100 border-blue-500': regulationType === 'vollzahlung', 'hover:bg-gray-100 bg-slate-100': regulationType !== 'vollzahlung' }"
                                    class="w-full border rounded-lg flex items-center justify-center text-center cursor-pointer transition duration-300 ease-in-out py-3 pr-4 pl-4">
                                        <svg class="size-14 flex-none h-12 aspect-square mr-4 bicolor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 306.8413 304.0166"><path class="a" d="M132.0322,158.7979a4.29834,4.29834,0,0,0-8.1836,2.6328c.1787.5537.373,1.0957.581,1.6328a4.29772,4.29772,0,1,0,8.0137-3.1084C132.2959,159.5752,132.1582,159.1905,132.0322,158.7979Z"/><path class="a" d="M128.4375,167.3096a5.76515,5.76515,0,0,1-5.4063-3.7031c-.2187-.5655-.4224-1.1338-.6103-1.7149a5.79845,5.79845,0,0,1,11.039-3.5537q.1758.5478.3819,1.0742a5.79918,5.79918,0,0,1-5.4043,7.8975Zm-.4986-9.9932a2.79864,2.79864,0,0,0-2.6621,3.6553q.2535.7866.5518,1.5498a2.7975,2.7975,0,1,0,5.2163-2.0234c-.1582-.4082-.3057-.8203-.4409-1.2422A2.79205,2.79205,0,0,0,127.9389,157.3164Z"/><path class="a" d="M154.3169,176.2051a22.44707,22.44707,0,0,1-14.2095-5.0791,4.29711,4.29711,0,1,0-5.4531,6.6426,31.00562,31.00562,0,1,0,15.1582-54.6748,4.29676,4.29676,0,1,0,1.2378,8.5039,23.16565,23.16565,0,0,1,3.2666-.2344,22.4209,22.4209,0,0,1,0,44.8418Z"/><path class="a" d="M154.3169,186.2998a32.55425,32.55425,0,0,1-20.6148-7.372,5.79665,5.79665,0,0,1,3.6773-10.2784,5.82125,5.82125,0,0,1,3.6792,1.3164,20.91994,20.91994,0,1,0,13.2583-37.1025,21.66138,21.66138,0,0,0-3.0542.2197,5.79707,5.79707,0,0,1-1.6675-11.4736,32.51728,32.51728,0,1,1,4.7217,64.6904Zm-16.9375-14.6504a2.79664,2.79664,0,0,0-1.774,4.959,29.51446,29.51446,0,1,0,18.7115-52.3398,29.86367,29.86367,0,0,0-4.2881.3095,2.79683,2.79683,0,1,0,.8066,5.5352,24.66766,24.66766,0,0,1,3.4815-.25,23.9209,23.9209,0,1,1-15.1612,42.4219A2.77114,2.77114,0,0,0,137.3794,171.6494Z"/><path class="a" d="M94.5542,148.1436a4.23082,4.23082,0,0,0,.7553.0664,4.30069,4.30069,0,0,0,4.2256-3.5479c.2608-1.4726.583-2.9355.9541-4.3515a4.29776,4.29776,0,0,0-8.313-2.1856c-.4297,1.6416-.8017,3.335-1.104,5.0362A4.2971,4.2971,0,0,0,94.5542,148.1436Z"/><path class="a" d="M95.3095,149.71a5.798,5.798,0,0,1-5.7143-6.8106c.3076-1.7304.6875-3.4648,1.1299-5.1543a5.79768,5.79768,0,0,1,11.2148,2.9463c-.3652,1.3936-.6772,2.8174-.9277,4.2325A5.78975,5.78975,0,0,1,95.3095,149.71ZM96.33,136.4209a2.79916,2.79916,0,0,0-2.7031,2.086c-.4219,1.6103-.7847,3.2656-1.0776,4.917a2.79725,2.79725,0,1,0,5.5088.9755c.2646-1.4931.5942-2.997.9799-4.4687a2.80235,2.80235,0,0,0-2.708-3.5098Z"/><path class="a" d="M90.7563,163.4805a63.51246,63.51246,0,1,0,46.105-70.4932,4.299,4.299,0,0,0,2.2695,8.293A54.97685,54.97685,0,1,1,99.2612,162.252a4.29658,4.29658,0,1,0-8.5049,1.2285Z"/><path class="a" d="M153.664,219.374a65.36916,65.36916,0,0,1-64.3921-55.6787,5.79645,5.79645,0,1,1,11.4737-1.6582,53.43014,53.43014,0,1,0,38.7817-59.3105,5.79583,5.79583,0,0,1-7.123-4.0645,5.79706,5.79706,0,0,1,4.0615-7.122A65.073,65.073,0,1,1,153.664,219.374ZM95.0175,160.0664a2.88649,2.88649,0,0,0-.4072.0293,2.80139,2.80139,0,0,0-2.3696,3.169,62.01381,62.01381,0,1,0,45.0161-68.8301,2.79621,2.79621,0,0,0-1.959,3.4355,2.838,2.838,0,0,0,3.439,1.9629,56.47685,56.47685,0,1,1-40.96,62.6338A2.80778,2.80778,0,0,0,95.0175,160.0664Z"/><path class="a" d="M114.8828,109.2862a4.29971,4.29971,0,0,0,6.0122.8789c1.249-.9287,2.5522-1.8145,3.8764-2.6348a4.29742,4.29742,0,0,0-4.5244-7.3076c-1.5332.9463-3.039,1.9726-4.4814,3.0478A4.29812,4.29812,0,0,0,114.8828,109.2862Z"/><path class="a" d="M118.3339,112.5147a5.82906,5.82906,0,0,1-4.6538-2.3321,5.79785,5.79785,0,0,1,1.191-8.1162c1.4917-1.1123,3.0361-2.1621,4.5883-3.1201a5.79735,5.79735,0,1,1,6.1016,9.8594c-1.2876.7978-2.5566,1.6601-3.771,2.5635A5.75587,5.75587,0,0,1,118.3339,112.5147Zm4.1709-11.4346a2.7921,2.7921,0,0,0-1.4692.4189c-1.4785.9122-2.9497,1.9131-4.3735,2.9737a2.79818,2.79818,0,0,0-.5757,3.9179A2.857,2.857,0,0,0,120,108.961c1.2822-.9532,2.6215-1.8633,3.9814-2.7061a2.79714,2.79714,0,0,0-1.4766-5.1748Z"/><path class="a" d="M59.4741,151.9951a4.30042,4.30042,0,0,0,4.2851-4.0156c.0938-1.4199.2193-2.832.377-4.2051a4.29821,4.29821,0,0,0-8.541-.9746c-.1719,1.5069-.3106,3.0586-.4136,4.6104a4.297,4.297,0,0,0,4.002,4.5732C59.2832,151.9893,59.3779,151.9951,59.4741,151.9951Z"/><path class="a" d="M59.4741,153.4951c-.1211,0-.2403-.0058-.3653-.0136a5.796,5.796,0,0,1-5.4238-6.1709c.105-1.5869.2466-3.1612.4199-4.6807a5.79826,5.79826,0,0,1,11.522,1.3125c-.1528,1.3281-.2773,2.7188-.3711,4.1357A5.81354,5.81354,0,0,1,59.4741,153.4951Zm.3789-13.0068a2.79246,2.79246,0,0,0-2.7676,2.4824c-.168,1.4717-.3047,2.999-.4072,4.5391a2.79707,2.79707,0,0,0,2.605,2.9766c.0669.0039.1284.0087.1909.0087a2.80608,2.80608,0,0,0,2.7886-2.6152c.0966-1.4639.2255-2.9033.3833-4.2764a2.80277,2.80277,0,0,0-2.4634-3.0957A2.95594,2.95594,0,0,0,59.853,140.4883Z"/><path class="a" d="M153.5097,252.5645A98.54743,98.54743,0,1,0,118.5639,61.8447a4.29837,4.29837,0,0,0,3.0484,8.0381,89.96681,89.96681,0,1,1-57.5508,93.709,4.29869,4.29869,0,0,0-8.5498.9033A98.2904,98.2904,0,0,0,153.5097,252.5645Z"/><path class="a" d="M153.5097,254.0645A99.7885,99.7885,0,0,1,54.02,164.6533a5.79871,5.79871,0,0,1,11.5332-1.2187A88.37955,88.37955,0,1,0,122.144,71.2852a5.79831,5.79831,0,0,1-4.1128-10.8428,100.05933,100.05933,0,1,1,35.4785,193.6221ZM59.7988,161.2442a2.87973,2.87973,0,0,0-.3032.0166,2.80388,2.80388,0,0,0-2.4922,3.0761A96.97421,96.97421,0,1,0,119.0957,63.2471a2.7999,2.7999,0,0,0-1.6241,3.6074,2.86035,2.86035,0,0,0,3.6094,1.626A91.46653,91.46653,0,1,1,62.5698,163.75,2.78669,2.78669,0,0,0,59.7988,161.2442Z"/><path class="a" d="M103.413,78.4737a4.3066,4.3066,0,0,0,2.2881-.6622q1.27665-.81,2.5899-1.5732a4.29727,4.29727,0,1,0-4.3389-7.4189q-1.43265.8379-2.8364,1.7207a4.29842,4.29842,0,0,0,2.2973,7.9336Z"/><path class="a" d="M103.413,79.9737a5.7981,5.7981,0,0,1-3.0966-10.7032q1.41945-.8979,2.8784-1.7461a5.79728,5.79728,0,1,1,5.853,10.0088q-1.28835.7515-2.543,1.5449A5.82017,5.82017,0,0,1,103.413,79.9737Zm2.7041-10.2432a2.79016,2.79016,0,0,0-1.4077.3838q-1.40985.8247-2.792,1.6933a2.79518,2.79518,0,0,0-.8759,3.8584,2.8574,2.8574,0,0,0,3.8647.8741c.8579-.545,1.7388-1.0782,2.6289-1.5977a2.79619,2.79619,0,0,0,1.0025-3.8232A2.81476,2.81476,0,0,0,106.1171,69.7305Z"/><path class="c" d="M89.9829,80.0264a4.29709,4.29709,0,0,0-1.8238-2.9844l-26.02-17.9228a4.2953,4.2953,0,0,0-6.7309,3.3711l-.833,21.0966L41.8144,75.25a4.29792,4.29792,0,1,0-4.6997,7.1973l11.7857,7.6992L30.0727,99.2666a4.29914,4.29914,0,0,0-.6479,7.3487l28.3354,20.5361a4.312,4.312,0,0,0,4.0957.5185l27.1734-10.707a4.52812,4.52812,0,0,0,.5136-.2646l62.6363,40.917a4.29774,4.29774,0,1,0,4.7006-7.1963L93.7793,109.1983ZM81.706,83.0362l2.603,19.9736L62.9624,89.0655,63.687,70.624ZM60.8955,118.8067,40.2871,103.8701l16.4736-7.9824a4.28581,4.28581,0,0,0,1.499.3731l22.522,14.7129Z"/><path class="c" d="M154.5268,159.8135a5.76756,5.76756,0,0,1-3.1699-.9434L89.4311,118.417,62.4057,129.0655a5.83612,5.83612,0,0,1-5.5268-.7002L28.5444,107.8301a5.7999,5.7999,0,0,1,.873-9.9131l16.4429-7.9648-9.5659-6.2491a5.79793,5.79793,0,0,1,6.3389-9.7099l10.5473,6.8906.7285-18.4531a5.80014,5.80014,0,0,1,3.1949-4.957,5.88739,5.88739,0,0,1,5.8847.4091L89.0102,75.8067a5.8024,5.8024,0,0,1,2.46,4.0263l3.7075,28.4873,62.5225,40.8428a5.79677,5.79677,0,0,1-3.1734,10.6504ZM89.5429,115.1983a1.50082,1.50082,0,0,1,.8203.2441l62.6363,40.917a2.79773,2.79773,0,0,0,3.06-4.6846L92.9589,110.4541a1.49987,1.49987,0,0,1-.6669-1.0625L88.4956,80.2197a2.7991,2.7991,0,0,0-1.1875-1.9423L61.288,60.3545a2.85014,2.85014,0,0,0-2.8422-.1973,2.79789,2.79789,0,0,0-1.5386,2.3907l-.833,21.0986a1.50026,1.50026,0,0,1-2.3194,1.1963L40.9941,76.5059a2.79772,2.79772,0,1,0-3.0586,4.6855l11.7852,7.6992a1.49963,1.49963,0,0,1-.1665,2.6055l-18.8277,9.1201a2.79956,2.79956,0,0,0-.4209,4.7852l28.335,20.5351a2.81657,2.81657,0,0,0,2.6723.335l27.1666-10.7041a1.9765,1.9765,0,0,0,.2314-.1221l.106-.0595A1.50189,1.50189,0,0,1,89.5429,115.1983Zm-28.6474,5.1084a1.49838,1.49838,0,0,1-.8804-.2852L39.4067,105.085a1.50021,1.50021,0,0,1,.2261-2.5645l16.4736-7.9824a1.4944,1.4944,0,0,1,1.2783-.0146,2.80822,2.80822,0,0,0,.9766.2412,1.48833,1.48833,0,0,1,.7187.2402l22.522,14.7129a1.5,1.5,0,0,1-.2705,2.6514l-19.8862,7.833A1.494,1.494,0,0,1,60.8955,120.3067ZM43.1972,104.127l17.9131,12.9824,16.4209-6.4678L57.7431,97.7149a5.80548,5.80548,0,0,1-.9052-.1973Zm41.1118.3828a1.49557,1.49557,0,0,1-.8203-.2442L62.1421,90.3213a1.50086,1.50086,0,0,1-.6788-1.3144l.7246-18.4414a1.50075,1.50075,0,0,1,2.3501-1.1768L82.5571,81.8008a1.49969,1.49969,0,0,1,.6362,1.042l2.603,19.9736a1.49919,1.49919,0,0,1-1.4873,1.6934ZM64.4946,88.2744,82.4004,99.9717,80.3051,83.8926,65.0791,73.4043Z"/><path class="a" d="M31.3369,126.3369c-.3506,1.5166-.6773,3.0401-.9619,4.5254a4.29323,4.29323,0,0,0,3.3999,5.0362,4.06314,4.06314,0,0,0,.8213.08,4.30037,4.30037,0,0,0,4.2158-3.4804c.2671-1.3838.5698-2.8018.8989-4.2159a4.29849,4.29849,0,0,0-8.374-1.9453Z"/><path class="a" d="M34.5962,137.4785a5.61746,5.61746,0,0,1-1.1236-.1103,5.79371,5.79371,0,0,1-4.5698-6.793c.2734-1.4277.6011-2.9687.9726-4.5762a5.774,5.774,0,0,1,5.6431-4.4892,5.83847,5.83847,0,0,1,1.3184.1523,5.7976,5.7976,0,0,1,4.3349,6.96c-.3271,1.4053-.6255,2.8047-.8872,4.1601A5.80751,5.80751,0,0,1,34.5962,137.4785Zm.9223-12.9687a2.78593,2.78593,0,0,0-2.7207,2.168c-.3628,1.5673-.6821,3.0712-.9497,4.4668a2.79334,2.79334,0,0,0,2.2124,3.2812,2.62167,2.62167,0,0,0,.5357.0527,2.80129,2.80129,0,0,0,2.7431-2.2666c.2686-1.3906.5752-2.8271.9107-4.2695a2.79673,2.79673,0,0,0-2.0928-3.3584A2.84868,2.84868,0,0,0,35.5185,124.5098Z"/><path class="a" d="M153.5097,29.4483a124.34941,124.34941,0,0,0-52.8364,11.6406,4.297,4.297,0,0,0,3.625,7.792,116.85968,116.85968,0,1,1-67.6299,106.001c0-1.8125.043-3.6661.1275-5.5108a4.29708,4.29708,0,0,0-8.585-.3965c-.0903,1.9746-.1392,3.9619-.1392,5.9073A125.4358,125.4358,0,1,0,153.5097,29.4483Z"/><path class="a" d="M153.5097,281.8184A127.08113,127.08113,0,0,1,26.5717,154.8819c0-1.9239.0474-3.9346.1407-5.9756a5.79716,5.79716,0,1,1,11.582.5342c-.0835,1.8232-.126,3.6542-.126,5.4414A115.25628,115.25628,0,1,0,104.9311,50.2412a5.7472,5.7472,0,0,1-2.4414.541,5.81943,5.81943,0,0,1-5.2602-3.3544,5.79828,5.79828,0,0,1,2.811-7.6993,126.94678,126.94678,0,1,1,53.4692,242.0899ZM32.5088,146.377a2.79616,2.79616,0,0,0-2.7994,2.667c-.0913,1.9951-.1377,3.959-.1377,5.8379A123.85585,123.85585,0,1,0,101.3061,42.4492a2.7961,2.7961,0,0,0-1.3564,3.7139,2.85534,2.85534,0,0,0,3.7158,1.3574A118.35993,118.35993,0,1,1,35.1684,154.8819c0-1.833.0435-3.71.1289-5.5791a2.79464,2.79464,0,0,0-2.6626-2.9239Z"/><path class="a" d="M84.6045,50.0537c-1.128.7422-2.3077,1.5469-3.5069,2.3936a4.298,4.298,0,1,0,4.9629,7.0185q1.612-1.13955,3.2671-2.2314a4.29739,4.29739,0,0,0-4.7231-7.1807Z"/><path class="a" d="M83.5815,61.7559a5.79905,5.79905,0,0,1-3.3501-10.5332c1.2031-.8496,2.397-1.6641,3.5483-2.4219a5.79752,5.79752,0,1,1,6.3716,9.6875q-1.6326,1.0737-3.2251,2.2022A5.75685,5.75685,0,0,1,83.5815,61.7559Zm3.3799-10.9082a2.77929,2.77929,0,0,0-1.5332.4599c-1.1236.7393-2.2901,1.5352-3.4653,2.3653a2.80328,2.80328,0,0,0-.667,3.9013,2.86866,2.86866,0,0,0,3.8984.667c1.1255-.7959,2.2388-1.5556,3.3091-2.2597a2.79716,2.79716,0,0,0-1.542-5.1338Z"/><path class="a" d="M247.3252,48.9307l-5.0298-4.1162a139.219,139.219,0,0,0-80.7227-31.4649l-6.4892-.3711L155.8256,0l6.4893.3711a152.135,152.135,0,0,1,88.2139,34.3828l5.0302,4.1172Z"/><path class="a" d="M40.3418,66.8155l-9.9903-8.3184,4.1592-4.9951c17.2476-20.7149,41.3731-36.1865,71.707-45.9864l6.1851-1.998,3.9966,12.3711-6.1856,1.998C82.2583,28.918,60.1494,43.0264,44.5009,61.8203Z"/><path class="a" d="M13.5605,217.0225l-2.436-6.0264a154.15488,154.15488,0,0,1-5.7993-97.666l1.6997-6.2734,12.5478,3.3994-1.6997,6.2744a141.15216,141.15216,0,0,0,5.3042,89.3945l2.436,6.0264Z"/><path class="a" d="M116.3188,302.583l-6.2363-1.833a152.22256,152.22256,0,0,1-54.9336-29.3926l-4.9893-4.166,8.3321-9.9785,4.9892,4.166a139.28792,139.28792,0,0,0,50.2686,26.8985l6.2358,1.834Z"/><path class="a" d="M184.164,304.0166l-3.1294-12.6181,6.3086-1.5645a141.06533,141.06533,0,0,0,88.3672-67.2275l3.2022-5.6563,11.3129,6.4043-3.2021,5.6563a154.11618,154.11618,0,0,1-96.5503,73.4404Z"/><path class="a" d="M299.5879,200.7373l-12.5308-3.4609,1.73-6.2656a141.42315,141.42315,0,0,0-.502-76.7217l-1.813-6.2412,12.4844-3.626,1.813,6.2422a154.42792,154.42792,0,0,1,.5488,83.8076Z"/></svg>
                                        <span class="grow flex items-center content-center text-lg font-semibold">Vollzahlung</span>
                                </div>
                                {{-- Karte: Teilzahlung --}}
                                <div 
                                    wire:click="$set('regulationType', 'teilzahlung')"
                                    @click="regulationType = 'teilzahlung'"
                                    :class="{ 'bg-blue-100 border-blue-500': regulationType === 'teilzahlung', 'hover:bg-gray-100 bg-slate-100': regulationType !== 'teilzahlung' }"
                                    class="w-full border rounded-lg flex items-center justify-center text-center cursor-pointer transition duration-300 ease-in-out py-3 pr-4 pl-4">
                                        <svg class="size-14 flex-none h-12 aspect-square mr-4 bicolor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 286.9961 280.3496"><path class="a" d="M140.127,280.3496C62.8604,280.3496,0,217.4883,0,140.2226A140.13724,140.13724,0,0,1,130.5879.414L136.7305,0l.8271,12.2861-6.1435.4131C64.6289,17.1933,12.3135,73.207,12.3135,140.2226A127.8314,127.8314,0,0,0,211.9551,245.9599l5.0898-3.4638,6.9297,10.1787-5.0908,3.4639A139.39014,139.39014,0,0,1,140.127,280.3496Z"/><path class="c" d="M248.793,236.5801l-9.3076-8.0625,4.0312-4.6543a127.82951,127.82951,0,0,0,2.2803-164.5782l-3.9024-4.7627,9.5254-7.8037,3.9024,4.7637a140.14025,140.14025,0,0,1-2.4981,180.4434Z"/><path class="b" d="M227.6631,46.8838l-4.6865-3.9932a126.739,126.739,0,0,0-72.4258-30.0644l-6.1367-.4952L145.4043.0576l6.1367.4951a138.98679,138.98679,0,0,1,79.4219,32.9649l4.6855,3.9941Z"/><polygon class="a" points="218 254.505 124.665 141.613 123.154 137.693 123.154 4.145 135.154 4.145 135.154 135.48 227.236 246.664 218 254.505"/><polygon class="b" points="155.62 117.056 145.154 112.665 145.154 2.145 157.154 2.145 157.154 97.982 226.858 29.626 235.568 38.408 155.62 117.056"/><polygon class="c" points="243.254 237.386 158.275 141.783 158.566 133.297 248.907 44.729 257.528 53.522 171.375 137.985 252.457 229.205 243.254 237.386"/><rect class="c" x="157.42501" y="106.82193" width="125.77749" height="12.31314" transform="translate(-17.74917 181.01036) rotate(-43.09757)"/><rect class="c" x="175.5426" y="130.69478" width="115.9441" height="12.31298" transform="translate(-31.76776 190.54118) rotate(-41.81385)"/><rect class="c" x="192.39346" y="154.98928" width="102.18279" height="12.31339" transform="translate(-46.43318 195.79327) rotate(-40.31279)"/><rect class="c" x="213.1431" y="180.40701" width="76.4116" height="12.31288" transform="translate(-60.70221 211.18657) rotate(-41.09632)"/></svg>
                                        <span class="grow flex items-center content-center text-lg font-semibold">Teilzahlung</span>
                                </div>
                                {{-- Karte: Ablehnung --}}
                                <div 
                                    wire:click="$set('regulationType', 'ablehnung')"
                                    @click="regulationType = 'ablehnung'"
                                    :class="{ 'bg-blue-100 border-blue-500': regulationType === 'ablehnung', 'hover:bg-gray-100 bg-slate-100': regulationType !== 'ablehnung' }"
                                    class="w-full border rounded-lg flex items-center justify-center text-center cursor-pointer transition duration-300 ease-in-out py-3 pr-4 pl-4">
                                        <svg class="size-14 flex-none h-12 aspect-square mr-4 bicolor" viewBox="0 0 347.29782 340.4675"><path class="b" d="M102.98143,287.1892a6.48988,6.48988,0,0,1-2.336-.4366L4.165,249.5769a6.50018,6.50018,0,0,1-3.7285-8.4024L91.76073,4.1638A6.50362,6.50362,0,0,1,100.163.4353l197.7559,76.1953a6.50058,6.50058,0,0,1,3.7285,8.4023L284.456,129.6452a6.50006,6.50006,0,0,1-12.1309-4.6738l14.8545-38.5469L101.55463,14.903,14.90423,239.7829l90.4151,34.8389a6.50128,6.50128,0,0,1-2.3379,12.5674Z"/><path class="b" d="M147.49313,304.3386a6.48921,6.48921,0,0,1-2.3359-.4365l-12.6504-4.875a6.50006,6.50006,0,0,1,4.6738-12.1309l6.5859,2.5381,21.8184-56.6182a6.49872,6.49872,0,0,1,8.4268-3.7187l50.4257,19.666,33.2725-86.3565a6.50008,6.50008,0,0,1,12.1309,4.6739l-35.6192,92.4472a6.5008,6.5008,0,0,1-8.4267,3.7188l-50.4258-19.667-21.8096,56.5937A6.5038,6.5038,0,0,1,147.49313,304.3386Z"/><path class="b" d="M152.17473,303.8435a6.50057,6.50057,0,0,1-2.9423-12.2989l72.7382-36.8525a6.49954,6.49954,0,1,1,5.875,11.5957l-72.7382,36.8525A6.4651,6.4651,0,0,1,152.17473,303.8435Z"/><path class="b" d="M201.60253,203.6022a6.48974,6.48974,0,0,1-2.336-.4365l-91.8847-35.4052a6.50006,6.50006,0,0,1,4.6738-12.1309l91.8848,35.4053a6.50121,6.50121,0,0,1-2.3379,12.5673Z"/><path class="b" d="M210.06933,181.6286a6.48974,6.48974,0,0,1-2.336-.4365l-91.8847-35.4053a6.5,6.5,0,1,1,4.6738-12.1308l91.8848,35.4052a6.50127,6.50127,0,0,1-2.3379,12.5674Z"/><path class="b" d="M193.09273,225.5573a6.48921,6.48921,0,0,1-2.3359-.4365L98.915,189.7341a6.50006,6.50006,0,0,1,4.6738-12.1309l91.8418,35.3867a6.50127,6.50127,0,0,1-2.3379,12.5674Z"/><path class="b" d="M153.78613,236.4099a6.48988,6.48988,0,0,1-2.336-.4366l-61.2568-23.6035a6.5,6.5,0,1,1,4.6738-12.1308l61.2569,23.6035a6.50128,6.50128,0,0,1-2.3379,12.5674Z"/><path class="b" d="M101.58293,139.3386a6.49245,6.49245,0,0,1-2.333-.4356l-.8476-.3261a6.49993,6.49993,0,0,1,4.6679-12.1329l.8477.3262a6.50124,6.50124,0,0,1-2.335,12.5684Z"/><path class="b" d="M93.07513,161.4235a6.484,6.484,0,0,1-2.3349-.4365l-.8496-.3271a6.4997,6.4997,0,0,1,4.6718-12.1309l.8496.3272a6.50108,6.50108,0,0,1-2.3369,12.5673Z"/><path class="b" d="M84.56443,183.5114a6.473,6.473,0,0,1-2.3408-.4384l-.8496-.3282a6.5,6.5,0,1,1,4.6836-12.1269l.8496.3281a6.50123,6.50123,0,0,1-2.3428,12.5654Z"/><path class="b" d="M75.72753,206.445a6.48506,6.48506,0,0,1-2.3242-.4316l-.8516-.3262a6.50039,6.50039,0,0,1,4.6504-12.1406l.8515.3262a6.50133,6.50133,0,0,1-2.3261,12.5722Z"/><path class="b" d="M118.41993,293.1364a6.46788,6.46788,0,0,1-2.3321-.4355l-.8506-.3272a6.49956,6.49956,0,1,1,4.6661-12.1328l.8505.3272a6.501,6.501,0,0,1-2.3339,12.5683Z"/><path class="b" d="M271.56543,151.3942a6.505,6.505,0,0,1-6.0645-8.8379l.3272-.8496a6.49967,6.49967,0,1,1,12.1308,4.6719l-.3271.8496A6.5014,6.5014,0,0,1,271.56543,151.3942Z"/><path class="b" d="M228.497,141.2946a6.49855,6.49855,0,0,1-2.3369-.4345L113.93353,97.6169a6.4996,6.4996,0,0,1-3.7285-8.4023l7.3291-19.0206a6.50333,6.50333,0,0,1,8.4024-3.7285l112.2246,43.2412a6.5,6.5,0,0,1,3.7285,8.4014l-7.3272,19.0225a6.49833,6.49833,0,0,1-6.0654,4.164ZM124.67283,87.823l100.0957,38.5693,2.6543-6.8916L127.32813,80.9333Z"/><path class="c" d="M241.31833,340.4675a6.5,6.5,0,0,1-6.2617-4.7559c-1.6895-6.0644-5.3272-20.9258-2.6299-27.9326l80.6289-209.2627a17.71584,17.71584,0,0,1,32.6904-.8037,17.51612,17.51612,0,0,1,.3662,13.542l-80.6328,209.2588c-2.6972,7.0058-15.3672,15.5849-20.6884,18.9482A6.49333,6.49333,0,0,1,241.31833,340.4675Zm88.25-240.2529a4.70889,4.70889,0,0,0-4.3818,2.9765l-80.628,209.2608c-.4189,1.4082-.0029,5.6962.9287,10.7041,4.0655-3.0957,7.2237-5.9844,7.8829-7.3692l80.6113-209.207a4.60392,4.60392,0,0,0-.1035-3.5606,4.73014,4.73014,0,0,0-4.3096-2.8046Z"/><path class="c" d="M321.76653,182.697a6.49175,6.49175,0,0,1-2.3359-.4365l-32.334-12.46a6.50006,6.50006,0,0,1,4.6738-12.1309l32.334,12.46a6.50128,6.50128,0,0,1-2.3379,12.5674Z"/></svg>
                                        <span class="grow flex items-center content-center text-lg font-semibold">Ablehnung</span>
                                </div>   
                                {{-- Karte: Austehend --}} 
                                <div 
                                    wire:click="$set('regulationType', 'austehend')"
                                    @click="regulationType = 'austehend'"
                                    :class="{ 'bg-blue-100 border-blue-500': regulationType === 'austehend', 'hover:bg-gray-100 bg-slate-100': regulationType !== 'austehend' }"
                                    class="w-full border rounded-lg flex items-center justify-center text-center cursor-pointer transition duration-300 ease-in-out py-3 pr-4 pl-4">
                                        <svg class="size-14 flex-none h-12 aspect-square mr-4 bicolor" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 321.91499 338.21384"><path class="c" d="M109.73724,295.25484a67.32615,67.32615,0,1,1,67.3242-67.3262A67.40353,67.40353,0,0,1,109.73724,295.25484Zm0-124.8769a57.55075,57.55075,0,1,0,57.5498,57.5507A57.61523,57.61523,0,0,0,109.73724,170.37794Z"/><path class="c" d="M109.73724,296.25484a68.32615,68.32615,0,1,1,68.3242-68.3262A68.40415,68.40415,0,0,1,109.73724,296.25484Zm0-134.6523a66.32615,66.32615,0,1,0,66.3242,66.3261A66.4016,66.4016,0,0,0,109.73724,161.60254Zm0,124.8769a58.55075,58.55075,0,1,1,58.5498-58.5508A58.61774,58.61774,0,0,1,109.73724,286.47944Zm0-115.1015a56.55075,56.55075,0,1,0,56.5498,56.5507A56.61513,56.61513,0,0,0,109.73724,171.37794Z"/><path class="c" d="M131.04584,337.21384l-5.5596-1.2051L113.09765,322.331l-19.72471,1.3555-11.83,11.9472-5.7832.8692-24.7364-13.2774-2.5761-4.3066v-16.0762l-11.3457-15.8984-18.6182,4.0527-5.667-3.2051-11.8164-34.832,2.5273-5.9824,12.169-5.7891.1162-28.6973-12.84179-8.4335-1.68751-6.2715,15.6406-31.2774,5.25-2.6211,17.041,3.1172,16.1426-14.7969-2.623-15.1386,3.0058-5.375,31.3653-12.502,4.9297-3.2305.6582,1.004,1.4707-.586,2.8818,7.2285,9.0732,13.8418,18.2481-2.8125,10.39451-11.2422,5.56839-1.1503,28.1953,12.4941,2.874,5.041-1.9111,16.1914,11.6426,16.3711,17.499-2.4355,5.2549,3.1406,13.20891,35.6035-2.19041,5.9629-12.2568,6.873-2.2901,20.9864,13.1885,11.1992,1.34381,5.6113L202.56924,288.749l-4.5088,3.0019h-20.1767l-13.4385,13.5742,3.2481,11.4395-2.76369,5.8222Zm-16.2529-24.7969,3.957,1.5938,11.6328,12.8437,26.7363-11.5429-2.85639-10.0606,1.22749-4.7734,16.8809-17.0528,3.47461-1.4492h18.96089l9.6866-23.166-12.44049-10.5664-1.69431-4.2539,2.8437-26.0645,2.4678-3.7343,10.8067-6.0586-10.3897-28-16.5459,2.3008-4.6562-2.0079-14.45409-20.3222-.87211-3.4063,1.7109-14.4824-21.7041-9.6152-9.17969,9.9257-2.84271,1.5137-23.043,3.5528-4.833-2.1504-10.7725-16.4375-25.8515,10.3027,2.4023,13.8652-1.5127,4.4375-19.9043,18.2442-4.18159,1.2051L24.02534,174.163l-12.05669,24.1094,11.42489,7.5039,2.20411,4.1055-.13971,34.4199-2.7881,4.3945-11.0176,5.2403,9.0176,26.58,17.5-3.8086,5.0176,1.9356,14.125,19.7949.91021,2.8399V315.997l18.94229,10.166,10.5586-10.666,3.1377-1.4375Z"/><path class="c" d="M131.04584,338.21384a.96592.96592,0,0,1-.2119-.0225l-5.5596-1.206a.996.996,0,0,1-.5293-.3057l-12.0625-13.3174-18.8642,1.295-11.5645,11.6796a.99761.99761,0,0,1-.5625.2852l-5.7832.8691a1.00549,1.00549,0,0,1-.6211-.1074l-24.7363-13.2763a1.00579,1.00579,0,0,1-.3858-.3682l-2.5761-4.3066a.99671.99671,0,0,1-.1416-.5137V303.163l-10.7774-15.1015-17.9736,3.913a.99375.99375,0,0,1-.7051-.1064l-5.667-3.207a.99848.99848,0,0,1-.4551-.5489l-11.81639-34.83a1.00268,1.00268,0,0,1,.02639-.711l2.5273-5.9834a.9963.9963,0,0,1,.49131-.5136l11.60149-5.5186.1113-27.5254-12.3886-8.1367a1.0006,1.0006,0,0,1-.417-.5762l-1.6875-6.2715a1.00153,1.00153,0,0,1,.07131-.707l15.64059-31.2773a1.003,1.003,0,0,1,.4482-.4473l5.25-2.6211a1.00891,1.00891,0,0,1,.626-.0889l16.5518,3.0264,15.373-14.0908-2.5293-14.6006a1.00249,1.00249,0,0,1,.1123-.6582l3.0059-5.375a1.00087,1.00087,0,0,1,.5029-.4395l31.2715-12.4648,4.8457-3.1768a1.00057,1.00057,0,0,1,1.3838.2881l.2109.3203.7119-.2832a1.0009,1.0009,0,0,1,1.2989.5586l2.8447,7.1348,8.665,13.2207,17.2676-2.6621,10.1572-10.9863a1.012,1.012,0,0,1,.5323-.3008l5.5683-1.1485a.99908.99908,0,0,1,.6074.0645l28.19541,12.4941a.99893.99893,0,0,1,.46379.418l2.8741,5.041a1.00176,1.00176,0,0,1,.124.6133l-1.8662,15.8086,11.06731,15.5625,16.89849-2.3516a.988.988,0,0,1,.6504.1328l5.2549,3.1407a.99475.99475,0,0,1,.4248.5097l13.2089,35.6035a1.00111,1.00111,0,0,1,.001.6934l-2.1904,5.9629a1.00041,1.00041,0,0,1-.4492.5273l-11.8076,6.6202-2.1768,19.9462,12.7842,10.8575a.99543.99543,0,0,1,.3252.5293l1.3437,5.6103a1.00009,1.00009,0,0,1-.0498.6182l-12.3193,29.4648a.99956.99956,0,0,1-.3682.4473l-4.5088,3.0019a1.003,1.003,0,0,1-.5547.168h-19.7597l-12.7344,12.8633,3.0889,10.8769a.99472.99472,0,0,1-.05859.7012l-2.76371,5.8223a1,1,0,0,1-.5068.4902l-33.8838,14.627A.99446.99446,0,0,1,131.04584,338.21384Zm-5.03419-3.1152,4.93259,1.0703,33.2432-14.3516,2.4414-5.1426-3.1455-11.0761a.99932.99932,0,0,1,.251-.9766l13.4384-13.5752a1.00338,1.00338,0,0,1,.711-.2959h19.874l4.00591-2.667,12.07609-28.8847-1.1914-4.9756-12.9394-10.9883a1.00013,1.00013,0,0,1-.3467-.8711l2.29-20.9863a1.00358,1.00358,0,0,1,.5049-.7637l11.9346-6.6914,1.9355-5.2695-12.958-34.9278-4.6504-2.7793-17.1503,2.3867a1,1,0,0,1-.9532-.4111l-11.6416-16.3711a.99442.99442,0,0,1-.1787-.6963l1.8731-15.8652-2.5489-4.4688L140.21774,123.29l-4.9395,1.0195-10.1767,11.0059a1.00431,1.00431,0,0,1-.5821.3105l-18.248,2.8125a1.00313,1.00313,0,0,1-.9883-.4394l-9.0732-13.8428a.93522.93522,0,0,1-.0928-.1787l-2.5117-6.2989-.542.2159a1.00106,1.00106,0,0,1-1.2061-.3809l-.1103-.168-4.0938,2.6836a1.05127,1.05127,0,0,1-.1777.0928l-31.0361,12.3711-2.6592,4.7539,2.5625,14.7891a.99969.99969,0,0,1-.3096.9082l-16.1426,14.7968a1.00562,1.00562,0,0,1-.8554.2471l-16.7129-3.0566-4.6534,2.3232L2.34765,197.89354l1.49019,5.5371,12.5234,8.2246a1.00019,1.00019,0,0,1,.4512.8399l-.1162,28.6972a1.00139,1.00139,0,0,1-.5703.8994l-11.8252,5.625-2.2315,5.2823,11.5713,34.1093,5.003,2.8311,18.24511-3.9727a.99584.99584,0,0,1,1.02729.3955l11.34571,15.9004a1.00532,1.00532,0,0,1,.18559.5801v15.8008l2.2929,3.832,24.1993,12.9883,5.1269-.7695,11.5957-11.7119a1.00114,1.00114,0,0,1,.64261-.294l19.72459-1.3545a1.00287,1.00287,0,0,1,.8096.3262Zm4.37109-7.2442a.99947.99947,0,0,1-.7412-.3291l-11.4785-12.6728-3.5313-1.4229-23.5195,1.6143-2.80659,1.2871-10.42971,10.5361a1.00485,1.00485,0,0,1-1.1836.1778l-18.9424-10.167a.99964.99964,0,0,1-.52729-.8809v-14.5625l-.81451-2.542-13.8604-19.4228-4.458-1.7207-17.207,3.7451a1.00381,1.00381,0,0,1-1.16009-.6553L10.705,254.25874a1.00006,1.00006,0,0,1,.51761-1.2246l10.75779-5.1172,2.4785-3.9082.13771-33.8779-1.96391-3.6572-11.2129-7.3653a.99994.99994,0,0,1-.3457-1.2832l12.0567-24.1093a1.0056,1.0056,0,0,1,1.0742-.5362l15.5908,2.8516,3.7295-1.0752,19.543-17.9121,1.3476-3.9551-2.3584-13.6113a.99809.99809,0,0,1,.6152-1.0996l25.8516-10.3047a1.00079,1.00079,0,0,1,1.2061.3808l10.6113,16.1914,4.29591,1.9122,22.585-3.4825,2.54389-1.3535,9.0645-9.8027a.9947.9947,0,0,1,1.13961-.2344l21.70409,9.6152a1,1,0,0,1,.58791,1.0313l-1.68951,14.2969.7803,3.0459,14.1836,19.9443,4.14361,1.7861,16.26849-2.2627a.9966.9966,0,0,1,1.0752.6426l10.3897,28a1.00093,1.00093,0,0,1-.4483,1.2207l-10.5957,5.9395-2.2002,3.3291-2.79,25.5771,1.5097,3.792,12.251,10.4053a1,1,0,0,1,.2754,1.1484l-9.6865,23.166a1.00162,1.00162,0,0,1-.9229.6133h-18.7607l-3.1055,1.2949-16.5488,16.7178-1.0928,4.249,2.7823,9.8008a1.00055,1.00055,0,0,1-.5655,1.1914l-26.7363,11.543A1.00806,1.00806,0,0,1,130.38274,327.85444Zm-15.5898-16.4375a.98515.98515,0,0,1,.374.0723l3.957,1.5947a.99151.99151,0,0,1,.3672.2559l11.1524,12.3125,25.2744-10.9112-2.6172-9.2177a.99918.99918,0,0,1-.0069-.5225l1.2276-4.7734a1.00131,1.00131,0,0,1,.2578-.4541l16.8809-17.0528a1.01246,1.01246,0,0,1,.3261-.2197l3.4746-1.4473a1.00531,1.00531,0,0,1,.38481-.0781h18.29489l9.1475-21.8769-11.8828-10.0928a1.011,1.011,0,0,1-.2813-.3916l-1.69429-4.2559a.99966.99966,0,0,1-.06541-.4785l2.8437-26.0644a1.00125,1.00125,0,0,1,.1602-.4424l2.4677-3.7334a.99342.99342,0,0,1,.34481-.3203l10.06049-5.6407-9.81249-26.4462-15.75,2.1904a.97722.97722,0,0,1-.53321-.0713l-4.6562-2.0078a1.00221,1.00221,0,0,1-.419-.3389l-14.4541-20.3232a.99643.99643,0,0,1-.1543-.3311l-.8721-3.4062a1.00512,1.00512,0,0,1-.0244-.3653l1.6241-13.75-20.375-9.0273L131.12,143.204a1.01287,1.01287,0,0,1-.2646.2032l-2.84279,1.5136a1.004,1.004,0,0,1-.31741.1055l-23.043,3.5527a.99346.99346,0,0,1-.55859-.0742l-4.833-2.1513a.99589.99589,0,0,1-.42961-.3653l-10.3252-15.7549-24.3389,9.7012,2.26371,13.0645a.99812.99812,0,0,1-.03911.4941l-1.5127,4.4375a1.00308,1.00308,0,0,1-.2705.4141L44.704,176.58884a1.00473,1.00473,0,0,1-.39839.2246l-4.18171,1.2051a.99136.99136,0,0,1-.457.0225l-15.083-2.7588-11.32329,22.6425,10.68169,7.0157a.997.997,0,0,1,.332.3632l2.2041,4.1045a.99365.99365,0,0,1,.11921.4776l-.13971,34.4199a1.0053,1.0053,0,0,1-.15529.5312l-2.78811,4.3946a.99845.99845,0,0,1-.415.3672l-10.2148,4.8593,8.4453,24.8926,16.6269-3.6201a1.00922,1.00922,0,0,1,.5733.0439l5.0175,1.9366a1.004,1.004,0,0,1,.4542.3525l14.125,19.7949a.99816.99816,0,0,1,.1376.2754l.9102,2.8399a.97931.97931,0,0,1,.04791.3047v14.1201l17.75679,9.5302,10.03321-10.1347a.99028.99028,0,0,1,.29389-.2051l3.1377-1.4395a1.00262,1.00262,0,0,1,.3487-.0878l23.9316-1.6426C114.747,311.41694,114.77044,311.41694,114.79294,311.41694Z"/><path class="c" d="M237.09954,141.17284c-1.5156,0-3.0518-.0664-4.5674-.1973a52.45478,52.45478,0,0,1,4.459-104.7148c1.5147,0,3.0518.0664,4.5674.1972a52.455,52.455,0,0,1-4.459,104.7149Zm-.1084-95.1367a42.67879,42.67879,0,0,0-3.6182,85.1992c1.2373.1094,2.4903.1621,3.7266.1621a42.67883,42.67883,0,0,0,3.6182-85.1992C239.48044,46.08884,238.22654,46.03614,236.99114,46.03614Z"/><path class="c" d="M237.09954,142.17284c-1.5361,0-3.1016-.0674-4.6533-.2012a53.45471,53.45471,0,0,1,4.5449-106.7109c1.543,0,3.1094.0683,4.6533.2011a53.45489,53.45489,0,0,1-4.5449,106.711Zm-.1084-104.9121a51.48069,51.48069,0,1,0,4.48151.1933C239.98524,37.32614,238.47744,37.26074,236.99114,37.26074Zm.1084,95.1367c-1.2734,0-2.5566-.0547-3.8135-.166a43.7254,43.7254,0,0,1-39.7568-47.2735,43.93149,43.93149,0,0,1,43.4619-39.9218c1.2744,0,2.5576.0547,3.81351.166a43.72353,43.72353,0,0,1,39.75578,47.2734A43.93271,43.93271,0,0,1,237.09954,132.39744Zm-.1084-85.3613a41.91982,41.91982,0,0,0-41.4697,38.0937,41.72568,41.72568,0,0,0,37.9375,45.1094c1.2002.1055,2.4248.1582,3.6406.1582a41.9204,41.9204,0,0,0,41.46879-38.0938,41.72564,41.72564,0,0,0-37.93659-45.1093C239.43154,47.08884,238.20694,47.03614,236.99114,47.03614Z"/><path class="c" d="M246.44524,174.06544l-5.4433-1.668-8.168-10.6992-14.0459-.2305-9.3115,8.0137-5.8692.3808-17.789-11.6757-2.1875-4.5079,1.0195-11.8164-7.0869-11.8574-13.7061,1.7598-5.3672-3.6778-6.7168-27.2148,3.03131-5.748,8.915-3.3379,1.84079-20.418-8.6992-6.8535-1.14259-6.3926,13.92-22.7129,5.457-2.1601,12.16989,3.33,12.2588-9.4316-.9463-11.0273,3.4571-5.0958L226.55265,3.624,231.42674.999l.5341.9921,1.3584-.4101,2.0147,6.6758,5.5664,10.3418,12.8828-.8614,8.3242-7.5781,5.64649-.668,20.583,11.3321,2.4287,5.2715-2.4228,11.7207,7.1767,12.1953,12.7364-.6641,4.96682,3.5801,7.69228,27.8887-2.6972,5.7519-9.20608,4.166-2.86722,14.5235,8.793,8.539.91022,5.8008-11.24912,21.1738-4.7373,2.5782-14.7861-1.2793-10.2979,9.0078,1.5938,8.4765-3.2441,5.5352Zm-29.3564-22.4004,18.2724.2968,3.8057,1.9219,7.4805,9.8008,19.331-6.5039-1.3242-7.0391,1.5859-4.5839,13.9649-12.2149,3.63862-1.1914,13.63768,1.1797,8.0127-15.0801-8.0625-7.832-1.3896-4.4512,3.8662-19.5957,2.7793-3.5078,7.6934-3.4785-5.53718-20.0742-11.77632.6152-4.4668-2.4024-9.6377-16.373-.57418-3.4687,2.07418-10.0352-14.3613-7.9063-7,6.3731-2.96289,1.2617-17.72271,1.1856-4.6308-2.5586-6.64649-12.3457-18.48251,5.5781.836,9.7383-1.8897,4.2929-16.3027,12.5411-4.2705.8398-10.97459-3.002-9.73151,15.8789,7.3682,5.8047,1.8428,4.2774-2.35449,26.1172-3.15431,4.1386-7.71969,2.8907,4.62979,18.7597,12.5596-1.6093,4.8174,2.3398,9.5391,15.959.6738,2.9277-.9043,10.4863,12.2715,8.0547,7.9297-6.8242Z"/><path class="c" d="M246.44524,175.06544a1.00183,1.00183,0,0,1-.293-.045l-5.4433-1.666a1.00591,1.00591,0,0,1-.502-.3496l-7.874-10.3144-13.1807-.2168-9.0234,7.7656a1.0124,1.0124,0,0,1-.5879.2402l-5.8691.3809a.97674.97674,0,0,1-.6133-.1621l-17.7891-11.6758a1,1,0,0,1-.3506-.4004l-2.1875-4.5059a.9974.9974,0,0,1-.0966-.5234l.9912-11.4941-6.585-11.0176-13.0566,1.6748a1.00374,1.00374,0,0,1-.6924-.167l-5.3672-3.6758a1.00409,1.00409,0,0,1-.4053-.5859l-6.71679-27.2158a1.00057,1.00057,0,0,1,.086-.7061l3.03119-5.7471a1,1,0,0,1,.5342-.4697l8.3262-3.1172,1.7353-19.2519-8.2734-6.5186a.99679.99679,0,0,1-.3652-.6103l-1.1426-6.3926a1.002,1.002,0,0,1,.1318-.6992l13.9199-22.711a1.00035,1.00035,0,0,1,.4844-.4082l5.457-2.1601a1.00264,1.00264,0,0,1,.6319-.0352l11.6904,3.1992,11.4316-8.7949-.8994-10.4824a.9988.9988,0,0,1,.169-.6465l3.457-5.0977a.99738.99738,0,0,1,.5381-.3955l24.4209-7.3711L230.953.11914a1.00116,1.00116,0,0,1,1.3535.4052l.1475.2735.5762-.1748a.99955.99955,0,0,1,1.2461.6689l1.98631,6.5791,5.21289,9.6866,11.8926-.795,8.0664-7.3437a1.00563,1.00563,0,0,1,.5556-.2539l5.64651-.666a.98957.98957,0,0,1,.59958.1171l20.583,11.3321a.99523.99523,0,0,1,.4258.457l2.4287,5.2705a.99858.99858,0,0,1,.0713.6211l-2.3447,11.3447,6.6728,11.3399,12.1309-.6338a1.0107,1.0107,0,0,1,.6367.1875l4.9668,3.5801a1.00112,1.00112,0,0,1,.3789.5449l7.6924,27.8887a.997.997,0,0,1-.0586.6904l-2.6973,5.7529a1.00116,1.00116,0,0,1-.4931.4873l-8.7363,3.9522-2.6631,13.4853,8.4033,8.1621a.99643.99643,0,0,1,.292.5625l.9101,5.7998a1.00342,1.00342,0,0,1-.1054.6241l-11.2491,21.1748a.99741.99741,0,0,1-.4052.4091l-4.7373,2.5772a.98726.98726,0,0,1-.5635.1182l-14.3623-1.2422-9.5479,8.3515,1.4883,7.9161a.99547.99547,0,0,1-.1201.6894l-3.2441,5.5352a.99219.99219,0,0,1-.544.4423l-26.68158,8.9756A.9845.9845,0,0,1,246.44524,175.06544Zm-4.8437-3.5293,4.82911,1.4785,26.02238-8.7539,2.8662-4.8897-1.5244-8.1084a.99934.99934,0,0,1,.3242-.9375l10.2979-9.0078a1.00065,1.00065,0,0,1,.7441-.2441l14.4864,1.2529,4.2089-2.29,10.9512-20.6143-.8066-5.1406-8.5528-8.3076a1.00061,1.00061,0,0,1-.2851-.9112l2.8672-14.5234a1.00023,1.00023,0,0,1,.5693-.7178l8.86912-4.0117,2.38378-5.085-7.5-27.1923-4.3955-3.169-12.3857.6465a1.00026,1.00026,0,0,1-.9141-.4912l-7.1767-12.1953a1.00713,1.00713,0,0,1-.1172-.71l2.35642-11.3994-2.15332-4.6728-20.01368-11.0186-5.00971.5918-8.0869,7.3613a.996.996,0,0,1-.6065.2588l-12.8828.8613a.96579.96579,0,0,1-.9473-.5234L234.453,8.73044a1.00712,1.00712,0,0,1-.0761-.1846l-1.7256-5.7187-.4014.1211a.99827.99827,0,0,1-1.1689-.4824l-.0606-.1114-3.9941,2.1494a.95272.95272,0,0,1-.1846.0772l-24.1777,7.2978-3.0596,4.5098.916,10.6729a.99806.99806,0,0,1-.3867.8779l-12.2588,9.4316a.9925.9925,0,0,1-.873.1719l-11.8487-3.2412-4.8359,1.915-13.5469,22.1026,1.0088,5.6455,8.40041,6.6181a1.00012,1.00012,0,0,1,.37689.875l-1.8408,20.418a1.00032,1.00032,0,0,1-.6455.8477l-8.55859,3.2041-2.67581,5.0732,6.5342,26.4766,4.7383,3.2461,13.3281-1.71a1.00019,1.00019,0,0,1,.9854.4785l7.0869,11.8574a.99909.99909,0,0,1,.1377.5987l-.9961,11.5429,1.94731,4.0098,17.27829,11.3418,5.2021-.3379,9.05761-7.7949a1.0208,1.0208,0,0,1,.669-.2422l14.04589.2305a1.00313,1.00313,0,0,1,.7783.3935Zm5.0459-6.8516a1.00046,1.00046,0,0,1-.795-.3935l-7.34079-9.6182-3.39651-1.7149-17.8593-.291-2.9209,1.0567-7.7901,6.705a1.00106,1.00106,0,0,1-1.20119.0782l-12.27141-8.0547a.99914.99914,0,0,1-.4473-.9219l.8906-10.3291-.6035-2.6201-9.3047-15.5693-4.2812-2.0791-12.2637,1.5722a.996.996,0,0,1-1.0977-.7519l-4.6298-18.7608a.99932.99932,0,0,1,.6201-1.1767l7.4502-2.7891,2.8047-3.6797,2.3056-25.5771-1.6426-3.8116-7.1689-5.6474a1.00052,1.00052,0,0,1-.23339-1.3076L175.202,43.124a.99389.99389,0,0,1,1.1163-.4424l10.748,2.9404,3.8096-.75,15.914-12.2422,1.6836-3.8242-.8134-9.4844a1,1,0,0,1,.707-1.0429l18.4824-5.5791a1.00124,1.00124,0,0,1,1.1699.4834l6.5069,12.0869,4.1162,2.2744,17.2597-1.1543,2.65141-1.1289,6.875-6.2598a1,1,0,0,1,1.15528-.1367l14.3613,7.9062a1.0019,1.0019,0,0,1,.4971,1.0791l-2.0371,9.8526.5136,3.1035,9.4014,15.9707,3.9746,2.1367,11.4971-.5996a.97286.97286,0,0,1,1.0156.7324l5.53712,20.0733a.99971.99971,0,0,1-.55172,1.1767l-7.4727,3.3799-2.4775,3.127-3.7705,19.1084,1.2382,3.9677,7.8799,7.6543a.99961.99961,0,0,1,.1866,1.1866l-8.0127,15.08a1.00111,1.00111,0,0,1-.9688.5274l-13.4346-1.1621-3.2509,1.0644-13.6114,11.9063-1.4121,4.082,1.2744,6.7774a.99932.99932,0,0,1-.6631,1.1328l-19.331,6.5039A1.01019,1.01019,0,0,1,246.64744,164.68454Zm-29.5586-14.0195h.0166l18.2724.2968a1.0095,1.0095,0,0,1,.4346.1075l3.8057,1.9218a1.00514,1.00514,0,0,1,.3437.2862l7.0459,9.2304,17.82719-5.998-1.16309-6.1836a1.00431,1.00431,0,0,1,.0371-.5127l1.58589-4.582a1.00957,1.00957,0,0,1,.2871-.4258l13.9649-12.2158a1.002,1.002,0,0,1,.3476-.1983l3.6387-1.1894a.95362.95362,0,0,1,.39652-.0469l12.97848,1.1221,7.3506-13.833-7.5235-7.3076a.99219.99219,0,0,1-.2578-.419l-1.3896-4.4531a.99452.99452,0,0,1-.0274-.4903l3.8663-19.5957a1.00394,1.00394,0,0,1,.1972-.4277l2.7793-3.5078a.9889.9889,0,0,1,.3721-.2891l6.9141-3.1269-5.09578-18.4746-10.97462.5722a1.02673,1.02673,0,0,1-.52538-.1171l-4.46672-2.4024a.98741.98741,0,0,1-.3877-.373l-9.6377-16.3731a.98327.98327,0,0,1-.125-.3437l-.5743-3.4707a.99962.99962,0,0,1,.0069-.3653l1.9248-9.3125-13.0879-7.2051-6.47169,5.8907a1.00738,1.00738,0,0,1-.2812.1816l-2.9629,1.2617a1.00357,1.00357,0,0,1-.3252.0772l-17.7227,1.1865a1.01946,1.01946,0,0,1-.5498-.123l-4.6308-2.5586a1.01434,1.01434,0,0,1-.3975-.4014l-6.2588-11.626-16.92379,5.1074.76659,8.9307a.998.998,0,0,1-.0811.4883l-1.8896,4.292a1.00259,1.00259,0,0,1-.3057.3896l-16.3027,12.542a1.0227,1.0227,0,0,1-.416.1895l-4.27049.8398a1.01505,1.01505,0,0,1-.45711-.0176l-10.251-2.8037-8.874,14.4815,6.6699,5.2539a.99637.99637,0,0,1,.2989.3906l1.8427,4.2773a.99136.99136,0,0,1,.0782.4844l-2.3545,26.1172a1.00326,1.00326,0,0,1-.2012.5176l-3.1543,4.1367a.98769.98769,0,0,1-.4443.3301l-6.8858,2.5791,4.2022,17.0303,11.6758-1.4961a.99267.99267,0,0,1,.5634.0918l4.8174,2.3398a1.002,1.002,0,0,1,.4219.3867l9.5391,15.96a.99234.99234,0,0,1,.1162.2891l.6738,2.9277a1.001,1.001,0,0,1,.0215.3096l-.8526,9.8906,11.1426,7.3144,7.3584-6.332a.99916.99916,0,0,1,.3125-.1836l3.2666-1.1816A1.00246,1.00246,0,0,1,217.08884,150.665Z"/></svg>
                                        <span class="grow flex items-center content-center text-lg font-semibold">Ausstehend</span>
                                </div>
                            </div>
                        </div>
                        @error('regulationType')
                            <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                        @enderror
                        <div class="flex justify-center space-x-4 mt-12">
                            <x-buttons.backbutton wire:click="previousStep" />
                            @if (!is_null($regulationType))
                                <x-buttons.furtherbutton wire:click="nextStep" />
                            @endif
                        </div>
                    </div>
                    {{-- Step 4: Fallstatus Details --}}
                    <div x-show="step == 4"  x-cloak  >
                        <h2 class="text-lg font-bold mb-6">
                            @switch($regulationType)
                                @case('vollzahlung')
                                    Grund für Bewertung des Falls
                                    @break
                                @case('teilzahlung')
                                    Was war deiner Meinung nach der Grund für die Teilzahlung?
                                    @break
                                @case('ablehnung')
                                    Wie wurde die Ablehnung deiner Versicherung begründet?
                                    @break
                                @case('austehend')
                                    Was ist der Grund für die ausstehende Regulierung?
                                    @break
                                @default
                                    Keine gültige Auswahl getroffen.
                            @endswitch
                        </h2>
                        <x-alert class="w-max mx-auto mb-6" role="alert">
                                <span>
                                    Bitte beschreibe den Grund für deine Bewertung des Falls. Deine Angaben helfen anderen Nutzern, die Qualität der Versicherung besser einzuschätzen und ihre Entscheidungen zu treffen.
                            </span>
                        </x-alert>
                        <div class="flex justify-center items-center">
                            <div class="xl:grid xl:grid-cols-2 xl:justify-center  items-center xl:space-x-4 w-full">
                                {{-- Karte: Vollzahlung --}}
                                
                                <div class="mb-4">
                                    <div class="mt-4 text-left bg-secondary text-white rounded-lg shadow-md p-4">
                                        <h3 class="text-lg font-semibold mb-2">Bitte wähle eine Option:</h3>
                                        {{-- Eingabefeld je nach Typ --}}
                                        @switch($regulationType)
                                            @case('vollzahlung')
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Schnelle und unkomplizierte Abwicklung" value="Schnelle und unkomplizierte Abwicklung" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70 " aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto"> Schnelle und unkomplizierte Abwicklung </span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Gute Kommunikation und Transparenz" value="Gute Kommunikation und Transparenz" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70 " aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto"> Gute Kommunikation und Transparenz</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Faire und angemessene Regulierung" value="Faire und angemessene Regulierung" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70 " aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto"> Faire und angemessene Regulierung</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Hervorragender Kundenservice" value="Hervorragender Kundenservice" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70 " aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto"> Hervorragender Kundenservice</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Erwartungen vollständig erfüllt" value="Erwartungen vollständig erfüllt" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70 " aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto"> Erwartungen vollständig erfüllt</span>
                                                </label>
                                                <label class="flex my-3 pb-3   text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Andere Gründe" value="Andere Gründe" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70 " aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto"> Andere Gründe</span>
                                                </label>
                                                @break
                                            @case('teilzahlung')
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Nur ein Teil des Schadens wurde anerkannt" value="Nur ein Teil des Schadens wurde anerkannt" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Nur ein Teil des Schadens wurde anerkannt</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Es gab eine Selbstbeteiligung" value="Es gab eine Selbstbeteiligung" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Es gab eine Selbstbeteiligung</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Die Versicherung hat die Summe nach Gutachten gekürzt" value="Die Versicherung hat die Summe nach Gutachten gekürzt" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Die Versicherung hat die Summe nach Gutachten gekürzt</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Kulanzzahlung statt voller Erstattung" value="Kulanzzahlung statt voller Erstattung" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Kulanzzahlung statt voller Erstattung</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Unklare Kommunikation / keine nachvollziehbare Begründung" value="Unklare Kommunikation / keine nachvollziehbare Begründung" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Unklare Kommunikation / keine nachvollziehbare Begründung</span>
                                                </label>
                                                <label class="flex my-3 pb-3   text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Andere Gründe" value="Andere Gründe" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Andere Gründe</span>
                                                </label>
                                                @break
                                            @case('ablehnung')
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Der Schaden sei nicht versichert" value="Der Schaden sei nicht versichert" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Der Schaden sei nicht versichert</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Formfehler oder Fristversäumnis" value="Formfehler oder Fristversäumnis" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Formfehler oder Fristversäumnis</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Verdacht auf Eigenverschulden" value="Verdacht auf Eigenverschulden" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Verdacht auf Eigenverschulden</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Kein nachvollziehbarer Grund genannt" value="Kein nachvollziehbarer Grund genannt" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Kein nachvollziehbarer Grund genannt</span>
                                                </label>
                                                <label class="flex my-3 pb-3   text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Andere Gründe" value="Andere Gründe" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Andere Gründe</span>
                                                </label>
                                                @break
                                            @case('austehend')
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Warte auf Rückmeldung der Versicherung" value="Warte auf Rückmeldung der Versicherung" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Warte auf Rückmeldung der Versicherung</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Benötigte Unterlagen wurden noch nicht eingereicht" value="Benötigte Unterlagen wurden noch nicht eingereicht" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Benötigte Unterlagen wurden noch nicht eingereicht</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Versicherung benötigt mehr Zeit zur Bearbeitung" value="Versicherung benötigt mehr Zeit zur Bearbeitung" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Versicherung benötigt mehr Zeit zur Bearbeitung</span>
                                                </label>
                                                <label class="flex my-3 pb-3 border-b border-white  text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Unklare Kommunikation seitens der Versicherung" value="Unklare Kommunikation seitens der Versicherung" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Unklare Kommunikation seitens der Versicherung</span>
                                                </label>
                                                <label class="flex my-3 pb-3   text-base items-center  content-center">
                                                    <input type="checkbox" wire:model.live.debounce.250ms="regulationDetails" name="Andere Gründe" value="Andere Gründe" role="switch" class="peer sr-only">
                                                    <div class="size-14 flex-none mr-3 relative h-6 !w-11 after:h-5 after:w-5 peer-checked:after:translate-x-5 rounded-full border border-outline bg-gray-300 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-200 peer-checked:after:bg-blue-500 peer-focus:outline-2 peer-focus:outline-offset-2 peer-focus:outline-outline-strong peer-focus:peer-checked:outline-blue-500 peer-active:outline-offset-0 peer-disabled:cursor-not-allowed peer-disabled:opacity-70" aria-hidden="true"></div>
                                                    <span class="size-14 grow flex text-lg items-center content-center h-auto">Andere Gründe</span>
                                                </label>
                                                @break
                                            @default
                                                Keine gültige Auswahl getroffen.
                                        @endswitch
                                    </div>
                                    @error('regulationDetails')
                                        <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div x-data="{ charCount: 0 }" >
                                    <h3 class="text-lg text-left font-semibold mb-2">Zusätzliche Informationen</h3>
                                    <textarea wire:model.live.debounce.500ms="regulationComment"
                                            class="focus:shadow-blue-300 min-h-unset  text-base leading-5.6 ease-soft block h-auto w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 outline-none transition-all placeholder:text-gray-500 focus:border-blue-300 focus:outline-none" rows="6"
                                            x-on:input="charCount = $event.target.value.length"
                                            maxlength="255" ></textarea>
                                    <span x-text="`${charCount}/255 Zeichen`" class="text-sm " :class="charCount >= 255 ? 'text-red-600' : 'text-gray-500 '"></span>
                                    @error('regulationComment')
                                        <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        @error('regulationDetail')
                            <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                        @enderror
                        <div class="flex justify-center space-x-4 mt-12">
                            <x-buttons.backbutton wire:click="previousStep" />
                            @if (!is_null($regulationDetails))
                                <x-buttons.furtherbutton wire:click="nextStep" />
                            @endif
                        </div>

                    </div>
                    {{-- Step 5: Versicherungs Vertragsdaten --}}
                    <div x-show="step == 5"  x-cloak  >
                        <div>
                            <h2 class="text-lg font-bold mb-6">Versicherungs Vertragsdaten</h2>
                            <x-alert class="w-max mx-auto mb-6" role="alert">
                                    <span>
                                        Bitte geben Sie die Vertragsdaten der Versicherung an, bei der Sie den Schaden gemeldet haben. Diese Informationen sind wichtig, um den Schadenfall korrekt zuzuordnen und zu bearbeiten.
                                </span>
                            </x-alert>
                            <div class="xl:grid xl:grid-cols-2 xl:justify-center  items-center xl:space-x-4 w-full">
                                <div  class="mb-4 mt-4 text-left bg-secondary  rounded-lg shadow-md p-4">

                                    <div class="">
                                        <label class="block text-sm font-medium text-white mb-2"> Deckungssumme </label>
                                        <input  x-mask:dynamic="$money($input)" wire:model.live.debounce.250ms="contractDetails.contract_coverage_amount" class="w-full border px-3 py-2 rounded" placeholder="z.B. 100 000 €" />
                                        @error('contractDetails.contract_coverage_amount')
                                            <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-white mb-2"> Selbstbeteiligung </label>
                                        <input  x-mask:dynamic="$money($input)" wire:model.live.debounce.250ms="contractDetails.contract_deductible_amount" class="w-full border px-3 py-2 rounded" placeholder="z.B. 100 000 €" />
                                        @error('contractDetails.contract_deductible_amount')
                                            <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                                        @enderror
                                    </div>
    
                                    <div class="mt-4 ">
                                        <label class="block text-sm font-medium text-white mb-2"> Schadenshöhe </label>
                                        <input  x-mask:dynamic="$money($input)" wire:model.live.debounce.250ms="contractDetails.claim_amount" class="w-full border px-3 py-2 rounded" placeholder="z.B. 100 000 €" />
                                        @error('contractDetails.claim_amount')
                                            <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @if ($regulationType == 'austehend' || $regulationType == 'abgelehnt')
                                        <div class="my-4 ">
                                            <label class="block text-sm font-medium text-white mb-2"> Regulierungshöhe </label>
                                            <input  x-mask:dynamic="$money($input)" wire:model.live.debounce.250ms="contractDetails.claim_settlement_amount" class="w-full border px-3 py-2 rounded" placeholder="z.B. 100 000 €" />
                                            @error('contractDetails.claim_settlement_amount')
                                                <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif
                                </div>
                                <div x-data="{ charCount: 0 }" >
                                    <h3 class="text-lg text-left font-semibold mb-2">Weitere Angaben zum Vertrag </h3>
                                    <textarea wire:model.live.debounce.500ms="contractDetails.textarea_value"
                                            class="focus:shadow-blue-300 min-h-unset  text-base leading-5.6 ease-soft block h-auto w-full appearance-none rounded-lg border border-solid border-gray-300 bg-white bg-clip-padding px-3 py-2 font-normal text-gray-700 outline-none transition-all placeholder:text-gray-500 focus:border-blue-300 focus:outline-none" rows="6"
                                            x-on:input="charCount = $event.target.value.length"
                                            maxlength="255" ></textarea>
                                    <span x-text="`${charCount}/255 Zeichen`" class="text-sm " :class="charCount >= 255 ? 'text-red-600' : 'text-gray-500 '"></span>
                                    @error('contractDetails.textarea_value')
                                        <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                                    @enderror
                                </div>

                            </div>
                        </div>
                        <div class="flex justify-center space-x-4 mt-12">
                            <x-buttons.backbutton wire:click="previousStep" />

                            @if (!is_null($contractDetails['contract_coverage_amount']) &&
                                !is_null($contractDetails['contract_deductible_amount']) &&
                                !is_null($contractDetails['claim_amount']) &&
                                (!($regulationType == 'austehend' || $regulationType == 'abgelehnt') || !is_null($contractDetails['claim_settlement_amount'])))
                                <x-buttons.furtherbutton wire:click="nextStep" />
                            @endif
                        </div>
                    </div>    
                    {{-- Step 6: Zeitraum --}}
                    <div x-show="step == 6"  x-cloak  >
                        <div>
                                    
                            <div x-data="{
                                    selectedDates: @entangle('selectedDates'),
                                    is_closed: @entangle('is_closed'),
                                    initDatepicker(refName, bindTo, is_closed) {
                                        const modus = is_closed ? 'range' : 'single';
                                        flatpickr(refName, {
                                            dateFormat: 'd.m.Y',
                                            defaultDate: bindTo || null,
                                            locale: 'de',
                                            inline: true,
                                            allowInput: true,
                                            mode: modus,
                                            onChange: function(selectedDates, dateStr) {
                                                    bindTo = dateStr;
                                            },
                                            onReady: function(selectedDates, dateStr, instance) {
                                                if (!bindTo) {
                                                    const targetDate = new Date();
                                                    targetDate.setMonth(targetDate.getMonth() - 6);
                                                    instance.changeMonth(targetDate.getMonth(), false);
                                                    instance.changeYear(targetDate.getFullYear());
                                                }
                                            }
                                        });
                                    }
                                }" 
                                x-init="$nextTick(() => { initDatepicker($refs.dates, selectedDates, {{ $is_closed }} ) })" 
                                class="flex flex-col items-center"
                                
                            >
                                <h2 class="text-lg font-bold mb-12">
                                    @if ($is_closed)
                                        In welchem Zeitraum wurde der Fall bearbeitet?
                                    @else
                                        Wann hast du den Fall gemeldet?
                                    @endif
                                </h2>
                                <div class="inline-flex mb-5">
                                    <button type="button" 
                                        :class="{ 'opacity-50 cursor-not-allowed': !selectedDates }"
                                        :disabled="true"
                                        class="px-2 py-1 text-sm font-medium bg-white border border-l btn  @if ($is_closed) rounded-r-none rounded-l border-r-0 @else  rounded @endif  text-blue-500 border-blue-500 hover:bg-blue-500 hover:text-white focus:bg-blue-500 focus:z-10 focus:ring-2 focus:ring-blue-500/30 focus:text-white ">
                                        {{ $started_at ?? 'Start' }}
                                    </button>
                                    @if ($is_closed)
                                        <button type="button"
                                            :class="{ 'opacity-50 cursor-not-allowed': !selectedDates }"
                                            :disabled="true"
                                            class="px-2 py-1 text-sm font-medium bg-white border rounded-r btn rounded-l-none text-blue-500 border-blue-500 hover:bg-blue-500 hover:text-white focus:z-10 focus:ring-2 focus:bg-blue-500 focus:ring-blue-500/30 focus:text-white">
                                            {{ $ended_at ?? 'Ende' }}
                                        </button>
                                    @endif
                                </div>
                                <div>    
                                    {{-- Startdatum --}}
                                    <label  class="block text-sm font-medium text-gray-700" wire:ignore >
                                        <input type="text" data-input data-id="inline" readonly="readonly" x-ref="dates" wire:model.live="selectedDates"  class="hidden max-w-xl  rounded px-4 py-2 text-center flatpickr flatpickr-input" />
                                    </label>
                                </div> 
                            </div>
                            @error('started_at')
                                <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                            @enderror
                            @error('ended_at')
                                <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-center space-x-4 mt-12">
                            <x-buttons.backbutton wire:click="previousStep" />

                            @if ($started_at && (!$is_closed || $ended_at))
                                <x-buttons.furtherbutton wire:click="nextStep" />
                            @endif
                        </div>
                    </div>
                    {{-- Step 6: Fragen durchgehen --}}
                        @foreach ($variableQuestions as $index => $q)
                            @php
                                $currentStep = $standardSteps + $index;
                                $fieldName = "answers." . $q->title;
                            @endphp
                            <div x-show="step === {{ $currentStep }}"  x-cloak >
                                <div>
                                    <h2 class="text-lg font-bold mb-2">Frage {{ $currentStep + 1 }} von {{ $totalSteps  }}</h2>
                                    <p class="text-md text-gray-800 mb-12 font-semibold">{{ $q->question_text }}</p>
                                    {{-- Eingabefeld je nach Typ --}}
                                    @switch($q->type)
                                        @case('text')
                                        @case('number')
                                        @case('date')
                                            <input type="{{ $q->type }}"
                                                wire:model.defer="{{ $fieldName }}"
                                                class="mt-2 w-full border px-3 py-2 rounded">
                                            @break
                                        @case('textarea')
                                            <div x-data="{ charCount: 0 }">
                                                <textarea wire:model.defer="{{ $fieldName }}"
                                                        x-ignore
                                                        class="mt-2 w-full text-base mx-auto border px-3 py-2 rounded" rows="4"
                                                        x-on:input="charCount = $event.target.value.length"
                                                        maxlength="255"></textarea>
                                                <span x-text="`${charCount}/255 Zeichen`" class="text-sm " :class="charCount >= 255 ? 'text-red-600' : 'text-gray-500 '"></span>
                                            </div>
                                            @break
                                        @case('boolean')
                                            <div class="mt-2 space-x-4">
                                                <div
                                                    class="w-max mx-auto rounded-full overflow-hidden flex bg-gray-50 border border-gray-300  group-checked:border-gray-500">
                                                    <div>
                                                        <input type="radio" wire:model.live="{{ $fieldName }}" id="yes" value="1" class="peer hidden">
                                                        <label for="yes"
                                                            class="py-2 px-6 cursor-pointer peer-checked:text-blue-700 peer-checked:cursor-default peer-checked:bg-gray-200 text-gray-400">Ja</label>
                                                    </div>
                                                    <div>
                                                        <input type="radio" wire:model.live="{{ $fieldName }}" id="no" value="0" class="peer hidden">
                                                        <label for="no"
                                                            class="py-2 px-4 cursor-pointer peer-checked:text-blue-700 peer-checked:cursor-default peer-checked:bg-gray-200 text-gray-400"">Nein</label>
                                                    </div>
                                                </div>
                                            </div>
                                            @break
                                        @case('rating')
                                            <div class="flex justify-center space-x-1 mt-6 rating-group"     
                                                x-data="{ hovered: 0 }"
                                                >
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <label class="cursor-pointer relative" 
                                                        @mouseover="hovered = {{ $i }}"
                                                        @mouseleave="hovered = 0"
                                                        >
                                                        <input 
                                                            type="radio" 
                                                            wire:model.live="{{ $fieldName }}" 
                                                            value="{{ $i }}" 
                                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                                        >
                                                        <span class="text-xl transition-colors duration-150 text-gray-300"
                                                            >
                                                            <svg
                                                                    class="w-12 h-12 transition-colors duration-150 "
                                                                        :class="{
                                                                            'text-yellow-400': hovered >= {{ $i }} || {{ data_get($this, $fieldName, 0) ?? 0 }} >= {{ $i }},
                                                                            'text-gray-300': hovered < {{ $i }} || {{ data_get($this, $fieldName, 0) ?? 0 }} < {{ $i }}
                                                                        }"
                                                                    fill="currentColor"
                                                                    viewBox="0 0 20 20"
                                                                >
                                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.204 3.698a1 1 0 00.95.69h3.894c.969 0 1.371 1.24.588 1.81l-3.15 2.286a1 1 0 00-.364 1.118l1.204 3.698c.3.921-.755 1.688-1.54 1.118l-3.15-2.286a1 1 0 00-1.176 0l-3.15 2.286c-.784.57-1.838-.197-1.539-1.118l1.203-3.698a1 1 0 00-.364-1.118L2.414 9.125c-.783-.57-.38-1.81.588-1.81h3.894a1 1 0 00.951-.69l1.202-3.698z"/>
                                                                </svg>
                                                        </span>
                                                    </label>
                                                @endfor
                                            </div>
                                            @break
                                        @default
                                            <p class="text-sm text-red-500">Unbekannter Fragetyp: {{ $q->type }}</p>
                                    @endswitch
                                    @error($fieldName)
                                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                                    @enderror
                                    {{-- Navigation --}}
                                    <div class="flex justify-center space-x-4 mt-12">
                                        @if ($step > 0)
                                        <x-buttons.backbutton wire:click="previousStep" />
                                        @endif
                                            <x-buttons.furtherbutton wire:click="{{ ($currentStep + 1) === $totalSteps ? 'submit' : 'nextStep' }}" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                </div>
            </div>
        </div>
    </template>
</div>
