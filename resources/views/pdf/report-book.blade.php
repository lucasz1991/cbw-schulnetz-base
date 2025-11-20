<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Berichtsheft' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1, h2, h3 { margin: 0 0 8px; }
        .course-block { page-break-after: always; }
        .entry { margin-bottom: 18px; page-break-inside: avoid; }
        .entry-header { font-weight: bold; margin-bottom: 4px; }
        .entry-status { font-size: 10px; color: #666; }
        .small { font-size: 10px; color: #444; }
    </style>
</head>
<body>

{{-- ===========================================
     MODUS: SINGLE ENTRY
=========================================== --}}
@if($mode === 'single')
    <h1>Bericht vom {{ $entry->entry_date->format('d.m.Y') }}</h1>
    <p class="small">
        Kurs: {{ $course->klassen_id ?? $course->title ?? 'Kurs' }}<br>
        Teilnehmer: {{ $user->name }}
    </p>

    <hr>

    <div class="entry">
        <div class="entry-text">
            {!! $entry->text !!}
        </div>
    </div>
@endif


{{-- ===========================================
     MODUS: MODULE (Ein ganzer Kurs)
=========================================== --}}
@if($mode === 'module')
    <h1>Berichtsheft – {{ $course->klassen_id ?? $course->title }}</h1>

    <p class="small">
        Teilnehmer: {{ $user->name }}<br>
        Zeitraum:
        @if($course->planned_start_date)
            {{ \Carbon\Carbon::parse($course->planned_start_date)->format('d.m.Y') }}
            –
            {{ $course->planned_end_date
                ? \Carbon\Carbon::parse($course->planned_end_date)->format('d.m.Y')
                : 'offen' }}
        @else
            (nicht hinterlegt)
        @endif
    </p>

    <hr>

    @foreach($entries as $entry)
        <div class="entry">
            <div class="entry-header">
                {{ $entry->entry_date->format('d.m.Y') }}
                <span class="entry-status">
                    ({{ $entry->status === 1 ? 'Fertig' : 'Entwurf' }})
                </span>
            </div>

            <div class="entry-text">
                {!! $entry->text !!}
            </div>
        </div>
    @endforeach
@endif


{{-- ===========================================
     MODUS: ALL (alle Kurse)
=========================================== --}}
@if($mode === 'all')
    <h1>Berichtsheft – Alle Kurse</h1>
    <p class="small">Teilnehmer: {{ $user->name }}</p>
    <hr>

    @foreach($books as $book)
        @php
            $course = $book->course;
        @endphp

        <div class="course-block">
            <h2>{{ $course->klassen_id ?? $course->title ?? 'Kurs #'.$course->id }}</h2>

            <p class="small">
                Zeitraum:
                @if($course->planned_start_date)
                    {{ \Carbon\Carbon::parse($course->planned_start_date)->format('d.m.Y') }}
                    –
                    {{ $course->planned_end_date
                        ? \Carbon\Carbon::parse($course->planned_end_date)->format('d.m.Y')
                        : 'offen' }}
                @else
                    (nicht hinterlegt)
                @endif
            </p>

            <hr>

            @foreach($book->entries as $entry)
                <div class="entry">
                    <div class="entry-header">
                        {{ $entry->entry_date->format('d.m.Y') }}
                        <span class="entry-status">
                            ({{ $entry->status === 1 ? 'Fertig' : 'Entwurf' }})
                        </span>
                    </div>
                    <div class="entry-text">
                        {!! $entry->text !!}
                    </div>
                </div>
            @endforeach

        </div>
    @endforeach
@endif

</body>
</html>
