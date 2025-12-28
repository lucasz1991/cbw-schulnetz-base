@php
    $start = $course->planned_start_date ? \Carbon\Carbon::parse($course->planned_start_date) : null;
    $end   = $course->planned_end_date   ? \Carbon\Carbon::parse($course->planned_end_date)   : null;

    $now = now();
    $isRunning = $start && $end ? $now->between($start, $end) : false;
    $isFuture  = $start ? $now->lt($start) : false;
    $isPast    = $end ? $now->gt($end) : false;

    $statusClasses = 'bg-gray-100 text-gray-700';
    $statusLabel   = $start || $end ? 'geplant' : 'ohne Zeitraum';
    if ($isRunning) { $statusClasses = 'bg-yellow-100 text-yellow-800'; $statusLabel = 'läuft'; }
    elseif ($isFuture) {
        $statusClasses = 'bg-blue-100 text-blue-800';
        $statusLabel   = $start && $start->isToday() ? 'heute' : 'bevorstehend';
    }
    elseif ($isPast) { $statusClasses = 'bg-gray-200 text-gray-600'; $statusLabel = 'beendet'; }
@endphp
<div class="mb-24">
    <x-slot name="header">
        <div class="px-4 flex items-center gap-3">
            <x-back-button />
            <span>
                Kurs im Detail 
            </span>
        </div> 
    </x-slot>  
    <div class=" space-y-12">
        <div class="bg-white p-4 border border-gray-300 rounded-lg shadow space-y-8 ">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex items-center  gap-2">    
                    <span class="inline-block {{ $statusClasses }} text-[11px] font-semibold px-2.5 py-1 rounded-full ">
                        {{ $statusLabel }}
                    </span>
                </div>
                @if($start || $end)
                <span class="inline-block bg-gray-100 text-gray-700 text-[11px] font-medium px-2.5 py-1 rounded-full">
                    {{ $start ? $start->isoFormat('ll') : '—' }} – {{ $end ? $end->isoFormat('ll') : '—' }}
                </span>
                @endif
            </div>
            <p class="text-base sm:text-md font-semibold text-gray-700 truncate">
                {{ $course->source_snapshot['course']['kurzbez'] ?: 'Ohne Kurzbezeichnung' }}
            </p>
            <h1 class="text-2xl font-bold text-gray-800">{{ $course->title }}</h1>
            @if($course->description)
            <div class="grid sm:grid-cols-2 gap-4 text-gray-700">
                <div>
                    <p class="text-sm"><strong class="text-gray-600">Beschreibung:</strong></p>
                    <p class="text-sm text-gray-600 mt-1">{{ $course->description ?: '—' }}</p>
                </div>
            </div>
            @endif
            <div class="min-w-0">
                <span class="inline-flex items-center bg-green-100 text-green-700 text-xs px-2.5 py-1 rounded-full">
                    {{ $course->dates_count ?? 0 }} Termin{{ ($course->dates_count ?? 0) === 1 ? '' : 'e' }}
                </span>
                <span class="inline-flex items-center bg-purple-100 text-purple-700 text-xs px-2.5 py-1 rounded-full">
                    {{ $course->participants_count ?? 0 }} Teilnehmer{{ ($course->participants_count ?? 0) === 1 ? '' : 'en' }}
                </span>
            </div>
        </div>
        <x-ui.accordion.tabs
            :tabs="[
                'anwesenheit' => ['label' => 'Anwesenheit',   'icon' => 'fad fa-user-clock'],
                'doku'        => ['label' => 'Dokumentation', 'icon' => 'fad fa-file-signature'],
                'medien'      => ['label' => 'Materialien',   'icon' => 'fad fa-books'],
                'results'     => ['label' => 'Ergebnisse',    'icon' => 'fad fa-poll-people'],
                'invoice'     => ['label' => 'Rechnung',      'icon' => 'fad fa-file-invoice-dollar']
            ]"
            :collapseAt="'lg'"
            default="doku"
            class="mt-8"
        >
            <x-ui.accordion.tab-panel for="anwesenheit">
                <livewire:tutor.courses.participants-table :courseId="$course->id" />
            </x-ui.accordion.tab-panel>
            <x-ui.accordion.tab-panel for="doku">
                <livewire:tutor.courses.course-documentation-panel :courseId="$course->id" />
            </x-ui.accordion.tab-panel> 
            <x-ui.accordion.tab-panel for="medien">
                <livewire:tutor.courses.manage-course-media :course="$course" lazy />
            </x-ui.accordion.tab-panel>
            <x-ui.accordion.tab-panel for="results">
                <livewire:tutor.courses.manage-course-results :course="$course" lazy />
            </x-ui.accordion.tab-panel>
            <x-ui.accordion.tab-panel for="invoice">
                <livewire:tutor.courses.manage-course-invoice :course="$course" lazy />
            </x-ui.accordion.tab-panel>
        </x-ui.accordion.tabs>
    </div>
</div>
