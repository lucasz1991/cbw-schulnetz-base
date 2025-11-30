<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Entschuldigung von Fehlzeiten</title>
    <style>
        @page { margin: 20px 20px 30px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .header-table td { padding: 2px 4px; vertical-align: middle; }
        .logo-cell img { max-height: 40px; }
        .title-cell { text-align: center; font-weight: bold; font-size: 14px; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta-table td { padding: 3px 4px; vertical-align: top; }
        .label { width: 80px; font-weight: bold; }

        .block { margin-top: 8px; }
        .block-title { font-weight: bold; margin-bottom: 4px; }
        .line { border-bottom: 0.4px solid #000; padding-bottom: 2px; margin-bottom: 6px; }
        .footer { margin-top: 20px; font-size: 9px; }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td class="logo-cell">
            @php
                $logoPath = public_path('site-images/logo.png');
            @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="Logo">
            @endif
        </td>
        <td class="title-cell">
            Formblatt – Entschuldigung von Fehlzeiten
        </td>
        <td style="text-align:right; font-size:9px;">
            CBW GmbH
        </td>
    </tr>
</table>

@php
    $user   = $user ?? $request->user ?? null;
    $course = $course ?? $request->course ?? null;

    $name   = $user?->person
        ? ($user->person->nachname . ', ' . $user->person->vorname)
        : ($user?->name ?? '—');

    $klasse = $course->klassen_id ?? '—';

    $date   = optional($request->absence_date ?? $request->created_at)->format('d.m.Y');
@endphp

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

<div class="block">
    <div class="block-title">Grund der Abwesenheit:</div>
    <div class="line">
        {{ $request->reason ?? '—' }}
    </div>
</div>

<div class="footer">
    {{ $request->place ?? 'Köln' }} - {{ optional($request->created_at)->format('d.m.Y - H:i') }}
    &nbsp;&nbsp;&nbsp;&nbsp;
    {{ $name }}<br>
    Ort - Datum &nbsp;&nbsp;&nbsp;&nbsp; Unterschrift
</div>

</body>
</html>
