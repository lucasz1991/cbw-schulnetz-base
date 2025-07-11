<div class="">
    @if($courses->count())
        <div class="mb-4">
            <span class="inline-block bg-blue-100 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full">
                {{ $courses->count() }} Kurs{{ $courses->count() === 1 ? '' : 'e' }} gefunden
            </span>
        </div>
    @endif
    <div class="grid gap-4">
        @forelse ($courses as $course)
            <a href="{{ route('tutor.courses.show', ['courseId' => $course->id]) }}" class="block bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition hover:bg-gray-50">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-md font-bold text-blue-700">{{ $course->title }}</h3>
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $course->description }}</p>
                        <p class="text-xs text-gray-500 mt-2">
                            {{ \Carbon\Carbon::parse($course->start_time)->isoFormat('ll') }} –
                            {{ \Carbon\Carbon::parse($course->end_time)->isoFormat('ll') }}
                        </p>
                    </div>
                    <div class="text-sm text-gray-400">
                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full">#{{ $course->id }}</span>
                    </div>
                </div>
            </a>
        @empty
            <p class="text-sm text-gray-500 col-span-2">Aktuell sind dir keine Kurse zugewiesen.</p>
        @endforelse
    </div>
</div>
