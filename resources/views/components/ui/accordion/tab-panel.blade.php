@props([
    'for' => null,
    'panelClass' => 'space-y-4 bg-white p-4 rounded-b-lg rounded-se-lg border border-blue-300 z-10',
])

<div
    x-show="openTab === '{{ $for }}'"
    x-effect="if (openTab === '{{ $for }}') { window.dispatchEvent(new CustomEvent('accordion-tab-changed', { detail: { tab: '{{ $for }}' } })) }"
    x-cloak
    wire:ignore
    role="tabpanel"
    :aria-hidden="openTab !== '{{ $for }}'"
    class="{{ $panelClass }}"
>
    {{ $slot }}
</div>
