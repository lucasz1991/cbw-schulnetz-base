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

        .head-grid { width:100%; border-collapse:collapse; margin-bottom: 8px; }
        .head-grid td { padding: 2px 6px 2px 0; vertical-align: bottom; }
        .label { font-size: 10px; color:#111; white-space: nowrap; }
        .field { display:inline-block; min-width: 80px; border-bottom: 1px solid #111; padding: 0 2px 1px; }
        .field.wide { min-width: 260px; }
        .field.mid  { min-width: 160px; }
        .field.sml  { min-width: 100px; }

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
        .sheet th { font-weight: 700; text-align: left; background: #fff; }

        .col-date { width: 13%; }
        .col-text { width: 87%; }

        .datecell { line-height: 1.15; }
        .datecell .d { font-weight: 700; }
        .datecell .w { font-size: 9px; color:#444; margin-top: 2px; }

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
        .sign-title { font-weight: 700; margin: 0 0 6px; }

        .sign-box {
            border: 1px solid #111;
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .sign-box td {
            border: 1px solid #111;
            padding: 6px;
            vertical-align: middle;
            font-size: 10px;
        }
        .sign-box .lbl { width: 30%; white-space: nowrap; font-weight: 700; }
        .sign-box .val { width: 70%; }

        .sig-img {
            max-height: 70px;
            max-width: 230px;
            display: block;
            margin: 0 0 6px;
        }

        .sig-line {
            border-bottom: 1px solid #111;
            height: 14px;
            display: block;
            width: 100%;
        }

        .sig-cell {
            min-height: 90px;
        }
    </style>
</head>
<body>

@php
    use Carbon\Carbon;

    /* -------------------------------------------------
     * Helpers (Images / Labels)
     * ------------------------------------------------- */
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

    /* -------------------------------------------------
     * Umschulungsstart aus programmdata (tn_baust) ermitteln
     * ------------------------------------------------- */
    $parseYmdSlash = function (?string $v): ?Carbon {
        $v = trim((string)$v);
        if ($v === '') return null;

        $v = str_replace('\/', '/', $v);

        // erwartetes Format: YYYY/MM/DD
        try { return Carbon::createFromFormat('Y/m/d', $v); } catch (\Throwable $e) {}

        // Fallback: Carbon::parse
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

        // Fallback: vertrag_beginn (wenn vorhanden)
        return $parseYmdSlash(data_get($pd, 'vertrag_beginn'));
    };

    /* -------------------------------------------------
     * Ausbildungsnachweis Nr. (Umschulungswoche) + Ausbildungsjahr
     * ------------------------------------------------- */
    $trainingWeekNo = function (Carbon $entryDate, Carbon $startDate): int {
        $startWeek = $startDate->copy()->startOfWeek(Carbon::MONDAY);
        $entryWeek = $entryDate->copy()->startOfWeek(Carbon::MONDAY);
        return max(1, (int)($startWeek->diffInWeeks($entryWeek) + 1));
    };

    $trainingYearNo = function (Carbon $entryDate, Carbon $startDate): int {
        $months = $startDate->copy()->startOfDay()->diffInMonths($entryDate->copy()->startOfDay());
        // Umschulung: 24 Monate => 1..2 (Clamp entfernen, falls später länger)
        return max(1, min(2, (int)(intdiv($months, 12) + 1)));
    };

    $padNachweis = fn (int $n): string => str_pad((string)$n, 2, '0', STR_PAD_LEFT);
@endphp


{{-- =========================================================
    SINGLE
========================================================= --}}
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


{{-- =========================================================
    MODULE: pro ISO-KW eine Seite
========================================================= --}}
@if(($mode ?? null) === 'module')
@php
    $abteilung = $course->title ?? ($course->klassen_id ?? 'Kurs');

    $groups = collect($entries)->map(function ($e) {
        $e->entry_date = $e->entry_date instanceof Carbon ? $e->entry_date : Carbon::parse($e->entry_date);
        return $e;
    })->groupBy(function ($e) {
        return $e->entry_date->isoWeekYear() . '-KW' . $e->entry_date->isoWeek();
    });

    // Umschulungsstart einmal ermitteln (für alle Wochen identisch)
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


{{-- =========================================================
    ALL: pro Kurs + pro KW eine Seite
========================================================= --}}
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

    // Hinweis: Falls $book einem anderen Teilnehmer gehört als $user, hier den passenden Bezug nutzen
    // (z.B. $book->participant / $book->user), damit programmdata korrekt ist.
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