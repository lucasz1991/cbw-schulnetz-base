<div  wire:loading.class="cursor-wait">
  <section class="relative home-banner  grid content-center overflow-hidden" >
    <img  class="h-full w-full !object-center !object-cover absolute" src="/site-images/background.jpg" alt="">
    <div class="absolute inset-0 bg-blue-100 opacity-70">
    </div>
    <div class="max-w-4xl mx-auto text-center space-y-4 md:py-6 bg-white/30 rounded-xl  px-6 md:px-12 relative h-max z-10">
      <h2 class="text-3xl md:text-4xl  text-gray-800 mb-4 mt-5">
        Bewerte deine Versicherung
      </h2>
      <p class="text-xl md:text-2xl text-gray-700 pb-8">
        fair, anonym und öffentlich. Erfahre, wie schnell und gerecht andere Kunden entschädigt wurden. Gemeinsam schaffen wir Transparenz im Versicherungsdschungel.
      </p>
      <div>
        <div class="flex space-x-4 items-center justify-center mb-4 w-full">
            <livewire:customer.rating.rating-form lazy />
            <x-buttons.button-basic href="/insurances" :mode="'success'">
                Erfahren
            </x-buttons.button-basic>
        </div>
      </div>
    </div>
  </section>
  <section>
    <livewire:banner.homepage-claimratings-random-banner />
  </section>
  <livewire:testphase.modal-notice lazy />
</div>
