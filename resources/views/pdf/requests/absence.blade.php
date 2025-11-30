{{-- resources/views/pdf/requests/absence.blade.php --}}
@php
    $user   = $user   ?? $request->user   ?? null;
    $course = $course ?? $request->course ?? null;

    $person = $user?->person;

    $name = $person
        ? trim(($person->nachname ?? '') . ', ' . ($person->vorname ?? ''))
        : ($user?->name ?? '—');

    $klasse = $request->class_label
        ?? $course->klassen_id
        ?? '—';

    $date = ($request->date_from ?? $request->date ?? $request->created_at)
        ? optional($request->date_from ?? $request->date ?? $request->created_at)->format('d.m.Y')
        : '—';

    $lateTime  = $request->time_arrived_late ?: '---';
    $earlyTime = $request->time_left_early  ?: '---';
    $fullDay   = $request->full_day ? 'JA' : 'NEIN';

    $absenceReason = $request->reason_item
        ?? $request->reason
        ?? '—';

    $extraReason = $request->message
        ?? ($request->data['extra_reason'] ?? null)
        ?? $request->reason
        ?? '—';

    $place = $request->place ?? 'Köln';

    $createdAt = $request->submitted_at ?? $request->created_at;
    $createdLabel = $createdAt
        ? $createdAt->format('d.m.Y - H:i')
        : '—';

    $logoPath = public_path('site-images/logo.png');
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Entschuldigung von Fehlzeiten</title>
    <style>
        @page { margin: 20px 25px 30px 25px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }

        table { border-collapse: collapse; width: 100%; }

        /* Kopf mit Formblatt links + Logo rechts */
        .header-layout td {
            vertical-align: top;
        }
        .header-left  { width: 45%; }
        .header-center{ width: 20%; }
        .header-right { width: 35%; text-align: right; }

        .formbox {
            border: 0.6px solid #000;
            border-collapse: collapse;
            width: 230px;
        }
        .formbox td {
            padding: 4px 6px;
            text-align: center;
        }
        .formbox-top td {
            font-weight: bold;
            font-size: 12px;
            border-bottom: 0.6px solid #000;
        }
        .formbox-bottom td {
            font-size: 10px;
        }

        .logo-cell img { max-height: 40px; }

        /* Name / Klasse / Datum + Uhrzeit-Block */
        .info-table {
            font-size: 10px;
        }
        .info-table td {
            border: 0.6px solid #000;
            padding: 4px 6px;
        }
        .info-label {
            width: 32%;
            font-weight: bold;
        }

        .block-separator {
            height: 8px;
        }

        /* einspaltige Blöcke für Grund / Begründung */
        .full-block-table {
            margin-top: 4px;
            font-size: 10px;
        }
        .full-block-table td {
            border: 0.6px solid #000;
            padding: 4px 6px;
        }
        .full-block-label {
            font-weight: bold;
        }
        .full-block-content {
            height: 40px;
        }

        /* Footer-Zeile (Ort/Datum + Unterschrift) */
        .footer-line {
            margin-top: 16px;
            font-size: 9px;
        }
        .footer-line .line-cell {
            padding-bottom: 4px;
            border-bottom: 0.6px solid #000;
        }
        .footer-small-row td {
            padding-top: 2px;
            font-size: 8px;
        }

        /* horizontale Linie über CBW-Block */
        .cbw-separator {
            margin-top: 14px;
            border-top: 0.6px solid #000;
        }

        /* CBW-Block mit Checkboxen */
        .cbw-block-title {
            margin-top: 6px;
            margin-bottom: 6px;
            font-size: 10px;
            font-weight: bold;
        }
        .cbw-table {
            width: 40%;
            font-size: 10px;
        }
        .cbw-table td {
            padding: 3px 4px;
            vertical-align: middle;
        }
        .checkbox {
            width: 10px;
            height: 10px;
            border: 0.6px solid #000;
            display: inline-block;
        }
    </style>
</head>
<body>

{{-- Kopf: Formblatt links, Logo rechts --}}
<table class="header-layout">
    <tr>
        <td class="header-left">
            <table class="formbox">
                <tr class="formbox-top">
                    <td>Formblatt</td>
                </tr>
                <tr class="formbox-bottom">
                    <td>Entschuldigung von Fehlzeiten</td>
                </tr>
            </table>
        </td>
        <td class="header-center"></td>
        <td class="header-right logo-cell">
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="CBW Logo">
            @endif
        </td>
    </tr>
</table>

{{-- Name / Klasse / Datum --}}
<table class="info-table" style="margin-top: 16px;">
    <tr>
        <td class="info-label">Name:</td>
        <td>{{ $name }}</td>
    </tr>
    <tr>
        <td class="info-label">Klasse:</td>
        <td>{{ $klasse }}</td>
    </tr>
    <tr>
        <td class="info-label">Datum:</td>
        <td>{{ $date }}</td>
    </tr>
</table>

<div class="block-separator"></div>

{{-- Uhrzeit / ganztags --}}
<table class="info-table">
    <tr>
        <td class="info-label">Uhrzeit – später gekommen:</td>
        <td>{{ $lateTime }}</td>
    </tr>
    <tr>
        <td class="info-label">Uhrzeit – früher gegangen:</td>
        <td>{{ $earlyTime }}</td>
    </tr>
    <tr>
        <td class="info-label">ganztags gefehlt:</td>
        <td>{{ $fullDay }}</td>
    </tr>
</table>

<div class="block-separator"></div>

{{-- Grund der Abwesenheit – einspaltig --}}
<table class="full-block-table">
    <tr>
        <td class="full-block-label">Grund der Abwesenheit:</td>
    </tr>
    <tr>
        <td class="full-block-content">
            {{ $absenceReason }}
        </td>
    </tr>
</table>

<div class="block-separator"></div>

{{-- sonst. Begründung – einspaltig --}}
<table class="full-block-table">
    <tr>
        <td class="full-block-label">sonst. Begründung:</td>
    </tr>
    <tr>
        <td class="full-block-content">
            {{ $extraReason }}
        </td>
    </tr>
</table>

{{-- Fußzeile mit Linie --}}
<table class="footer-line">
    <tr>
        <td class="line-cell" style="width: 50%;">
            {{ $place }} - {{ $createdLabel }}
        </td>
        <td class="line-cell" style="width: 50%; text-align: right;">
            {{ $name }}
        </td>
    </tr>
    <tr class="footer-small-row">
        <td>Ort - Datum</td>
        <td style="text-align: right;">Unterschrift</td>
    </tr>
</table>

<div class="cbw-separator"></div>

<div class="cbw-block-title">
    Wird von CBW ausgefüllt!
</div>

<table class="cbw-table">
    <tr>
        <td style="width: 18px;">
            <span class="checkbox"></span>
        </td>
        <td>wichtiger Grund</td>
    </tr>
    <tr>
        <td>
            <span class="checkbox"></span>
        </td>
        <td>krank mit Attest</td>
    </tr>
    <tr>
        <td>
            <span class="checkbox"></span>
        </td>
        <td>ohne wichtigen Grund</td>
    </tr>
</table>

</body>
</html>
