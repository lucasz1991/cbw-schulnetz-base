{{-- resources/views/pdf/requests/external-exam.blade.php --}}
@php
    /** @var \App\Models\User|null $user */
    /** @var \App\Models\Course|null $course */
    $user   = $user   ?? ($request->user   ?? null);
    $course = $course ?? ($request->course ?? null);
    $person = $user?->person;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Anmeldung zur externen Prüfung</title>
    <style>
        @page {
            margin: 20px 25px 30px 25px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .header-table td {
            padding: 4px 6px;
            vertical-align: middle;
        }

        .header-left {
            width: 25%;
        }

        .header-left img {
            max-height: 40px;
        }

        .header-center {
            width: 50%;
            text-align: center;
            font-size: 14px;
            font-weight: bold;
        }

        .header-right {
            width: 25%;
            text-align: right;
            font-size: 9px;
        }

        .meta-table {
            margin-top: 12px;
            border: 0.4px solid #000;
        }

        .meta-table td {
            padding: 4px 6px;
            font-size: 9px;
        }

        .meta-label {
            width: 30%;
            font-weight: bold;
            background: #f5f5f5;
        }

        .meta-value {
            width: 70%;
        }

        .section-title {
            margin-top: 14px;
            font-size: 11px;
            font-weight: bold;
            text-decoration: underline;
        }

        .text-block {
            margin-top: 8px;
            font-size: 9px;
            line-height: 1.4;
            text-align: justify;
        }

        .signature-table {
            margin-top: 30px;
        }

        .signature-table td {
            padding-top: 25px;
            font-size: 9px;
            text-align: center;
        }

        .signature-line {
            border-top: 0.4px solid #000;
            padding-top: 2px;
        }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td class="header-left">
            <img src="{{ public_path('site-images/logo.png') }}" alt="Logo">
        </td>
        <td class="header-center">
            Anmeldung zur externen Prüfung
        </td>
        <td class="header-right">
            Datum: {{ now()->format('d.m.Y') }}<br>
            @if($course?->klassen_id)
                Klasse: {{ $course->klassen_id }}
            @endif
        </td>
    </tr>
</table>

<table class="meta-table">
    <tr>
        <td class="meta-label">Teilnehmer/-in</td>
        <td class="meta-value">
            {{ trim(($person->nachname ?? $user->name ?? '') . ', ' . ($person->vorname ?? '')) ?: '—' }}
        </td>
    </tr>
    <tr>
        <td class="meta-label">Geburtsdatum</td>
        <td class="meta-value">
            @if(!empty($person?->geburt_datum))
                {{ optional($person->geburt_datum)->format('d.m.Y') }}
            @else
                —
            @endif
        </td>
    </tr>
    <tr>
        <td class="meta-label">Teilnehmer-Nr.</td>
        <td class="meta-value">
            {{ $person->teilnehmer_nr ?? '—' }}
        </td>
    </tr>
    <tr>
        <td class="meta-label">Baustein / Kurs</td>
        <td class="meta-value">
            {{ $course?->courseShortName ?: $course?->title ?: '—' }}
        </td>
    </tr>
    <tr>
        <td class="meta-label">Klasse</td>
        <td class="meta-value">
            {{ $course?->courseClassName ?: $course?->klassen_id ?: '—' }}
        </td>
    </tr>
    <tr>
        <td class="meta-label">Prüfungsinstitution</td>
        <td class="meta-value">
            {{ $request->external_institution ?? '—' }}
        </td>
    </tr>
    <tr>
        <td class="meta-label">Externe Prüfungsbezeichnung</td>
        <td class="meta-value">
            {{ $request->external_exam_name ?? '—' }}
        </td>
    </tr>
    <tr>
        <td class="meta-label">Prüfungstermin extern</td>
        <td class="meta-value">
            @if(!empty($request->external_exam_date))
                {{ \Carbon\Carbon::parse($request->external_exam_date)->format('d.m.Y') }}
            @else
                —
            @endif
        </td>
    </tr>
</table>

<div class="section-title">
    Erklärung
</div>

<div class="text-block">
    Hiermit beantrage ich die Anmeldung zur oben genannten externen Prüfung.
    Mir ist bewusst, dass die Durchführung, Bewertung und Terminierung der
    Prüfung durch die angegebene externe Institution erfolgt und die
    Teilnahmevoraussetzungen dort festgelegt sind. Die notwendigen Unterlagen
    wurden bzw. werden vollständig und fristgerecht eingereicht.
</div>

@if(!empty($request->reason))
    <div class="section-title">
        Zusätzliche Hinweise / Begründung
    </div>

    <div class="text-block">
        {!! nl2br(e($request->reason)) !!}
    </div>
@endif

<table class="signature-table">
    <tr>
        <td style="width: 50%;">
            <div class="signature-line">
                Ort, Datum (Teilnehmer/-in)
            </div>
        </td>
        <td style="width: 50%;">
            <div class="signature-line">
                Unterschrift Teilnehmer/-in
            </div>
        </td>
    </tr>
</table>

</body>
</html>
