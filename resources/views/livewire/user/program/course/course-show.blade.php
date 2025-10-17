<div class="w-full relative border-t border-t-gray-300 bg-cover bg-center bg-[#eeeeeebd]" wire:loading.class="cursor-wait">
  <livewire:user.program.course.course-rating-form-modal />

  <x-ui.tabsnav.container storage-key="selectedTabcourse" default="basic" class="w-full">
    <div class="container mx-auto md:px-5">
      <x-ui.tabsnav.nav :tabs="[
        ['id'=>'basic',    'label'=>'Übersicht',   'icon'=> new \Illuminate\Support\HtmlString('<svg class=\'h-4\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\'><path stroke=\'currentColor\' stroke-width=\'2\' d=\'M15 4h3a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3m0 3h6m-3 5h3m-6 0h.01M12 16h3m-6 0h.01M10 3v4h4V3h-4Z\'/></svg>')],
        ['id'=>'doku',     'label'=>'Dokumentation', 'icon'=> new \Illuminate\Support\HtmlString('<svg class=\'h-4\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\' stroke-width=\'2\'><rect x=\'3\' y=\'4\' width=\'18\' height=\'18\' rx=\'2\' ry=\'2\'/><line x1=\'16\' y1=\'2\' x2=\'16\' y2=\'6\'/><line x1=\'8\' y1=\'2\' x2=\'8\' y2=\'6\'/><line x1=\'3\' y1=\'10\' x2=\'21\' y2=\'10\'/></svg>')],
        ['id'=>'material', 'label'=>'Materialien', 'icon'=> new \Illuminate\Support\HtmlString('<svg class=\'h-4\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z\'/></svg>')],
      ]" />
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
