<div class="p-6 container mx-auto">
    <div class="space-y-6">
        <h2 class="text-2xl font-semibold">KI-Test: Testen Sie gerne die Analyse einer einzelnen Antwort </h2>

        <h4 for="question" class="block text-sm font-medium text-gray-700">{{ $questionTitle }}</h4>
        <div>
        {{ $questionText }}
        </div>
        
        <label for="customerAnswer" class="block text-sm font-medium text-gray-700">Antwort</label>
        <textarea wire:loading.class=" disabled opacity-50 cursor-progress "  id="customerAnswer" wire:model="customerAnswer" rows="4"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
        @error('customerAnswer') 
            <span class="text-red-600 text-sm">{{ $message }}</span> 
        @enderror
    
        <div class="flex justify-between items-center">
            <button wire:click="getScore" wire:loading.attr="disabled"  wire:loading.class=" disabled opacity-50 cursor-progress "
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 focus:outline-none h-min">
                Analyse starten
            </button>
    
            <div wire:loading class="text-blue-600 text-sm ml-4">
                Lädt...
            </div>
    
        </div>
        @if ($aiResult)
        <div  wire:loading.class=" disabled opacity-50 cursor-progress " class="bg-green-100 text-sm p-4 rounded">
            <x-insurance.insurance-rating-stars :score="$aiResult" />
            <div wire:loading.class=" disabled opacity-50 cursor-progress " class="mt-4">{{ $aiResultComment }}</div>
        </div>
        @endif
        @if ($error ?? false)
            <div class="text-red-600 mt-4">{{ $error }}</div>
        @endif
    </div>
</div>
