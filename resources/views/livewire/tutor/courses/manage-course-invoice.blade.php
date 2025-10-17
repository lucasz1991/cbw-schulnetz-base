<div class="space-y-4 pt-6">
    @if(!$invoice)
      <x-alert class="bg-yellow-50 border border-yellow-800 text-yellow-800 rounded mb-6">
        <p class="text-sm leading-snug">
          Sobald alle Kursinhalte erfasst sind – z.&nbsp;B. <span class="font-medium">Roter Faden</span> und 
          <span class="font-medium">Testergebnisse</span> – können Sie hier die <span class="font-medium">Rechnung</span> für diesen Kurs speichern.
        </p>
      </x-alert>
    @endif
  @if($invoice)
    <div class="md:flex items-center flex-wrap justify-between rounded border p-3 bg-white ">
        <div class="text-base max-md:mb-4 flex items-center gap-2">
            <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center">
                <img class="w-12 h-12 object-contain"
                    src="{{ $invoice->icon_or_thumbnail }}"
                    alt="Datei-Icon">
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-medium truncate">
                    {{ $invoice->name }}
                </div>
                <div class="font-medium text-xs text-gray-500">
                    {{ $invoice->size_formatted }}
                </div>
            </div>
        </div>
        <div class="flex items-center flex-wrap gap-2">
        <x-buttons.btn-group.btn-group>
            {{-- Öffnen (disabled falls keine Rechnung vorhanden) --}}
            <x-buttons.btn-group.btn-group-item
                href="{{ $invoice?->getEphemeralPublicUrl(10) }}"
                target="_blank"
                :disabled="!$invoice"
            >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Öffnen
            </x-buttons.btn-group.btn-group-item>

            {{-- Entfernen (disabled falls keine Rechnung vorhanden) --}}
            <x-buttons.btn-group.btn-group-item
                wire:click="removeInvoice"
                :disabled="!$invoice"
                class="text-red-600 hover:text-red-700"
            >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                <line x1="10" y1="11" x2="10" y2="17"/>
                <line x1="14" y1="11" x2="14" y2="17"/>
            </svg>
            Entfernen
            </x-buttons.btn-group.btn-group-item>

            {{-- Ersetzen / Hochladen (öffnet dein Upload-Modal) --}}
            <x-buttons.btn-group.btn-group-item
                wire:click="$set('openInvoiceForm', true)"
            >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>
            </svg>
            Ersetzen
            </x-buttons.btn-group.btn-group-item>
        </x-buttons.btn-group.btn-group>
        </div>

    </div>
  @else
    <div class="flex items-center justify-between">
        <p class="text-gray-600 text-sm">Es ist noch keine Rechnung hinterlegt.</p>
        <button  wire:click="$toggle('openInvoiceForm')"
                class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-sm">
        PDF hochladen
        </button>
    </div>
  @endif
  <x-dialog-modal wire:model="openInvoiceForm">
    <x-slot name="title">Rechnung (PDF) hochladen</x-slot>

    <x-slot name="content">
      <x-ui.filepool.drop-zone :model="'invoiceUpload'" mode="single" acceptedFiles=".pdf" :maxFilesize="30" />

      @error('invoiceUpload')
        <span class="text-sm text-red-600">{{ $message }}</span>
      @enderror
    </x-slot>

    <x-slot name="footer">
      <div class="flex justify-end gap-2">
        @if(!empty($invoiceUpload)) 
            <x-button
            wire:click="uploadInvoice"
            wire:loading.attr="disabled"
            wire:target="invoiceUpload,uploadInvoice"
            >
            Hochladen
            </x-button>
        @endif

        <x-button wire:click="$toggle('openInvoiceForm')" class="!bg-gray-200 text-gray-800">
          Abbrechen
        </x-button>
      </div>
    </x-slot>
  </x-dialog-modal>
</div>
