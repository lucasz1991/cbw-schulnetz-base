@props([
    'id' => null,
    'maxWidth' => '2xl',
    'trapClose' => false,
])
<x-modal :id="$id" :maxWidth="$maxWidth" :trapClose="$trapClose" {{ $attributes }}>
    <div class="relative">
        <div class="px-2 md:px-6 py-2 pt-4  md:pt-5 md:py-3 bg-gray-100 border-b border-gray-300">
            <div class="text-lg font-medium text-gray-900">
                {{ $title }}
            </div>
            @if(!$trapClose)
            <button 
                type="button"
                class="absolute top-1 right-1 text-gray-500 hover:text-gray-700 transition p-1 rounded"
                @click="close()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" 
                     class="h-4 w-4"     {{-- kleiner --}}
                     viewBox="0 0 20 20" 
                     fill="currentColor">
                    <path fill-rule="evenodd" 
                          d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 
                          1.414L11.414 10l4.293 4.293a1 1 0 
                          01-1.414 1.414L10 11.414l-4.293 
                          4.293a1 1 0 01-1.414-1.414L8.586 
                          10 4.293 5.707a1 1 0 010-1.414z" 
                          clip-rule="evenodd" />
                </svg>
            </button>
            @endif
        </div>
        <div class="px-2 md:px-6 py-2 md:py-4 text-sm text-gray-600">
            {{ $content }}
        </div>
        <div class="flex flex-row justify-end px-2 md:px-6 py-2 md:py-4 bg-gray-100 text-end border-t border-gray-300">
            {{ $footer }}
        </div>
    </div>
</x-modal>