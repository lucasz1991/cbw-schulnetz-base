{{-- resources/views/pdf/courses/course-ratings.blade.php --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Baustein-Bewertung</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }
        h1, h2, h3, h4 {
            margin: 0 0 4px 0;
            padding: 0;
        }
        h1 { font-size: 16px; margin-bottom: 8px; }
        h2 { font-size: 13px; margin-top: 8px; }
        h3 { font-size: 12px; margin-top: 6px; }
        p  { margin: 0 0 4px 0; }

        .logo {
            width: 120px;
            margin-bottom: 12px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .meta-table td {
            padding: 2px 4px;
            vertical-align: top;
        }
        .label {
            font-weight: bold;
            width: 140px;
        }

        .section {
            margin-bottom: 10px;
        }

        .section-header {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 3px;
        }

        .questions-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .questions-table td {
            padding: 2px 4px;
            vertical-align: top;
        }
        .questions-table td.text {
            width: 80%;
        }
        .questions-table td.value {
            width: 20%;
            text-align: right;
            white-space: nowrap;
        }

        .remarks-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 14px;
        }

        .remark-block {
            margin-bottom: 24px;
        }
        .remark-header {
            font-weight: bold;
            margin-bottom: 6px;
            font-size: 12px;
        }
        .remark-text {
            margin-left: 8px;
            line-height: 1.45;
            font-size: 11px;
            text-align: justify;
        }
        .remark-separator {
            width: 100%;
            border-bottom: 1px solid #aaa;
            margin-top: 8px;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>

@php
    $logoPath = public_path('site-images/logo.png');
    $logoSrc = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp

@if($logoSrc)
    <img src="{{ $logoSrc }}" class="logo">
@endif

    {{-- Kopfbereich --}}
    <h1>Baustein-Bewertung</h1>

    <table class="meta-table">
        <tr>
            <td class="label">Klasse</td>
            <td>{{ $meta['class_label'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Baustein</td>
            <td>{{ $meta['module_label'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Dozent/-in</td>
            <td>{{ $meta['tutor_name'] ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Termin</td>
            <td>{{ $meta['termin_label'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Anzahl Bewertungen</td>
            <td>{{ $meta['ratings_count'] ?? 0 }}</td>
        </tr>
    </table>

    {{-- Bewertungsbereiche --}}
    @foreach($sections as $key => $section)
        @php
            $catAvg = $section['avg'] ?? null;
        @endphp

        <div class="section">
            <div class="section-header">
                {{ $section['label'] }}
                @if($catAvg !== null)
                    {{ ' ' . number_format($catAvg, 2, ',', '.') }}
                @endif
            </div>

            <table class="questions-table">
                @foreach($section['questions'] as $q)
                    @php $qAvg = $q['avg'] ?? null; @endphp
                    <tr>
                        <td class="text">
                            {{ $q['label'] }}
                        </td>
                        <td class="value">
                            @if($qAvg !== null)
                                {{ number_format($qAvg, 2, ',', '.') }}
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach

    {{-- Seite 2: Bemerkungen --}}
    @php
        $hasRemarks = $ratings->contains(fn($r) => !empty($r->message));
    @endphp

    @if($hasRemarks)
        <div class="page-break"></div>

        <div class="remarks-title">Bemerkungen der Teilnehmer/-innen</div>

        @foreach($ratings as $rating)
            @if(!empty($rating->message))

                @php
                    $teilnehmerNr = 'anonym';
                    if (! $rating->is_anonymous) {
                        $teilnehmerNr = $rating->user?->person?->teilnehmer_nr
                            ?: $rating->participant_id
                            ?: 'Anonym';
                    }
                @endphp

                <div class="remark-block">
                    <div class="remark-header">
                        Bemerkung von Teilnehmer-Nr: {{ $teilnehmerNr }}
                    </div>
                    <div class="remark-text">
                        {!! nl2br(e($rating->message)) !!}
                    </div>
                    <div class="remark-separator"></div>
                </div>

            @endif
        @endforeach
    @endif

</body>
</html>
