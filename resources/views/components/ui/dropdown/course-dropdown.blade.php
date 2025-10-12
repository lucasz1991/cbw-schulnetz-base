@props(['group','itemId'])

<div x-data>
  <div class="rounded-lg overflow-hidden">
    <button
      @click="$dispatch('accordion-toggle', { group: '{{ $group }}', id: '{{ $itemId }}' })"
      class="w-full px-4 py-2 flex justify-start items-center space-x-3 bg-sky-600 text-white"
    >
      <svg xmlns="http://www.w3.org/2000/svg"
           :class="{ 'rotate-180': activeId === '{{ $itemId }}' }"
           class="w-5 h-5 transition-transform transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
      <span class="font-semibold">{{ $trigger }}</span>
    </button>

    <div x-show="activeId === '{{ $itemId }}'" x-cloak x-collapse.duration.400ms class="overflow-hidden">
      <div class="p-3 pt-6 bg-white px-4 py-2 border rounded-b-lg border-sky-600">
        {{ $content }}
      </div>
    </div>
  </div>

  {{-- Falls nichts persistiert ist (erste Seite), öffnet „Roter Faden“ genau einmal --}}
  <div x-init="
    if (!activeId && '{{ $itemId }}' === 'roter-faden') {
      $dispatch('accordion-set', { group: '{{ $group }}', id: 'roter-faden' })
    }
  "></div>
</div>
