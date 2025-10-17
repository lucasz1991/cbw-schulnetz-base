<div class="w-full relative border-t border-t-gray-300 bg-cover bg-center bg-[#eeeeeebd] pb-20 min-h-[70vh]" wire:loading.class="cursor-wait">
  <x-ui.tabsnav.container storage-key="selectedTabdashboard" default="basic" class="w-full">
    <div class="container mx-auto md:px-5">
      <x-ui.tabsnav.nav :tabs="[
        ['id'=>'basic',  'label'=>'Dashboard', 'icon'=> new \Illuminate\Support\HtmlString('<svg class=\'h-4\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\'><path stroke=\'currentColor\' stroke-width=\'2\' d=\'M15 4h3a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3m0 3h6m-3 5h3m-6 0h.01M12 16h3m-6 0h.01M10 3v4h4V3h-4Z\'/></svg>')],
        ['id'=>'claims', 'label'=>'Anträge',   'icon'=> new \Illuminate\Support\HtmlString('<svg class=\'h-4\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\' stroke-width=\'2\'><rect x=\'3\' y=\'4\' width=\'18\' height=\'18\' rx=\'2\' ry=\'2\'/><line x1=\'16\' y1=\'2\' x2=\'16\' y2=\'6\'/><line x1=\'8\' y1=\'2\' x2=\'8\' y2=\'6\'/><line x1=\'3\' y1=\'10\' x2=\'21\' y2=\'10\'/></svg>')],
      ]" />
    </div>

    <div class="container mx-auto px-5">
      <x-ui.tabsnav.panel name="basic">
        <livewire:user.program-show lazy />
      </x-ui.tabsnav.panel>

      <x-ui.tabsnav.panel name="claims">
        <livewire:user.user-requests lazy />
      </x-ui.tabsnav.panel>
    </div>
  </x-ui.tabsnav.container>
</div>
