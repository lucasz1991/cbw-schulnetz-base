@props(['course' => null, 'display' => null, 'showOwnRole' => false, 'showCurrent' => true, 'showReasons' => false])
@php
    $lecturers = $display ?? ($course ? \App\Services\Courses\CourseLecturerAccess::display($course) : []);
    $current = $lecturers['current'] ?? null;
    $fmt = fn($date) => \Carbon\Carbon::parse($date)->format('d.m.Y');
@endphp
@if($lecturers['conflict'] ?? false)
    <p class="my-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">Die Dozentenzuständigkeit wird geklärt. Bearbeitungsfunktionen sind vorübergehend eingeschränkt.</p>
@elseif($lecturers['multiple'] ?? false)
    <div class="my-3 rounded-lg border border-gray-200 px-3 py-2 text-sm">
        @if($current && $showCurrent)
            <p class="font-medium">Hauptdozent: {{ $current['name'] }}</p>
            <p class="text-xs text-gray-500">{{ $fmt($current['starts_on']) }} – {{ $fmt($current['ends_on']) }}</p>
        @endif
        @if($showOwnRole && $course && !\App\Services\Courses\CourseLecturerAccess::canManageCourse(auth()->user(),$course))
            <p class="mt-1 text-xs text-gray-600">Sie sind derzeit nicht der Hauptdozent. Der Kurs ist für Sie lesbar. Eigene Rechnungen können Sie nach Beginn Ihres Einsatzes einreichen.</p>
        @endif
        @foreach(($lecturers['previous'] ?? []) as $row)
            @if(($row['assignment_id'] ?? null) !== ($current['assignment_id'] ?? null))
                <p class="mt-1 text-xs text-gray-600">{{ $row['name'] }} · früherer Hauptdozent · {{ $fmt($row['starts_on']) }} – {{ $fmt($row['ends_on']) }}</p>
            @endif
        @endforeach
        @foreach(($lecturers['planned'] ?? []) as $row)
            @if(($row['assignment_id'] ?? null) !== ($current['assignment_id'] ?? null))
                <p class="mt-1 text-xs text-gray-600">{{ $row['name'] }} · geplant ab {{ $fmt($row['starts_on']) }}</p>
            @endif
        @endforeach
        @if($showReasons)
            @foreach(($lecturers['assignments'] ?? []) as $row)
                @if(!empty($row['reason']))
                    <p class="mt-1 text-xs text-gray-500">Wechsel ab {{ $fmt($row['starts_on']) }}: {{ $row['reason'] }}</p>
                @endif
            @endforeach
        @endif
    </div>
@endif
