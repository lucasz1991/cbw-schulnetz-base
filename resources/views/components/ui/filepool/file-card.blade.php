<div    x-data="{ isHovered: false }" 
        class="relative border border-gray-300 rounded-lg overflow-hidden bg-white shadow mb-2 "
        @mouseenter="isHovered = true" @mouseleave="isHovered = false">
    <div class="mb-2 p-1">
            <img src="{{ $file->icon_or_thumbnail }}" alt="{{ $file->name }}" class="w-full max:w-24 mx-auto aspect-video object-cover">
    </div>
    <div class="p-4 space-y-2">
        <div class="text-sm text-gray-800 truncate ">{{ $file->name }}</div>
        <div class="text-xs text-gray-500">
            {{ number_format($file->size / 1024, 1) }} KB
            @if($file->expires_at)
                | läuft ab am {{ $file->expires_at->format('d.m.Y') }}
                @if($file->isExpired())
                    <span class="text-red-500 font-semibold ml-2">(abgelaufen)</span>
                @endif
            @endif
        </div>
    </div>
    <div class="absolute inset-0 flex items-center justify-center space-x-3 bg-white bg-opacity-75 rounded-lg" x-show="isHovered" x-transition>
        <a href="{{ Storage::url($file->path) }}" target="_blank" class="text-blue-600 underline text-sm bg-gray-200 rounded-full px-2 py-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 3a1 1 0 011 1v4h4a1 1 0 110 2h-4v4a1 1 0 11-2 0v-4H6a1 1 0 110-2h4V4a1 1 0 011-1z" />
            </svg>
        </a>
        <button wire:click="deleteFile({{ $file->id }})" class="text-red-600 text-sm bg-gray-200 rounded-full px-2 py-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block" viewBox="0 0 20 20" fill="currentColor">
                <path d="M6 2a1 1 0 00-1 1v1H2a1 1 0 000 2h1v12a2 2 0 002 2h12a2 2 0 002-2V6h1a1 1 0 000-2h-3V3a1 1 0 00-1-1H6zm0 2h8v12H6V4z" />
            </svg>
        </button>
    </div>
</div>