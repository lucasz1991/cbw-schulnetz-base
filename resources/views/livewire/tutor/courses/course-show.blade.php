<div class="">
    <div class=" space-y-8 ">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h1 class="text-2xl font-bold text-gray-800">{{ $course->title }}</h1>
            <div class="text-sm text-gray-500">
                {{ $course->start_time?->format('d.m.Y') }} – {{ $course->end_time?->format('d.m.Y') }}
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4 text-gray-700">
            <div>
                <p class="text-sm"><strong class="text-gray-600">Beschreibung:</strong></p>
                <p class="text-sm text-gray-600 mt-1">{{ $course->description ?: '—' }}</p>
            </div>
        </div>
        <x-ui.accordion.tabs
                :tabs="['termine' => 'Termine', 'teilnehmer' => 'Teilnehmer', 'materialien' => 'Materialien']"
                default="termine"
                class="mt-4"
            >
                <x-ui.accordion.tab-panel for="termine">
                    <div>
                        
                        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
                        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" defer></script>
                        <livewire:tutor.courses.course-days-panel :courseId="$course->id" lazy />

                    </div>
                </x-ui.accordion.tab-panel>
                <x-ui.accordion.tab-panel for="teilnehmer">
                    <div wire:key="participants-list">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Teilnehmer ({{ $course->participants->count() }})</h2>
                        <livewire:tutor.courses.participants-table :courseId="$course->id" lazy />

                    </div>
                </x-ui.accordion.tab-panel>
                <x-ui.accordion.tab-panel for="materialien">
                    <div>
                        <livewire:tools.file-pools.manage-file-pools
                            :modelType="\App\Models\Course::class"
                            :modelId="$course->id"
                             lazy
                        />
                    </div>
                </x-ui.accordion.tab-panel>
            </x-ui.accordion.tabs>
    </div>
</div>
