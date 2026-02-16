<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Berichtsheft' }}</title>

    <style>
        @page { margin: 20px 20px 30px 20px; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            padding: 4px 6px;
            vertical-align: top;
        }

        .logo {
            width: 120px;
            margin-bottom: 8px;
        }

        .title-center {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            color: #0f172a;
            padding-top: 6px;
        }

        .subtitle {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
            font-weight: normal;
        }

        .meta-box {
            border: 0.4px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 6px;
            padding: 6px 8px;
            line-height: 1.35;
        }

        .meta-k {
            display: inline-block;
            min-width: 120px;
            font-weight: bold;
            color: #334155;
        }

        table.sheet {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
        }

        .sheet th, .sheet td {
            border: 0.4px solid #cbd5e1;
            padding: 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .sheet th {
            font-weight: 700;
            text-align: left;
            background: #eef2f7;
            color: #334155;
        }

        .col-date { width: 17%; }
        .col-text { width: 83%; }

        .datecell { line-height: 1.2; }
        .datecell .d { font-weight: 700; color: #0f172a; }
        .datecell .w { font-size: 9px; color: #64748b; margin-top: 2px; }

        .work-text { line-height: 1.25; }
        .work-text h1, .work-text h2, .work-text h3, .work-text h4, .work-text h5, .work-text h6 {
            margin: 0 0 4px;
            padding: 0;
            font-size: 11px;
        }
        .work-text p { margin: 0 0 4px; }
        .work-text ul, .work-text ol { margin: 0 0 4px 18px; padding: 0; }
        .work-text li { margin: 0 0 2px; }

        .sign-wrap {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 10px;
        }

        .sign-col { width: 50%; vertical-align: top; }
        .sign-title { font-weight: 700; margin: 0 0 6px; color: #0f172a; }

        .sign-box {
            border: 0.4px solid #cbd5e1;
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #fff;
        }

        .sign-box td {
            border: 0.4px solid #cbd5e1;
            padding: 6px;
            vertical-align: middle;
            font-size: 10px;
        }

        .sign-box .lbl { width: 30%; white-space: nowrap; font-weight: 700; color: #334155; background: #f8fafc; }
        .sign-box .val { width: 70%; }

        .sig-img {
            max-height: 70px;
            max-width: 230px;
            display: block;
            margin: 0;
        }

        .sig-cell { min-height: 90px; }
    </style>
</head>
<body>

@php
    use Carbon\Carbon;

    $logoPath = public_path('site-images/logo.png');
    $logoSrc = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    $resolveImg = function (?string $src) {
        if (!$src) return null;

        if (is_string($src) && (str_starts_with($src, '/') || preg_match('/^[A-Z]:\\\\/i', $src))) {
            return is_file($src) ? $src : null;
        }

        $path = parse_url($src, PHP_URL_PATH) ?: $src;
        if (!is_string($path) || $path === '') return null;

        if (str_starts_with($path, '/storage/')) {
            $full = public_path(ltrim($path, '/'));
            return is_file($full) ? $full : $src;
        }
        if (str_starts_with($path, 'storage/')) {
            $full = public_path($path);
            return is_file($full) ? $full : $src;
        }

        return $src;
    };

    $weekdayLong = function (Carbon $d) {
        return match ((int)$d->isoWeekday()) {
            1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
            5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
        };
    };

    $kwLabel = function (Carbon $d) {
        return 'KW ' . $d->isoWeek() . ' / ' . $d->isoWeekYear();
    };

    $participantName = $user->name ?? '';

    $parseYmdSlash = function (?string $v): ?Carbon {
        $v = trim((string)$v);
        if ($v === '') return null;

        $v = str_replace('\\/', '/', $v);

        try { return Carbon::createFromFormat('Y/m/d', $v); } catch (\Throwable $e) {}
        try { return Carbon::parse($v); } catch (\Throwable $e) {}

        return null;
    };

    $umschulungStartFromProgramm = function ($user) use ($parseYmdSlash): ?Carbon {
        $pd = data_get($user, 'person.programmdata', []);
        $baust = collect(data_get($pd, 'tn_baust', []));

        $min = $baust
            ->map(fn ($b) => $parseYmdSlash(data_get($b, 'beginn_baustein')))
            ->filter()
            ->sort()
            ->first();

        if ($min) return $min;

        return $parseYmdSlash(data_get($pd, 'vertrag_beginn'));
    };

    $trainingWeekNo = function (Carbon $entryDate, Carbon $startDate): int {
        $startWeek = $startDate->copy()->startOfWeek(Carbon::MONDAY);
        $entryWeek = $entryDate->copy()->startOfWeek(Carbon::MONDAY);
        return max(1, (int)($startWeek->diffInWeeks($entryWeek) + 1));
    };

    $trainingYearNo = function (Carbon $entryDate, Carbon $startDate): int {
        $months = $startDate->copy()->startOfDay()->diffInMonths($entryDate->copy()->startOfDay());
        return max(1, min(2, (int)(intdiv($months, 12) + 1)));
    };

    $padNachweis = fn (int $n): string => str_pad((string)$n, 2, '0', STR_PAD_LEFT);
@endphp

@if(($mode ?? null) === 'single')
@php
    $d  = $entry->entry_date instanceof Carbon ? $entry->entry_date : Carbon::parse($entry->entry_date);
    $kw = $kwLabel($d);

    $start = $umschulungStartFromProgramm($user) ?? $d;

    $ausbildungsnachweisNr = $padNachweis($trainingWeekNo($d, $start));
    $ausbildungsjahr       = $trainingYearNo($d, $start);

    $abteilung = $course->title ?? ($course->klassen_id ?? 'Kurs');

    $pImg = $resolveImg($participantSignatureUrl ?? null);
    $tImg = $resolveImg($trainerSignatureUrl ?? null);

    $pDate = !empty($participantSignatureFile?->created_at)
        ? Carbon::parse($participantSignatureFile->created_at)->format('d.m.Y')
        : '';

    $tDate = !empty($trainerSignatureFile?->created_at)
        ? Carbon::parse($trainerSignatureFile->created_at)->format('d.m.Y')
        : '';
@endphp

<div class="page">
    <table class="header-table">
        <tr>
            <td style="width: 32%;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" class="logo" alt="Logo">
                @endif
                <div class="meta-box">
                    <div><span class="meta-k">Teilnehmer:</span> {{ $participantName ?: '�' }}</div>
                    <div><span class="meta-k">Abteilung:</span> {{ $abteilung }}</div>
                    <div><span class="meta-k">Kalenderwoche:</span> {{ $kw }}</div>
                </div>
            </td>
            <td class="title-center" style="width: 40%;">
                Berichtsheft
                <div class="subtitle">Ausbildungsnachweis und Tätigkeitsdokumentation</div>
            </td>
            <td style="width: 28%;">
                <div class="meta-box">
                    <div><span class="meta-k">Nachweis Nr.:</span> {{ $ausbildungsnachweisNr }}</div>
                    <div><span class="meta-k">Ausbildungsjahr:</span> {{ $ausbildungsjahr }}</div>
                    <div><span class="meta-k">Export:</span> {{ now()->format('d.m.Y H:i') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="sheet">
        <thead>
            <tr>
                <th class="col-date">Datum</th>
                <th class="col-text">Ausgeführte Arbeiten, Unterricht usw. Stichworte</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="datecell">
                    <div class="d">{{ $d->format('d.m.Y') }}</div>
                    <div class="w">{{ $weekdayLong($d) }}</div>
                </td>
                <td class="work-text">{!! $entry->text !!}</td>
            </tr>
        </tbody>
    </table>

    <table class="sign-wrap">
        <tr>
            <td class="sign-col" style="padding-right:10px;">
                <div class="sign-title">Ausbilder/in</div>
                <table class="sign-box">
                    <tr>
                        <td class="lbl">Datum</td>
                        <td class="val">{{ $tDate }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Unterschrift</td>
                        <td class="val sig-cell">
                            @if($tImg)
                                <img class="sig-img" src="{{ $tImg }}" alt="Unterschrift Ausbilder">
                            @endif
                        </td>
                    </tr>
                </table>
            </td>

            <td class="sign-col" style="padding-left:10px;">
                <div class="sign-title">Auszubildende/r</div>
                <table class="sign-box">
                    <tr>
                        <td class="lbl">Datum</td>
                        <td class="val">{{ $pDate }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Unterschrift</td>
                        <td class="val sig-cell">
                            @if($pImg)
                                <img class="sig-img" src="{{ $pImg }}" alt="Unterschrift Teilnehmer">
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
@endif

@if(($mode ?? null) === 'module')
@php
    $abteilung = $course->title ?? ($course->klassen_id ?? 'Kurs');

    $groups = collect($entries)->map(function ($e) {
        $e->entry_date = $e->entry_date instanceof Carbon ? $e->entry_date : Carbon::parse($e->entry_date);
        return $e;
    })->groupBy(function ($e) {
        return $e->entry_date->isoWeekYear() . '-KW' . $e->entry_date->isoWeek();
    });

    $start = $umschulungStartFromProgramm($user);
@endphp

@foreach($groups as $groupKey => $weekEntries)
@php
    $firstDay = $weekEntries->first()->entry_date;
    $kw = $kwLabel($firstDay);

    $startEffective = $start ?? $firstDay;

    $ausbildungsnachweisNr = $padNachweis($trainingWeekNo($firstDay, $startEffective));
    $ausbildungsjahr       = $trainingYearNo($firstDay, $startEffective);

    $pImg = $resolveImg($participantSignatureUrl ?? null);
    $tImg = $resolveImg($trainerSignatureUrl ?? null);

    $pDate = !empty($participantSignatureFile?->created_at)
        ? Carbon::parse($participantSignatureFile->created_at)->format('d.m.Y')
        : '';

    $tDate = !empty($trainerSignatureFile?->created_at)
        ? Carbon::parse($trainerSignatureFile->created_at)->format('d.m.Y')
        : '';
@endphp

<div class="page">
    <table class="header-table">
        <tr>
            <td style="width: 32%;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" class="logo" alt="Logo">
                @endif
                <div class="meta-box">
                    <div><span class="meta-k">Teilnehmer:</span> {{ $participantName ?: '�' }}</div>
                    <div><span class="meta-k">Abteilung:</span> {{ $abteilung }}</div>
                    <div><span class="meta-k">Kalenderwoche:</span> {{ $kw }}</div>
                </div>
            </td>
            <td class="title-center" style="width: 40%;">
                Berichtsheft
                <div class="subtitle">Ausbildungsnachweis und Tätigkeitsdokumentation</div>
            </td>
            <td style="width: 28%;">
                <div class="meta-box">
                    <div><span class="meta-k">Nachweis Nr.:</span> {{ $ausbildungsnachweisNr }}</div>
                    <div><span class="meta-k">Ausbildungsjahr:</span> {{ $ausbildungsjahr }}</div>
                    <div><span class="meta-k">Export:</span> {{ now()->format('d.m.Y H:i') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="sheet">
        <thead>
            <tr>
                <th class="col-date">Datum</th>
                <th class="col-text">Ausgeführte Arbeiten, Unterricht usw. Stichworte</th>
            </tr>
        </thead>
        <tbody>
            @foreach($weekEntries as $e)
                @php $dd = $e->entry_date; @endphp
                <tr>
                    <td class="datecell">
                        <div class="d">{{ $dd->format('d.m.Y') }}</div>
                        <div class="w">{{ $weekdayLong($dd) }}</div>
                    </td>
                    <td class="work-text">{!! $e->text !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign-wrap">
        <tr>
            <td class="sign-col" style="padding-right:10px;">
                <div class="sign-title">Ausbilder/in</div>
                <table class="sign-box">
                    <tr>
                        <td class="lbl">Datum</td>
                        <td class="val">{{ $tDate }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Unterschrift</td>
                        <td class="val sig-cell">
                            @if($tImg)
                                <img class="sig-img" src="{{ $tImg }}" alt="Unterschrift Ausbilder">
                            @endif
                        </td>
                    </tr>
                </table>
            </td>

            <td class="sign-col" style="padding-left:10px;">
                <div class="sign-title">Auszubildende/r</div>
                <table class="sign-box">
                    <tr>
                        <td class="lbl">Datum</td>
                        <td class="val">{{ $pDate }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Unterschrift</td>
                        <td class="val sig-cell">
                            @if($pImg)
                                <img class="sig-img" src="{{ $pImg }}" alt="Unterschrift Teilnehmer">
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
@endforeach
@endif

@if(($mode ?? null) === 'all')
@foreach($books as $book)
@php
    $course = $book->course;

    $abteilung = $course->title ?? ($course->klassen_id ?? 'Kurs');

    $entriesAll = collect($book->entries)->map(function ($e) {
        $e->entry_date = $e->entry_date instanceof Carbon ? $e->entry_date : Carbon::parse($e->entry_date);
        return $e;
    });

    $groups = $entriesAll->groupBy(function ($e) {
        return $e->entry_date->isoWeekYear() . '-KW' . $e->entry_date->isoWeek();
    });

    $start = $umschulungStartFromProgramm($user);

    $pImg  = $resolveImg($book->participantSignatureUrl ?? null);
    $tImg  = $resolveImg($book->trainerSignatureUrl ?? null);

    $pDate = !empty($book->participantSignatureFile?->created_at)
        ? Carbon::parse($book->participantSignatureFile->created_at)->format('d.m.Y')
        : '';

    $tDate = !empty($book->trainerSignatureFile?->created_at)
        ? Carbon::parse($book->trainerSignatureFile->created_at)->format('d.m.Y')
        : '';
@endphp

@foreach($groups as $groupKey => $weekEntries)
@php
    $firstDay = $weekEntries->first()->entry_date;
    $kw = $kwLabel($firstDay);

    $startEffective = $start ?? $firstDay;

    $ausbildungsnachweisNr = $padNachweis($trainingWeekNo($firstDay, $startEffective));
    $ausbildungsjahr       = $trainingYearNo($firstDay, $startEffective);
@endphp

<div class="page">
    <table class="header-table">
        <tr>
            <td style="width: 32%;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" class="logo" alt="Logo">
                @endif
                <div class="meta-box">
                    <div><span class="meta-k">Teilnehmer:</span> {{ $participantName ?: '�' }}</div>
                    <div><span class="meta-k">Abteilung:</span> {{ $abteilung }}</div>
                    <div><span class="meta-k">Kalenderwoche:</span> {{ $kw }}</div>
                </div>
            </td>
            <td class="title-center" style="width: 40%;">
                Berichtsheft
                <div class="subtitle">Ausbildungsnachweis und Tätigkeitsdokumentation</div>
            </td>
            <td style="width: 28%;">
                <div class="meta-box">
                    <div><span class="meta-k">Nachweis Nr.:</span> {{ $ausbildungsnachweisNr }}</div>
                    <div><span class="meta-k">Ausbildungsjahr:</span> {{ $ausbildungsjahr }}</div>
                    <div><span class="meta-k">Export:</span> {{ now()->format('d.m.Y H:i') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="sheet">
        <thead>
            <tr>
                <th class="col-date">Datum</th>
                <th class="col-text">Ausgeführte Arbeiten, Unterricht usw. Stichworte</th>
            </tr>
        </thead>
        <tbody>
            @foreach($weekEntries as $e)
                @php $dd = $e->entry_date; @endphp
                <tr>
                    <td class="datecell">
                        <div class="d">{{ $dd->format('d.m.Y') }}</div>
                        <div class="w">{{ $weekdayLong($dd) }}</div>
                    </td>
                    <td class="work-text">{!! $e->text !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign-wrap">
        <tr>
            <td class="sign-col" style="padding-right:10px;">
                <div class="sign-title">Ausbilder/in</div>
                <table class="sign-box">
                    <tr>
                        <td class="lbl">Datum</td>
                        <td class="val">{{ $tDate }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Unterschrift</td>
                        <td class="val sig-cell">
                            @if($tImg)
                                <img class="sig-img" src="{{ $tImg }}" alt="Unterschrift Ausbilder">
                            @endif
                        </td>
                    </tr>
                </table>
            </td>

            <td class="sign-col" style="padding-left:10px;">
                <div class="sign-title">Auszubildende/r</div>
                <table class="sign-box">
                    <tr>
                        <td class="lbl">Datum</td>
                        <td class="val">{{ $pDate }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Unterschrift</td>
                        <td class="val sig-cell">
                            @if($pImg)
                                <img class="sig-img" src="{{ $pImg }}" alt="Unterschrift Teilnehmer">
                            @endif
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
