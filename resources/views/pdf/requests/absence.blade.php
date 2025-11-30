<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Entschuldigung von Fehlzeiten</title>
    <style>
        @page { margin: 28px 28px 32px 28px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #000;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }
        .header-table td {
            padding: 2px 4px;
            vertical-align: middle;
        }

        .top-title-box {
            border: 0.6px solid #000;
            text-align: center;
            padding: 6px 0;
            font-weight: bold;
            font-size: 13px;
        }
        .top-subtitle {
            border-top: 0.6px solid #000;
            padding: 4px 0 2px 0;
            font-size: 11px;
        }

        .logo-cell img {
            max-height: 50px; /* KLEINERES LOGO */
        }

        /* Meta-Felder */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .meta-table td {
            padding: 6px 4px;
            vertical-align: middle;
            border: 0.6px solid #000;
        }
        .label {
            width: 140px;
            font-weight: bold;
            background: #fafafa;
        }

        /* Abschnittskästen */
        .block {
            margin-top: 18px;
        }
        .block-title {
            font-weight: bold;
            margin-bottom: 6px;
        }
        .block-box {
            border: 0.6px solid #000;
            padding: 8px 6px; /* ← LEICHTES PADDING FÜR INHALTSZELLEN */
            min-height: 32px;
        }

        /* Footer – Unterschriftenbereich */
        .signature-wrapper {
            margin-top: 22px;
        }

        .signature-line {
            border-bottom: 0.6px solid #000;
            margin-bottom: 4px; /* Strich direkt über den Linienüberschriften */
        }

        .signature-row {
            width: 100%;
            font-size: 10px;
        }

        .signature-row td {
            padding-top: 2px;
            vertical-align: top;
        }

        /* CBW-Ausgefüllt */
        .cbw-section {
            margin-top: 40px;
            font-size: 12px;
            font-weight: bold;
        }

        .checkbox-table {
            margin-top: 10px;
            width: 100%;
            font-size: 11px;
        }
        .checkbox-table td {
            padding: 3px;
        }
        .checkbox {
            width: 13px;
            height: 13px;
            border: 0.6px solid #000;
            display: inline-block;
            margin-right: 6px;
        }
    </style>
</head>
<body>

{{-- Kopfbereich --}}
<table class="header-table">
    <tr>
        <td style="width: 55%;">
            <div class="top-title-box">
                Formblatt<br>
                <div class="top-subtitle">Entschuldigung von Fehlzeiten</div>
            </div>
        </td>

        <td style="width: 45%; text-align:right;" class="logo-cell">
            @php $logoPath = public_path('site-images/logo.png'); @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="Logo">
            @endif
        </td>
    </tr>
</table>

@php
    $user   = $user ?? $request->user ?? null;

    $name   = $user?->person
        ? ($user->person->nachname . ', ' . $user->person->vorname)
        : ($user?->name ?? '—');

    $klasse = $request->class_code ?? '—';

    $date   = optional($request->date_from ?? $request->created_at)->format('d.m.Y');
@endphp


{{-- Meta-Felder --}}
<table class="meta-table">
    <tr>
        <td class="label">Name:</td>
        <td>{{ $name }}</td>
    </tr>
    <tr>
        <td class="label">Klasse:</td>
        <td>{{ $klasse }}</td>
    </tr>
    <tr>
        <td class="label">Datum:</td>
        <td>{{ $date }}</td>
    </tr>
</table>


{{-- Uhrzeiten --}}
<table class="meta-table">
    <tr>
        <td class="label">Uhrzeit – später gekommen:</td>
        <td>{{ $request->time_arrived_late ? $request->time_arrived_late.' Uhr' : '---' }}</td>
    </tr>
    <tr>
        <td class="label">Uhrzeit – früher gegangen:</td>
        <td>{{ $request->time_left_early ? $request->time_left_early.' Uhr' : '---' }}</td>
    </tr>
    <tr>
        <td class="label">Ganztags gefehlt:</td>
        <td>{{ $request->full_day ? 'JA' : 'NEIN' }}</td>
    </tr>
</table>


{{-- Grund der Abwesenheit --}}
<div class="block">
    <div class="block-title">Grund der Abwesenheit:</div>
    <div class="block-box">
        {{ $request->reason === 'abw_unwichtig' ? '' : 'Wichtig = ' }} {{ $request->reason_item  ?? 'Fehlzeit ohne wichtigen Grund'}}
    </div> 
</div>

{{-- sonstige Begründung --}}
<div class="block">
    <div class="block-title">sonst. Begründung:</div>
    <div class="block-box">
        {{ $request->message ?? '—' }}
    </div>
</div>

 
{{-- Unterschriftenbereich --}}
<div class="signature-wrapper" style="margin-top: 22px;">

    <table class="signature-row" style="width:100%; border-collapse:collapse;">
        
        {{-- Obere Zeile: Köln - Datum | Name --}}
        <tr>
            <td style="width:50%; padding:2px 0; vertical-align:bottom;">
                Köln - {{ optional($request->created_at)->format('d.m.Y - H:i') }}
            </td>
            <td style="width:50%; padding:2px 0; text-align:right; vertical-align:bottom;">
                {{ $name }}
            </td>
        </tr>

        {{-- Strich zwischen oberer und unterer Zeile --}}
        <tr>
            <td colspan="2" style="padding:0;">
                <div style="border-bottom: 0.6px solid #000; width:100%; margin:4px 0;"></div>
            </td>
        </tr>

        {{-- Untere Zeile: Ort - Datum | Unterschrift --}}
        <tr>
            <td style="padding-top:2px; vertical-align:top;">
                Ort – Datum
            </td>
            <td style="padding-top:2px; text-align:right; vertical-align:top;">
                Unterschrift
            </td>
        </tr>

    </table>

</div>



{{-- CBW-Bereich --}}
<div class="cbw-section">
    Wird von CBW ausgefüllt!
</div>

<table class="checkbox-table">
    <tr><td><span class="checkbox"></span> wichtiger Grund</td></tr>
    <tr><td><span class="checkbox"></span> krank mit Attest</td></tr>
    <tr><td><span class="checkbox"></span> ohne wichtigen Grund</td></tr>
</table>

</body>
</html>
