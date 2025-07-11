<div class="p-6 bg-gray-100 min-h-screen">
    <div class="container mx-auto">
        <h1 class="text-2xl font-bold mb-4 text-gray-800">Meine Kurse</h1>

        <input type="text"
               wire:model.debounce.500ms="search"
               placeholder="🔍 Suche nach Kursen..."
               class="w-full p-3 mb-6 border rounded-lg focus:ring focus:ring-primary-400"/>

        @forelse($courses as $course)
            <div class="bg-white rounded-lg shadow mb-4 p-4 border border-gray-200">
                <h2 class="text-lg font-semibold text-primary-700">{{ $course->title }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ Str::limit($course->description, 100) }}</p>

                <div class="text-xs text-gray-500 mt-2">
                    📅 {{ optional($course->start_time)->format('d.m.Y') }} – {{ optional($course->end_time)->format('d.m.Y') }}
                    @if($course->days_count)
                        | 🗓️ {{ $course->days_count }} Unterrichtstage
                    @endif
                </div>
            </div>
        @empty
            <p class="text-gray-500">Keine Kurse gefunden.</p>
        @endforelse
    </div>
</div>
