{{-- resources/views/pdf/requests/exam-registration.blade.php --}}
@php
    $user   = $user   ?? ($request->user   ?? null);
    $person = $user?->person;
    $klasse = $request->class_code ?? '—';

    $name = $person
        ? trim(($person->nachname ?? ''). ', ' . ($person->vorname ?? ''))
        : ($user?->name ?? '—');

    $geburt = !empty($person?->geburt_datum)
        ? optional($person->geburt_datum)->format('d.m.Y')
        : '—';

    $tnr = $person->teilnehmer_nr ?? '—';

    $originalExam = $request->original_exam_date
        ? \Carbon\Carbon::parse($request->original_exam_date)->format('d.m.Y')
        : '—';

    $requestedExam = $request->scheduled_at
        ? \Carbon\Carbon::parse($request->scheduled_at)->format('d.m.Y H:i')
        : '—';

    $module = $request->module_code ?? '—';
    $instructor = $request->instructor_name ?? '—';
@endphp

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Antrag auf Nachprüfung</title>

    <style>
        /* Großzügige Seitenränder wie Fehlzeiten */
        @page { margin: 56px 56px 64px 56px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #000;
        }

        table { border-collapse: collapse; width: 100%; }

        /* Kopfbereich */
        .header-table td { padding: 2px 4px; vertical-align: middle; }
        .logo img { max-height: 45px; }

        .title-box {
            border: 0.6px solid #000;
            text-align: center;
            padding: 6px 0;
            font-size: 13px;
            font-weight: bold;
        }
        .title-sub {
            border-top: 0.6px solid #000;
            padding: 4px 0 2px 0;
            font-size: 11px;
        }

        /* Meta-Felder */
        .meta-table td {
            border: 0.6px solid #000;
            padding: 6px 5px;
            font-size: 10px;
        }
        .meta-label { width: 35%; background: #fafafa; font-weight: bold; }

        .block { margin-top: 18px; }
        .block-title { font-weight: bold; margin-bottom: 6px; }
        .block-box {
            border: 0.6px solid #000;
            padding: 8px 6px;
            min-height: 32px;
        }

        /* Checkbox-Bereich */
        .checkbox-table td { padding: 4px 2px; font-size: 10px; }
        .checkbox { width: 13px; height: 13px; border: 0.6px solid #000; display:inline-block; margin-right:6px; }

        /* Unterschriften-Block */
        .signature-wrapper { margin-top: 30px; }
        .signature-row td { padding-top: 2px; vertical-align: top; }

        .signature-line {
            border-bottom: 0.6px solid #000;
            margin: 4px 0;
        }

        .cbw-section {
            margin-top: 35px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>

{{-- HEADER --}}
<table class="header-table" style="margin-bottom: 22px;">
    <tr>
        {{-- Titelkasten --}}
        <td style="width: 55%;">
            <div class="title-box">
                Formblatt
                <div class="title-sub">Antrag auf Nachprüfung</div>
            </div>
        </td>

        {{-- Logo --}}
        <td style="width: 45%; text-align:right;" class="logo">
            @php $logoPath = public_path('site-images/logo.png'); @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="Logo">
            @endif
        </td>
    </tr>
</table>

{{-- META-TABELLE — analog zur Vorlage --}}
<table class="meta-table">
    <tr>
        <td class="meta-label">Name</td>
        <td>{{ $name }}</td>
    </tr>
    <tr>
        <td class="meta-label">Klasse</td>
        <td>{{ $klasse }}</td>
    </tr>
    <tr>
        <td class="meta-label">TN-Nummer</td>
        <td>{{ $tnr }}</td>
    </tr>
</table>

{{-- Auswahlfelder (wie Vorlage: mit „X“ gesetzt, sonst leer) --}}
<div class="block">
    <table class="checkbox-table">
        <tr>
            <td>
                <span class="checkbox">@if($request->exam_modality === 'retake') X @endif</span>
                eine Nach- / Wiederholungsprüfung – 20,00 € (*)(**)
            </td>
        </tr>

        <tr>
            <td>
                <span class="checkbox">@if($request->exam_modality === 'improvement') X @endif</span>
                eine Nachprüfung zwecks Ergebnisverbesserung – 40,00 € (**)
            </td>
        </tr>
    </table>
</div>

{{-- Prüfungstermin --}}
<table class="meta-table" style="margin-top: 18px;">
    <tr>
        <td class="meta-label">zum Nachprüfungstermin</td>
        <td>{{ $requestedExam }}</td>
    </tr>

    <tr>
        <td class="meta-label">für den Baustein</td>
        <td>{{ $module }}</td>
    </tr>

    <tr>
        <td class="meta-label">Instruktor/Dozent</td>
        <td>{{ $instructor }}</td>
    </tr>

    <tr>
        <td class="meta-label">ursprüngliche Prüfung war am</td>
        <td>{{ $originalExam }}</td>
    </tr>
</table>

{{-- Begründung --}}
<div class="block">
    <div class="block-title">Begründung</div>
    <table class="checkbox-table">
        <tr>
            <td>
                <span class="checkbox">@if($request->reason === 'unter51') X @endif</span>
                ursprüngliche Prüfung unter 51 Punkte
            </td>
        </tr>

        <tr>
            <td>
                <span class="checkbox">@if($request->reason === 'krankMitAtest') X @endif</span>
                Krankheit am Prüfungstag, mit Attest
            </td>
        </tr>

        <tr>
            <td>
                <span class="checkbox">@if($request->reason === 'krankOhneAtest') X @endif</span>
                Krankheit am Prüfungstag, ohne Attest
            </td>
        </tr>
    </table>
</div>

{{-- UNTERSCHRIFT ebenfalls identisch zur Vorlage + Fehlzeiten --}}
<div class="signature-wrapper">

    {{-- obere Zeile --}}
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:50%; padding:2px 0;">
                Köln - {{ now()->format('d.m.Y - H:i') }}
            </td>
            <td style="width:50%; text-align:right; padding:2px 0;">
                {{ $name }}
            </td>
        </tr>

        {{-- Strich --}}
        <tr>
            <td colspan="2">
                <div class="signature-line"></div>
            </td>
        </tr>

        {{-- untere Zeile --}}
        <tr>
            <td>Ort – Datum</td>
            <td style="text-align:right;">Unterschrift</td>
        </tr>
    </table>

</div>

{{-- CBW Bereich --}}
<div class="cbw-section">Wird von CBW ausgefüllt!</div>

<table class="checkbox-table">
    <tr>
        <td><span class="checkbox"></span> CBW, Attest liegt vor</td>
    </tr>
    <tr>
        <td><span class="checkbox"></span> CBW, Betrag erhalten</td>
    </tr>
</table>

</body>
</html>
