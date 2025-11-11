@props(['item'])

<x-dropdown align="right" width="48">
    <x-slot name="trigger">
        <button type="button" class="text-center px-4 py-2 text-xl font-semibold bg-white hover:bg-gray-100 rounded-lg border border-gray-200">
            &#x22EE;
        </button>
    </x-slot>

    <x-slot name="content">
        {{-- Anzeigen --}}
        <x-dropdown-link href="javascript:void(0)"
                         class="!text-blue-600"
                         wire:click="showMessage({{ $item->id }})">
            <i class="far fa-eye mr-2"></i>
            Anzeigen
        </x-dropdown-link>

        {{-- Nur Beispiel: „Als gelesen markieren“ falls ungelesen --}}
        @if((int)($item->status ?? 0) === 1)
            <x-dropdown-link href="javascript:void(0)"
                             class="!text-gray-700"
                             wire:click="markAsRead({{ $item->id }})">
                <i class="far fa-envelope-open mr-2"></i>
                Als gelesen markieren
            </x-dropdown-link>
        @endif

        {{-- Löschen --}}
        <x-dropdown-link href="javascript:void(0)"
                         class="!text-red-600"
                         wire:click="deleteMessage({{ $item->id }})">
            <i class="far fa-trash-alt mr-2"></i>
            Löschen
        </x-dropdown-link>
    </x-slot>
</x-dropdown>
