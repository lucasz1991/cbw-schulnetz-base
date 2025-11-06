@props(['item'])

<x-dropdown align="right" width="48">
    <x-slot name="trigger">
        <button type="button" class="text-center px-4 py-2 text-xl font-semibold hover:bg-gray-100 rounded-lg">
            &#x22EE;
        </button>
    </x-slot>

    <x-slot name="content">
        {{-- Öffnen (nutzt dein bestehendes Event im Parent-Component) --}}
        <x-dropdown-link href="javascript:void(0)"
                        class="!text-blue-600"
                         wire:click="$dispatch('open-request-form-edit', [ { id: {{ $item->id }} } ])">
            <i class="mr-2 fa fa-info-circle "></i>
            Details
        </x-dropdown-link>

        @if($item->status === 'pending')
            <x-dropdown-link href="javascript:void(0)"
            class="text-yellow-600"
                             wire:click="cancel({{ $item->id }})">
                <i class="mr-2 fa fa-times-circle "></i>
                Stornieren
            </x-dropdown-link>
        @endif

        <x-dropdown-link href="javascript:void(0)"
                         class="text-red-600"
                         wire:click="delete({{ $item->id }})">
                         <i class="mr-2 fa fa-times-circle"></i>
            Löschen
        </x-dropdown-link>
    </x-slot>
</x-dropdown>
