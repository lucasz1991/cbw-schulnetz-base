<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Berichtsheft' }}</title>

    <style>
        @page { margin: 18mm 14mm 16mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color:#111; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }

        .h1 { font-size: 13px; font-weight: 700; margin: 0 0 8px; }
        .muted { color:#444; }

        /* Kopfbereich wie Vorlage */
        .head-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .head-grid td {
            padding: 2px 6px 2px 0;
            vertical-align: bottom;
        }
        .label { font-size: 10px; color:#111; white-space: nowrap; }
        .field {
            display: inline-block;
            min-width: 80px;
            border-bottom: 1px solid #111;
            padding: 0 2px 1px;
        }
        .field.wide { min-width: 230px; }
        .field.mid  { min-width: 150px; }
        .field.sml  { min-width: 90px;  }

        /* Tabelle wie Vorlage */
        table.sheet {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #111;
        }
        .sheet th, .sheet td {
            border: 1px solid #111;
            padding: 6px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .sheet th {
            font-weight: 700;
            text-align: left;
            background: #fff;
        }

        /* Spaltenbreiten angelehnt an Vorlage */
        .col-date { width: 18%; }
        .col-day  { width: 14%; }
        .col-text { width: 68%; }

        /* Zellen-Text */
        .work-text { line-height: 1.25; }
        .work-text h1, .work-text h2, .work-text h3, .work-text h4, .work-text h5, .work-text h6 {
            margin: 0 0 4px;
            padding: 0;
            font-size: 11px;
        }
        .work-text p { margin: 0 0 4px; }
        .work-text ul, .work-text ol { margin: 0 0 4px 18px; padding: 0; }
        .work-text li { margin: 0 0 2px; }

        /* Signaturbereich wie Vorlage */
        .sign-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 10px;
        }
        .sign-table td {
            padding: 2px 6px;
            vertical-align: top;
        }
        .sign-title {
            font-weight: 700;
            padding-bottom: 6px;
        }
        .sign-row {
            width: 100%;
            border-collapse: collapse;
        }
        .sign-row td {
            padding: 2px 6px 2px 0;
            vertical-align: bottom;
        }
        .line {
            display: inline-block;
            border-bottom: 1px solid #111;
            min-width: 120px;
            height: 14px;
        }
        .sig-img {
            max-height: 70px;
            max-width: 230px;
            display: block;
            margin: 0 0 4px;
        }
        .sig-box {
            min-height: 78px;
        }
        .small { font-size: 9px; color:#111; }
    </style>
</head>
<body>

@php
    /**
     * Resolver: Ephemeral Public URL (absolut oder /storage/..) -> möglichst lokaler Pfad
     * damit DomPDF stabil ist. Dein getEphemeralPublicUrl() basiert auf public disk url. :contentReference[oaicite:3]{index=3}
     */
    $resolveImg = function (?string $src) {
        if (!$src) return null;

        // schon absoluter Dateipfad?
        if (is_string($src) && (str_starts_with($src, '/') || preg_match('/^[A-Z]:\\\\/i', $src))) {
            return is_file($src) ? $src : null;
        }

        $path = parse_url($src, PHP_URL_PATH) ?: $src;
        if (!is_string($path) || $path === '') return null;

        if (str_starts_with($path, '/storage/')) {
            $full = public_path(ltrim($path, '/')); // public/storage/...
            return is_file($full) ? $full : $src;   // fallback: URL
        }

        if (str_starts_with($path, 'storage/')) {
            $full = public_path($path);
            return is_file($full) ? $full : $src;
        }

        return $src;
    };

    $weekdayName = function (\Carbon\Carbon $d) {
        // ISO: 1=Mo .. 7=So
        return match ((int)$d->isoWeekday()) {
            1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So',
        };
    };

    $kwLabel = function (\Carbon\Carbon $d) {
        return 'KW ' . $d->isoWeek() . ' / ' . $d->isoWeekYear();
    };

    // Kopf-Felder (Fallbacks – anpassbar, aber keine Infos weglassen)
    $participantName = $user->name ?? '';
@endphp


{{-- =========================================================
    SINGLE: 1 Seite im Vorlagenraster
========================================================= --}}
@if(($mode ?? null) === 'single')
@php
    $d = $entry->entry_date instanceof \Carbon\Carbon ? $entry->entry_date : \Carbon\Carbon::parse($entry->entry_date);
    $kw = $kwLabel($d);

    $ausbildungsnachweisNr = $course->klassen_id ?? ('Kurs #' . ($course->id ?? ''));
    $ausbildungsjahr = $course->planned_start_date ? \Carbon\Carbon::parse($course->planned_start_date)->format('Y') : $d->format('Y');
    $abteilung = $course->title ?? ($course->klassen_id ?? 'Kurs');

    $pImg = $resolveImg($participantSignatureUrl ?? null);
    $tImg = $resolveImg($trainerSignatureUrl ?? null);
@endphp

<div class="page">
    <div class="h1">Berichtsheft von: <span class="field wide">{{ $participantName }}</span></div>

    <table class="head-grid">
        <tr>
            <td class="label">Ausbildungsnachweis Nr.:</td>
            <td><span class="field mid">{{ $ausbildungsnachweisNr }}</span></td>

            <td class="label">Ausbildungsjahr</td>
            <td><span class="field sml">{{ $ausbildungsjahr }}</span></td>
        </tr>
        <tr>
            <td class="label">Für die Kalenderwoche:</td>
            <td><span class="field mid">{{ $kw }}</span></td>

            <td class="label">Ausbildungsabteilung:</td>
            <td><span class="field mid">{{ $abteilung }}</span></td>
        </tr>
    </table>

    <table class="sheet">
        <thead>
            <tr>
                <th class="col-date">Datum</th>
                <th class="col-day">Wochen-<br>tag.</th>
                <th class="col-text">Ausgeführte Arbeiten, Unterricht usw. Stichworte</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $d->format('d.m.Y') }}</td>
                <td>{{ $weekdayName($d) }}</td>
                <td class="work-text">{!! $entry->text !!}</td>
            </tr>
        </tbody>
    </table>

    <table class="sign-table">
        <tr>
            <td style="width:50%">
                <div class="sign-title">Ausbilder/in</div>

                <table class="sign-row">
                    <tr>
                        <td class="label">Datum</td>
                        <td><span class="line"></span></td>
                    </tr>
                    <tr>
                        <td class="label">Unterschrift</td>
                        <td class="sig-box">
                            @if($tImg)
                                <img class="sig-img" src="{{ $tImg }}" alt="Unterschrift Ausbilder">
                            @endif
                            <span class="line"></span>
                        </td>
                    </tr>
                </table>
            </td>

            <td style="width:50%">
                <div class="sign-title">Auszubildende/r</div>

                <table class="sign-row">
                    <tr>
                        <td class="label">Datum</td>
                        <td><span class="line"></span></td>
                    </tr>
                    <tr>
                        <td class="label">Unterschrift</td>
                        <td class="sig-box">
                            @if($pImg)
                                <img class="sig-img" src="{{ $pImg }}" alt="Unterschrift Teilnehmer">
                            @endif
                            <span class="line"></span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
@endif


{{-- =========================================================
    MODULE: pro ISO-KW eine Seite im Vorlagenraster
========================================================= --}}
@if(($mode ?? null) === 'module')
@php
    $ausbildungsnachweisNr = $course->klassen_id ?? ('Kurs #' . ($course->id ?? ''));
    $ausbildungsjahr = $course->planned_start_date ? \Carbon\Carbon::parse($course->planned_start_date)->format('Y') : now()->format('Y');
    $abteilung = $course->title ?? ($course->klassen_id ?? 'Kurs');

    // entries nach KW gruppieren
    $groups = collect($entries)->map(function($e){
        $e->entry_date = $e->entry_date instanceof \Carbon\Carbon ? $e->entry_date : \Carbon\Carbon::parse($e->entry_date);
        return $e;
    })->groupBy(function($e){
        return $e->entry_date->isoWeekYear() . '-KW' . $e->entry_date->isoWeek();
    });

    $pImg = $resolveImg($participantSignatureUrl ?? null);
    $tImg = $resolveImg($trainerSignatureUrl ?? null);
@endphp

@foreach($groups as $groupKey => $weekEntries)
@php
    $firstDay = $weekEntries->first()->entry_date;
    $kw = $kwLabel($firstDay);
@endphp

<div class="page">
    <div class="h1">Berichtsheft von: <span class="field wide">{{ $participantName }}</span></div>

    <table class="head-grid">
        <tr>
            <td class="label">Ausbildungsnachweis Nr.:</td>
            <td><span class="field mid">{{ $ausbildungsnachweisNr }}</span></td>

            <td class="label">Ausbildungsjahr</td>
            <td><span class="field sml">{{ $ausbildungsjahr }}</span></td>
        </tr>
        <tr>
            <td class="label">Für die Kalenderwoche:</td>
            <td><span class="field mid">{{ $kw }}</span></td>

            <td class="label">Ausbildungsabteilung:</td>
            <td><span class="field mid">{{ $abteilung }}</span></td>
        </tr>
    </table>

    <table class="sheet">
        <thead>
            <tr>
                <th class="col-date">Datum</th>
                <th class="col-day">Wochen-<br>tag.</th>
                <th class="col-text">Ausgeführte Arbeiten, Unterricht usw. Stichworte</th>
            </tr>
        </thead>
        <tbody>
            @foreach($weekEntries as $e)
                @php $d = $e->entry_date; @endphp
                <tr>
                    <td>{{ $d->format('d.m.Y') }}</td>
                    <td>{{ $weekdayName($d) }}</td>
                    <td class="work-text">{!! $e->text !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign-table">
        <tr>
            <td style="width:50%">
                <div class="sign-title">Ausbilder/in</div>

                <table class="sign-row">
                    <tr>
                        <td class="label">Datum</td>
                        <td><span class="line"></span></td>
                    </tr>
                    <tr>
                        <td class="label">Unterschrift</td>
                        <td class="sig-box">
                            @if($tImg)
                                <img class="sig-img" src="{{ $tImg }}" alt="Unterschrift Ausbilder">
                            @endif
                            <span class="line"></span>
                        </td>
                    </tr>
                </table>
            </td>

            <td style="width:50%">
                <div class="sign-title">Auszubildende/r</div>

                <table class="sign-row">
                    <tr>
                        <td class="label">Datum</td>
                        <td><span class="line"></span></td>
                    </tr>
                    <tr>
                        <td class="label">Unterschrift</td>
                        <td class="sig-box">
                            @if($pImg)
                                <img class="sig-img" src="{{ $pImg }}" alt="Unterschrift Teilnehmer">
                            @endif
                            <span class="line"></span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
@endforeach
@endif


{{-- =========================================================
    ALL: pro Kurs + pro ISO-KW eine Seite im Vorlagenraster
========================================================= --}}
@if(($mode ?? null) === 'all')
@foreach($books as $book)
@php
    $course = $book->course;

    $ausbildungsnachweisNr = $course->klassen_id ?? ('Kurs #' . ($course->id ?? ''));
    $ausbildungsjahr = $course->planned_start_date ? \Carbon\Carbon::parse($course->planned_start_date)->format('Y') : now()->format('Y');
    $abteilung = $course->title ?? ($course->klassen_id ?? 'Kurs');

    $entriesAll = collect($book->entries)->map(function($e){
        $e->entry_date = $e->entry_date instanceof \Carbon\Carbon ? $e->entry_date : \Carbon\Carbon::parse($e->entry_date);
        return $e;
    });

    $groups = $entriesAll->groupBy(function($e){
        return $e->entry_date->isoWeekYear() . '-KW' . $e->entry_date->isoWeek();
    });

    $pImg = $resolveImg($book->participantSignatureUrl ?? null);
    $tImg = $resolveImg($book->trainerSignatureUrl ?? null);
@endphp

@foreach($groups as $groupKey => $weekEntries)
@php
    $firstDay = $weekEntries->first()->entry_date;
    $kw = $kwLabel($firstDay);
@endphp

<div class="page">
    <div class="h1">Berichtsheft von: <span class="field wide">{{ $participantName }}</span></div>

    <table class="head-grid">
        <tr>
            <td class="label">Ausbildungsnachweis Nr.:</td>
            <td><span class="field mid">{{ $ausbildungsnachweisNr }}</span></td>

            <td class="label">Ausbildungsjahr</td>
            <td><span class="field sml">{{ $ausbildungsjahr }}</span></td>
        </tr>
        <tr>
            <td class="label">Für die Kalenderwoche:</td>
            <td><span class="field mid">{{ $kw }}</span></td>

            <td class="label">Ausbildungsabteilung:</td>
            <td><span class="field mid">{{ $abteilung }}</span></td>
        </tr>
    </table>

    <table class="sheet">
        <thead>
            <tr>
                <th class="col-date">Datum</th>
                <th class="col-day">Wochen-<br>tag.</th>
                <th class="col-text">Ausgeführte Arbeiten, Unterricht usw. Stichworte</th>
            </tr>
        </thead>
        <tbody>
            @foreach($weekEntries as $e)
                @php $d = $e->entry_date; @endphp
                <tr>
                    <td>{{ $d->format('d.m.Y') }}</td>
                    <td>{{ $weekdayName($d) }}</td>
                    <td class="work-text">{!! $e->text !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign-table">
        <tr>
            <td style="width:50%">
                <div class="sign-title">Ausbilder/in</div>

                <table class="sign-row">
                    <tr>
                        <td class="label">Datum</td>
                        <td><span class="line"></span></td>
                    </tr>
                    <tr>
                        <td class="label">Unterschrift</td>
                        <td class="sig-box">
                            @if($tImg)
                                <img class="sig-img" src="{{ $tImg }}" alt="Unterschrift Ausbilder">
                            @endif
                            <span class="line"></span>
                        </td>
                    </tr>
                </table>
            </td>

            <td style="width:50%">
                <div class="sign-title">Auszubildende/r</div>

                <table class="sign-row">
                    <tr>
                        <td class="label">Datum</td>
                        <td><span class="line"></span></td>
                    </tr>
                    <tr>
                        <td class="label">Unterschrift</td>
                        <td class="sig-box">
                            @if($pImg)
                                <img class="sig-img" src="{{ $pImg }}" alt="Unterschrift Teilnehmer">
                            @endif
                            <span class="line"></span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
@endforeach

@endforeach
@endif

</body>
</html>
