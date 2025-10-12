@props([
    'for' => null, // Tab-Key, z. B. 'basic'
    'panelClass' => 'space-y-4 bg-sky-50 p-4 rounded-b-lg rounded-se-lg border border-sky-300 z-10',
])

<div
    x-show="openTab === '{{ $for }}'"
    x-cloak
    wire:ignore
    role="tabpanel"
    :aria-hidden="openTab !== '{{ $for }}'"
    class="{{ $panelClass }}"
>
    {{ $slot }}
</div>
