
<div class="space-y-4">
    {{-- Filter + PerPage --}}
    <div class="flex items-center justify-between">
            <x-ui.lists.search-field 
                resultsCount="{{ $participants->count() }}"
                wire:model.live="search"
            />

        <select wire:model.live="perPage" class="border rounded px-2 py-1 pr-8 text-sm ">
            <option value="10">10 / Seite</option>
            <option value="15">15 / Seite</option>
            <option value="25">25 / Seite</option>
            <option value="50">50 / Seite</option>
        </select>
    </div>

    {{-- Tabelle --}}
    <div class="overflow-x-auto border rounded">
        <table class="bg-white min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">
                        <button wire:click="sort('name')" class="font-semibold">
                            Name
                            @if($sortBy === 'name')
                                {{ $sortDir === 'asc' ? '▲' : '▼' }}
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-2 text-left">
                        <button wire:click="sort('email')" class="font-semibold">
                            Email
                            @if($sortBy === 'email')
                                {{ $sortDir === 'asc' ? '▲' : '▼' }}
                            @endif
                        </button>
                    </th>
                    <th class="w-6"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($participants as $p)
                    <tr>
                        <td class="px-4 py-2"><x-user.public-info :person="$p" /></td>
                        <td class="px-4 py-2">{{ $p->email_priv }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('tutor.participants.show', $p) }}"  class="text-blue-600 hover:underline" wire:navigate>
                                Öffnen
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-4 text-center text-gray-500">
                            Keine Teilnehmer gefunden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div>
        {{ $participants->onEachSide(1)->links() }}
    </div>
</div>
