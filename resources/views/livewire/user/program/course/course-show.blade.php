<div
  class="bg-slate-50"
  @if($courseLoading)
    wire:poll.500ms="pollCourse"
  @endif
>
@if(! $course)
  <section class="container mx-auto flex min-h-[60vh] items-center justify-center px-4 py-10 sm:px-6">
    @if($courseLoading)
      <div
        class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white px-5 py-8 text-center shadow-sm sm:px-10 sm:py-12"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        aria-busy="true"
        x-data="{
          showLoadingMessage: false,
          loadingMessageTimer: null,
          init() {
            const elapsed = Math.max(0, Date.now() - @js($courseLoadingStartedAtMs));
            if (elapsed >= 3000) {
              this.showLoadingMessage = true;
              return;
            }

            this.loadingMessageTimer = setTimeout(() => {
              this.showLoadingMessage = true;
            }, 3000 - elapsed);
          },
          destroy() {
            clearTimeout(this.loadingMessageTimer);
          }
        }"
      >
        <svg
          class="mx-auto h-12 w-12 animate-spin text-blue-600 motion-reduce:animate-none sm:h-14 sm:w-14"
          viewBox="0 0 24 24"
          fill="none"
          aria-hidden="true"
        >
          <circle class="opacity-20" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
          <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
        </svg>
        <span class="sr-only">Baustein wird geladen.</span>

        <p class="mt-5 text-sm font-semibold text-slate-800 sm:text-base">
          {{ $pendingCourseTitle }} wird vorbereitet
        </p>
        <p
          x-cloak
          x-show="showLoadingMessage"
          class="mt-2 text-sm text-slate-600"
        >
          Seite wird geladen...
        </p>
      </div>
    @else
      <div
        class="w-full max-w-lg rounded-3xl border border-amber-200 bg-white px-5 py-8 text-center shadow-sm sm:px-10 sm:py-12"
        role="alert"
      >
        <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700" aria-hidden="true">
          <i class="fal fa-clock"></i>
        </span>
        <h1 class="mt-5 text-xl font-semibold text-slate-900 sm:text-2xl">Baustein noch nicht verfügbar</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">
          Die Synchronisierung von „{{ $pendingCourseTitle }}“ dauert etwas länger. Bitte versuche es erneut oder kehre zur Übersicht zurück.
        </p>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
          <a
            href="{{ route('dashboard') }}"
            wire:navigate
            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
          >
            Abbrechen
          </a>
          <button
            type="button"
            wire:click="retryCourseLoad"
            wire:loading.attr="disabled"
            wire:target="retryCourseLoad"
            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60"
          >
            <span wire:loading.remove wire:target="retryCourseLoad">Erneut laden</span>
            <span wire:loading wire:target="retryCourseLoad">Wird geladen...</span>
          </button>
        </div>
      </div>
    @endif
  </section>
@else
<header class="relative bg-cover bg-center min-h-36  md:px-8 " style="background-image: url('{{ asset('site-images/bg_footer.jpg') }}');">
  <div class="absolute inset-0 bg-white opacity-60"></div>
  <div class="relative container mx-auto px-5 pb-12 pt-8 text-xl  space-x-6 flex justify-start  items-center">
      <a href="/user/dashboard" wire:navigate class="shadow transition-all duration-100 inline-flex items-center content-center px-2 py-1 text-sm border border-blue-300 bg-white text-gray-600 rounded-full aspect-square hover:bg-blue-200 cursor-pointer waves-effect" x-data="{ isClicked: false }" @click="isClicked = true; setTimeout(() =&gt; isClicked = false, 100)" style="" :style="isClicked ? 'transform:scale(0.7);' : ''">
          <i class="fal fa-arrow-left"></i>
      </a>
      <h1 class=" text-xl text-gray-800 leading-tight flex items-center">
          Baustein im Detail
      </h1>
  </div>   
</header>
<div class="w-full relative border-t border-t-gray-300 min-h-[400px]" wire:loading.class="cursor-wait">
  <x-ui.tabsnav.container storage-key="selectedTabcourse-{{ $course->id }}" default="basic" class="w-full min-h-[400px]">
    <div class="container mx-auto px-3 md:px-5 min-h-0 h-0">
      <x-ui.tabsnav.nav
      :tabs="[
          'basic'    => ['label' => 'Übersicht',      'icon' => 'fad fa-tachometer-alt'],
          'doku'     => ['label' => 'Dokumentation',  'icon' => 'fad fa-file-alt'],
          'material' => ['label' => 'Materialien',    'icon' => 'fad fa-books'],
          ]"
        collapseAt="md"
        />
      </div>
      <x-ui.tabsnav.panel name="basic">
        <livewire:user.program.course.course-show-overview
        :klassen-id="$klassenId ?? ($courseArray['klassen_id'] ?? null)"
        :key="'overview-'.$klassenId"
        lazy
        />
      </x-ui.tabsnav.panel>
      <x-ui.tabsnav.panel name="doku">
        <livewire:user.program.course.course-show-doku :course-id="$course->id" lazy />
      </x-ui.tabsnav.panel>
      <x-ui.tabsnav.panel name="material">
        <livewire:user.program.course.course-show-media :course="$course" lazy />
      </x-ui.tabsnav.panel>
    </x-ui.tabsnav.container>
  </div>
  <livewire:user.program.course.courses-slider :klassenId="$course->klassen_id" />
  <livewire:user.program.course.course-rating-form-modal />
@endif
</div>
