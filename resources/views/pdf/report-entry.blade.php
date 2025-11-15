<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Berichtsheft-Eintrag</title>

    <style>
        @page {
            margin: 25mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1;
            color: #111;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 15px 0;
            padding: 0;
            font-weight: bold;
        }

        .info-box {
            border: 1px solid #888;
            padding: 10px 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            background: #f9f9f9;
        }

        .info-row {
            margin: 3px 0;
            font-size: 12px;
        }

        .label {
            font-weight: bold;
            width: 130px;
            display: inline-block;
        }

        .entry-text {
            margin-top: 25px;
        }
        .entry-text p{
            margin:0px;
            line-height: 1.5;

        }

        hr {
            border: 0;
            border-top: 1px solid #ccc;
            margin: 20px 0;
        }
    </style>
</head>

<body>

    {{-- Titel --}}
    
    {{-- Info-Box --}}
    <div class="info-box">
        <h1>Berichtsheft – Eintrag</h1>

        <div class="info-row">
            <span class="label">Datum:</span>
            {{ optional($entry->entry_date)->format('d.m.Y') ?? '—' }}
        </div>

        @if($entry->course ?? false)
            <div class="info-row">
                <span class="label">Kurs:</span>
                {{ $entry->course->title }}
            </div>
        @endif

    </div>

    {{-- Eingetragener Text --}}
    <div class="entry-text">
        {!! $entry->text !!}
    </div>

</body>
</html>
