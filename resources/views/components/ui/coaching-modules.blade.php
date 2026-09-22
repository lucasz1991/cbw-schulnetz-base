@if(\App\Services\Coaching\Access::available())
    @php($coachingModules = \App\Models\CoachingContract::whereIn('participant_person_id', auth()->user()->persons()->pluck('persons.id'))->with('course')->orderByDesc('id')->get())
    @if($coachingModules->isNotEmpty())
    <section class="bg-white border border-gray-200 rounded-lg p-4 mb-6 space-y-3">
        <h2 class="text-xl font-semibold text-gray-700">Meine Einzelcoaching-Bausteine</h2>
        @foreach($coachingModules as $coaching)
        <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-3"><div><strong>{{ $coaching->title }}</strong><p class="text-sm text-gray-500">{{ $coaching->planning_label }}</p></div>
            <x-buttons.button-basic size="sm" href="{{ $coaching->course ? route('user.program.course.show', ['klassenId' => $coaching->course->klassen_id]) : route('coaching.planning') }}">{{ $coaching->course ? 'Baustein öffnen' : 'Termine abstimmen' }}</x-buttons.button-basic>
        </div>
        @endforeach
    </section>
    @endif
@endif
