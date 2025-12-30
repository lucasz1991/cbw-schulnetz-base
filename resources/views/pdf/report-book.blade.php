<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Berichtsheft' }}</title>

    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color:#111; }
        h1, h2 { margin: 0 0 8px; padding:0; }
        hr { border:0; border-top:1px solid #ddd; margin: 10px 0 14px; }

        .meta { font-size: 10px; color: #444; margin-bottom: 6px; line-height: 1.35; }
        .course-block { page-break-after: always; padding-bottom: 6px; }
        .course-block:last-child { page-break-after: auto; }

        .entry { margin-bottom: 14px; page-break-inside: avoid; }
        .entry-header { font-weight: bold; margin-bottom: 4px; }
        .entry-text { line-height: 1.35; }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 9px;
            border: 1px solid #ddd;
            color: #444;
            vertical-align: middle;
            margin-left: 6px;
        }
        .badge-fertig { border-color: #b7e3c6; color:#14532d; background:#ecfdf5; }
        .badge-entwurf { border-color: #fde68a; color:#92400e; background:#fffbeb; }

        .signature-block { margin-top: 26px; page-break-inside: avoid; }
        .sig-col {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            text-align: center;
        }
        .sig-col.right { float: right; }
        .sig-img { max-height: 90px; margin: 0 0 6px; }
        .sig-line {
            border-top: 1px solid #000;
            margin-top: 8px;
            padding-top: 4px;
            font-size: 10px;
            line-height: 1.25;
        }
        .sig-label { color:#444; font-size: 9px; margin-top: 2px; }

        .clearfix:after { content:""; display:block; clear:both; }
    </style>
</head>
<body>

@php
    /**
     * DomPDF ist mit lokalen absoluten Pfaden am stabilsten.
     * getEphemeralPublicUrl() liefert z.B.:
     *   /storage/temp/xyz.png  (oder vollqualifiziert)
     * Wir mappen das auf:
     *   public_path('storage/temp/xyz.png')
     */
    $ephemeralToPublicPath = function (?string $url) {
        if (!$url) return null;

        $path = parse_url($url, PHP_URL_PATH) ?: $url;  // nur "/storage/..."
        if (!is_string($path) || $path === '') return null;

        if (!str_starts_with($path, '/storage/')) {
            return null; // Sicherheitsnetz: wir erwarten storage-link Pfade
        }

        $relative = ltrim($path, '/');      // "storage/temp/.."
        $full = public_path($relative);     // ".../public/storage/temp/.."

        return is_file($full) ? $full : null;
    };
@endphp


{{-- =========================================================
    MODUS: SINGLE
========================================================= --}}
@if(($mode ?? null) === 'single')
    <h1>Bericht vom {{ $entry->entry_date->format('d.m.Y') }}</h1>

    <div class="meta">
        Kurs: {{ $course->klassen_id ?? $course->title ?? 'Kurs' }}<br>
        Teilnehmer: {{ $user->name }}
    </div>

    <hr>

    <div class="entry">
        <div class="entry-text">
            {!! $entry->text !!}
        </div>
    </div>

    @php
        $pUrl = $participantSignatureUrl ?? null;
        $tUrl = $trainerSignatureUrl ?? null;
        $pImg = $ephemeralToPublicPath($pUrl);
        $tImg = $ephemeralToPublicPath($tUrl);
    @endphp

    @if($pImg || $tImg)
        <div class="signature-block clearfix">
            <div class="sig-col">
                @if($pImg)
                    <img class="sig-img" src="{{ $pImg }}" alt="Unterschrift Teilnehmer">
                @endif
                <div class="sig-line">
                    Unterschrift Teilnehmer
                    <div class="sig-label">{{ $user->name }}</div>
                </div>
            </div>

            <div class="sig-col right">
                @if($tImg)
                    <img class="sig-img" src="{{ $tImg }}" alt="Unterschrift Ausbilder">
                @endif
                <div class="sig-line">Unterschrift Ausbilder</div>
            </div>
        </div>
    @endif
@endif


{{-- =========================================================
    MODUS: MODULE
========================================================= --}}
@if(($mode ?? null) === 'module')
    <h1>Berichtsheft – {{ $course->klassen_id ?? $course->title }}</h1>

    <div class="meta">
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
    </div>

    <hr>

    @foreach($entries as $entry)
        @php $isFinished = ((int)($entry->status ?? 0) >= 1); @endphp

        <div class="entry">
            <div class="entry-header">
                {{ $entry->entry_date->format('d.m.Y') }}
                <span class="badge {{ $isFinished ? 'badge-fertig' : 'badge-entwurf' }}">
                    {{ $isFinished ? 'Fertig' : 'Entwurf' }}
                </span>
            </div>

            <div class="entry-text">
                {!! $entry->text !!}
            </div>
        </div>
    @endforeach

    @php
        $pUrl = $participantSignatureUrl ?? null;
        $tUrl = $trainerSignatureUrl ?? null;
        $pImg = $ephemeralToPublicPath($pUrl);
        $tImg = $ephemeralToPublicPath($tUrl);
    @endphp

    @if($pImg || $tImg)
        <div class="signature-block clearfix">
            <div class="sig-col">
                @if($pImg)
                    <img class="sig-img" src="{{ $pImg }}" alt="Unterschrift Teilnehmer">
                @endif
                <div class="sig-line">
                    Unterschrift Teilnehmer
                    <div class="sig-label">{{ $user->name }}</div>
                </div>
            </div>

            <div class="sig-col right">
                @if($tImg)
                    <img class="sig-img" src="{{ $tImg }}" alt="Unterschrift Ausbilder">
                @endif
                <div class="sig-line">Unterschrift Ausbilder</div>
            </div>
        </div>
    @endif
@endif


{{-- =========================================================
    MODUS: ALL
========================================================= --}}
@if(($mode ?? null) === 'all')
    <h1>Berichtsheft – Alle Kurse</h1>
    <div class="meta">Teilnehmer: {{ $user->name }}</div>
    <hr>

    @foreach($books as $book)
        @php
            $course = $book->course;

            $pUrl = $book->participantSignatureUrl ?? null;
            $tUrl = $book->trainerSignatureUrl ?? null;

            $pImg = $ephemeralToPublicPath($pUrl);
            $tImg = $ephemeralToPublicPath($tUrl);
        @endphp

        <div class="course-block">
            <h2>{{ $course->klassen_id ?? $course->title ?? 'Kurs #'.$course->id }}</h2>

            <div class="meta">
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
            </div>

            <hr>

            @foreach($book->entries as $entry)
                @php $isFinished = ((int)($entry->status ?? 0) >= 1); @endphp

                <div class="entry">
                    <div class="entry-header">
                        {{ $entry->entry_date->format('d.m.Y') }}
                        <span class="badge {{ $isFinished ? 'badge-fertig' : 'badge-entwurf' }}">
                            {{ $isFinished ? 'Fertig' : 'Entwurf' }}
                        </span>
                    </div>
                    <div class="entry-text">
                        {!! $entry->text !!}
                    </div>
                </div>
            @endforeach

            @if($pImg || $tImg)
                <div class="signature-block clearfix">
                    <div class="sig-col">
                        @if($pImg)
                            <img class="sig-img" src="{{ $pImg }}" alt="Unterschrift Teilnehmer">
                        @endif
                        <div class="sig-line">
                            Unterschrift Teilnehmer
                            <div class="sig-label">{{ $user->name }}</div>
                        </div>
                    </div>

                    <div class="sig-col right">
                        @if($tImg)
                            <img class="sig-img" src="{{ $tImg }}" alt="Unterschrift Ausbilder">
                        @endif
                        <div class="sig-line">Unterschrift Ausbilder</div>
                    </div>
                </div>
            @endif
        </div>
    @endforeach
@endif

</body>
</html>
