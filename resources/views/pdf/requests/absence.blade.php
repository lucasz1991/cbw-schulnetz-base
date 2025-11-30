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
        ?? $request->data['extra_reason'] ?? null
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

        /* Kopf mit Formblatt + Logo */
        .header-layout td {
            vertical-align: top;
        }
        .header-left  { width: 20%; }
        .header-center{ width: 55%; text-align: center; }
        .header-right { width: 25%; text-align: right; }

        .formbox {
            border: 0.6px solid #000;
            width: 100%;
        }
        .formbox td {
            padding: 4px 6px;
            text-align: center;
        }
        .formbox-title {
            font-weight: bold;
            font-size: 12px;
        }
        .formbox-subtitle {
            font-size: 10px;
        }

        .logo-cell img { max-height: 40px; }

        /* große Infotabelle */
        .info-table {
            margin-top: 14px;
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

        /* Footer-Zeile (Ort/Datum + Unterschrift) */
        .footer-line {
            margin-top: 14px;
            font-size: 9px;
        }
        .footer-line td {
            padding-top: 10px;
        }
        .footer-small {
            font-size: 8px;
        }

        /* CBW-Block mit Checkboxen */
        .cbw-block-title {
            margin-top: 22px;
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

{{-- Kopf: Formblatt + Logo --}}
<table class="header-layout">
    <tr>
        <td class="header-left"></td>
        <td class="header-center">
            <table class="formbox">
                <tr>
                    <td class="formbox-title">Formblatt</td>
                </tr>
                <tr>
                    <td class="formbox-subtitle">Entschuldigung von Fehlzeiten</td>
                </tr>
            </table>
        </td>
        <td class="header-right logo-cell">
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="CBW Logo">
            @endif
        </td>
    </tr>
</table>

{{-- Daten- / Infotabelle --}}
<table class="info-table">
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

    <tr>
        <td class="info-label">Grund der Abwesenheit:</td>
        <td style="height: 40px;">
            {{ $absenceReason }}
        </td>
    </tr>

    <tr>
        <td class="info-label">sonst. Begründung:</td>
        <td style="height: 40px;">
            {{ $extraReason }}
        </td>
    </tr>
</table>

{{-- Fußzeile mit Ort / Datum / Unterschrift --}}
<table class="footer-line">
    <tr>
        <td style="width: 50%;">
            {{ $place }} - {{ $createdLabel }}
        </td>
        <td style="width: 50%; text-align: right;">
            {{ $name }}
        </td>
    </tr>
    <tr>
        <td class="footer-small">
            Ort - Datum
        </td>
        <td class="footer-small" style="text-align: right;">
            Unterschrift
        </td>
    </tr>
</table>

{{-- CBW-Ausfüllbereich --}}
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
