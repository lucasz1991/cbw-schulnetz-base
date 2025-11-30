<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Entschuldigung von Fehlzeiten</title>
    <style>
        @page { margin: 20px 20px 30px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .header-table td {
            padding: 2px 4px;
            vertical-align: middle;
        }
        .logo-cell img {
            max-height: 40px;
        }
        .title-cell {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }
        .header-right {
            text-align: right;
            font-size: 9px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .meta-table td {
            padding: 3px 4px;
            vertical-align: top;
        }
        .label {
            width: 120px;
            font-weight: bold;
        }

        .block {
            margin-top: 8px;
            margin-bottom: 6px;
        }
        .block-title {
            font-weight: bold;
            margin-bottom: 4px;
        }
        .line {
            border-bottom: 0.4px solid #000;
            padding-bottom: 2px;
            margin-bottom: 4px;
            min-height: 14px;
        }

        .footer {
            margin-top: 20px;
            font-size: 9px;
        }

        .footer-note {
            margin-top: 18px;
            font-size: 8px;
        }
    </style>
</head>
<body>

@php
    /** @var \App\Models\UserRequest $request */

    $user   = $user   ?? ($request->user ?? null);
    $course = $course ?? ($request->course ?? null);

    // Name
    if ($user?->person) {
        $name = trim(($user->person->nachname ?? '') . ', ' . ($user->person->vorname ?? ''));
        if ($name === ',') {
            $name = $user->name ?? '—';
        }
    } else {
        $name = $user->name ?? '—';
    }

    // Klasse
    $klasse = $request->class_label
        ?? $course->klassen_id
        ?? '—';

    // Datumsfeld oben
    $absenceDate = $request->date_from
        ?? $request->date_to
        ?? $request->submitted_at
        ?? $request->created_at
        ?? now();
    $dateLabel = optional($absenceDate)->format('d.m.Y') ?? '—';

    // Uhrzeit-Felder (falls du Zeiten speicherst)
    $lateTime  = $request->time_arrived_late
        ? $request->time_arrived_late
        : '---';

    $earlyTime = $request->time_left_early
        ? $request->time_left_early
        : '---';

    // Ganztags gefehlt
    $fullDayLabel = is_null($request->full_day)
        ? '—'
        : ($request->full_day ? 'JA' : 'NEIN');

    // Grund der Abwesenheit (Kurzcode + Freitext)
    $reasonItem = $request->reason_item ?? null;

    // Optional: simple Mapping für bekannte Codes
    $reasonItemLabel = match ($reasonItem) {
        'abw_wichtig'            => 'Fehlzeit aus wichtigem Grund',
        'abw_krank_attest'       => 'krank mit Attest',
        'abw_ohne_wichtigen_grund' => 'Fehlzeit ohne wichtigen Grund',
        default                  => $reasonItem,
    };

    $reasonItemLabel = $reasonItemLabel ?: '—';

    // sonstige Begründung / Freitext
    $reasonText = $request->reason ?: '—';

    // Ort / Datum unten
    $place = $request->place ?: 'Köln';
    $createdAtLabel = optional($request->created_at)->format('d.m.Y - H:i') ?? now()->format('d.m.Y - H:i');
@endphp

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
            Formblatt<br>
            Entschuldigung von Fehlzeiten
        </td>
        <td class="header-right">
            CBW GmbH
        </td>
    </tr>
</table>

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
        <td>{{ $dateLabel }}</td>
    </tr>
</table>

<div class="block">
    <div class="line">
        Uhrzeit – später gekommen: {{ $lateTime }}
    </div>
    <div class="line">
        Uhrzeit – früher gegangen: {{ $earlyTime }}
    </div>
    <div class="line">
        ganztags gefehlt: {{ $fullDayLabel }}
    </div>
</div>

<div class="block">
    <div class="block-title">Grund der Abwesenheit:</div>
    <div class="line">
        {{ $reasonItemLabel }}
    </div>

    <div class="block-title">sonst. Begründung:</div>
    <div class="line" style="min-height: 40px;">
        {{ $reasonText }}
    </div>
</div>

<div class="footer">
    {{ $place }} - {{ $createdAtLabel }}
    &nbsp;&nbsp;&nbsp;&nbsp;
    {{ $name }}<br>
    Ort - Datum &nbsp;&nbsp;&nbsp;&nbsp; Unterschrift
</div>

<div class="footer-note">
    Wird von CBW ausgefüllt! – wichtiger Grund / krank mit Attest / ohne wichtigen Grund
</div>

</body>
</html>
