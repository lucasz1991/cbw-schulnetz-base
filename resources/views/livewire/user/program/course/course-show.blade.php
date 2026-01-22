<div>
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
<div class="w-full relative border-t border-t-gray-300 bg-cover bg-center bg-slate-50" wire:loading.class="cursor-wait">
  <livewire:user.program.course.course-rating-form-modal />
  <x-ui.tabsnav.container storage-key="selectedTabcourse-{{ $course->id }}" default="basic" class="w-full">
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

<livewire:user.program.course.courses-slider :klassenId="$course->klassen_id" lazy />

  
</div>
