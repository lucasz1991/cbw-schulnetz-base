<div class="w-full relative border-t border-t-gray-300 bg-cover bg-center bg-[#eeeeeebd]" wire:loading.class="cursor-wait">
  <livewire:user.program.course.course-rating-form-modal />

  <x-ui.tabsnav.container storage-key="selectedTabcourse" default="basic" class="w-full">
    <div class="container mx-auto px-3 md:px-5">
      <x-ui.tabsnav.nav
        :tabs="[
          'basic'    => ['label' => 'Übersicht',      'icon' => 'fad fa-tachometer-alt'],
          'doku'     => ['label' => 'Dokumentation',  'icon' => 'fad fa-file-alt'],
          'material' => ['label' => 'Materialien',    'icon' => 'fad fa-books'],
        ]"
        collapseAt="md"
      />
    </div>

    <div>
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
    </div>
  </x-ui.tabsnav.container>
</div>
