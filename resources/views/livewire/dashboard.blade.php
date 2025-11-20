<div class="w-full relative border-t border-t-gray-300 bg-cover bg-center bg-[#eeeeeebd] pb-20 min-h-[70vh]" wire:loading.class="cursor-wait">
  <x-ui.tabsnav.container :persistKey="false" storage-key="selectedTabdashboard" default="basic" class="w-full">
    <div class="container mx-auto px-3 md:px-5">
      <x-ui.tabsnav.nav
        :tabs="[
          'basic'      => ['label' => 'Dashboard',   'icon' => 'fad fa-tachometer-alt'],
          'reportbook' => ['label' => 'Berichtsheft','icon' => 'fad fa-book-open'],
          'claims'     => ['label' => 'Anträge',     'icon' => 'fad fa-file-invoice']
        ]"
        collapseAt="md"
      />
    </div>
    <div class="container mx-auto px-3 md:px-5">
      <x-ui.tabsnav.panel name="basic">
        <livewire:user.program-show lazy />
      </x-ui.tabsnav.panel>
      <x-ui.tabsnav.panel name="reportbook">
        <livewire:user.report-book  lazy />
      </x-ui.tabsnav.panel>
      <x-ui.tabsnav.panel name="claims">
        <livewire:user.user-requests lazy />
      </x-ui.tabsnav.panel>
    </div>
  </x-ui.tabsnav.container>
</div>
