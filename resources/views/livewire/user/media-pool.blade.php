<div>
        {{-- Hinweis-Alert --}}
        <div class="mb-4">
            <x-alert type="info">
                <x-slot name="title">Medienpool</x-slot>
                Hier kannst du deine persönlichen Dateien verwalten. Lade neue Dateien hoch oder lade bestehende herunter.
            </x-alert>
        </div>
        <div class="p-4 border border-gray-200 rounded-md bg-gray-50 shadow-sm">            
            <livewire:tools.file-pools.manage-file-pools
                :modelType="\App\Models\User::class"
                :modelId="auth()->user()->id"
                 lazy
            />
        </div>
</div>
