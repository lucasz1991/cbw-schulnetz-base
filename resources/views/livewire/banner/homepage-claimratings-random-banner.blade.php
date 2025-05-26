<div class="relative bg-primary-50 overflow-hidden py-4">
    <div x-data="{
            swiperclaimRatings: null,
            initSwiperswiperclaimRatings() {
                this.swiperclaimRatings = new Swiper(this.$refs.swiperclaimRatings, {
                    autoplay: {
                        delay: 0,
                    },
                    disableOnInteraction: true,
                    speed: 5000,
                    loop: true,
                    centeredSlides: true,
                    slidesPerView: '1',
                    spaceBetween: 20,
                    disableOnMobile: true,
                    breakpoints: {
                        1000: {
                            slidesPerView: 2,
                        },
                        1200: {
                            slidesPerView: 3,
                        },
                        1400: {
                            slidesPerView: 4,
                        },
                    },
                    
                });
                this.swiperclaimRatings.slideNext();
            },
            stopSwiperswiperclaimRatings() {
                this.swiperclaimRatings.autoplay.stop();
            },
            startSwiperswiperclaimRatings() {
                this.swiperclaimRatings?.autoplay?.start();
            }
        }"
        x-init="initSwiperswiperclaimRatings() "
        x-on:click="this.swiperclaimRatings.stopSwiperswiperclaimRatings(); setTimeout(() => this.swiperclaimRatings.startSwiperswiperclaimRatings(), 5000)"

        class=" relative w-full"
        wire:ignore
    >
        {{-- Navigation links/rechts außerhalb --}}
        
        <div class="swiper w-full  overflow-visible" x-ref="swiperclaimRatings" >
            <div class="swiper-wrapper  !ease-linear">
                @foreach ($claimRatings as $claimRating)
                    <div class="swiper-slide h-full px-4">
                        <x-claim-rating.claim-rating-card :rating="$claimRating" />
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
