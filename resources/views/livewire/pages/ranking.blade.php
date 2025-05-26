<div class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">

        {{-- TOP 5 SWIPER --}}
        <div class="my-12">
            <h2 class="text-xl font-bold mb-4 text-green-900">Top 5 Versicherungen</h2>

            <div x-data="{
                swiper: null,
                initSwiper() {
                    this.swiper = new Swiper(this.$refs.topSwiper, {
                        slidesPerView: 1,
                        spaceBetween: 20,
                        autoplay: {
                            delay: 1300,
                        },
                        speed: 2500,
                        loop: true,
                        pagination: {
                            el: '.swiper-pagination-top',
                            clickable: true,
                        },
                        breakpoints: {
                            640: { slidesPerView: 2 },
                            1024: { slidesPerView: 3 },
                        }
                    });
                }
            }"
            x-init="initSwiper()"
            class="relative"
            wire:ignore>
                <div class="swiper" x-ref="topSwiper">
                    <div class="swiper-wrapper">
                        @foreach ($top5 as $insurance)
                            <div class="swiper-slide">
                                <x-insurance.insurance-card :insurance="$insurance" />
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- FLOP 5 SWIPER --}}
        <div class="my-12">
            <h2 class="text-xl font-bold mb-4 text-red-900">Flop 5 Versicherungen</h2>

            <div x-data="{
                swiper: null,
                initSwiper() {
                    this.swiper = new Swiper(this.$refs.flopSwiper, {
                        slidesPerView: 1,
                        spaceBetween: 20,
                        autoplay: {
                            delay: 1500,
                        },
                        speed: 2500,
                        loop: true,
                        pagination: {
                            el: '.swiper-pagination-flop',
                            clickable: true,
                        },
                        breakpoints: {
                            640: { slidesPerView: 2 },
                            1024: { slidesPerView: 3 },
                        }
                    });
                }
            }"
            x-init="initSwiper()"
            class="relative"
            wire:ignore>
                <div class="swiper" x-ref="flopSwiper">
                    <div class="swiper-wrapper">
                        @foreach ($flop5 as $insurance)
                            <div class="swiper-slide">
                                <x-insurance.insurance-card :insurance="$insurance" />
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        {{-- Gesamtranking --}}
        <div class="mt-12">
            <h2 class="text-2xl font-bold mb-4 text-gray-900">Gesamtranking</h2>
            <div class="flex flex-col space-y-4">
                @foreach ($allInsurances as $insurance)
                    <x-insurance.insurance-card :insurance="$insurance" />
                @endforeach
            </div>
        </div>
    </div>
</div>
