@php
    // Kurzhelfer pro Spaltenindex (kommt von deiner Table-Komponente)
    $hc = fn($i) => $hideClass($columnsMeta[$i]['hideOn'] ?? 'none');

    /** @var \App\Models\UserRequest $item */

    // Typ-Label
    $typeLabel = [
        'makeup'          => 'Nachprüfung (intern)',
        'external_makeup' => 'Nachprüfung (extern)',
        'absence'         => 'Fehlzeit',
        'general'         => 'Allgemein',
    ][$item->type] ?? ucfirst($item->type);

    // Zeitraum / Termin
    $isAbsence = $item->type === 'absence';
    $dateFrom  = $item->date_from;  // cast: date
    $dateTo    = $item->date_to;    // cast: date
    $sched     = $item->scheduled_at; // cast: datetime (tz)

    $dateFromLbl = optional($dateFrom)->format('d.m.Y');
    $dateToLbl   = optional($dateTo)->format('d.m.Y');
    $schedLbl    = $sched ? $sched->timezone(config('app.timezone'))->format('d.m.Y H:i') : null;

    // Absence-Zeitdetails
    $fullDay     = (bool) $item->full_day;
    $late        = $item->time_arrived_late ? substr($item->time_arrived_late, 0, 5) : null;
    $leftEarly   = $item->time_left_early ? substr($item->time_left_early, 0, 5) : null;

    // Status-Badge
    $status      = $item->status ?? 'pending';
    $statusLabel = [
        'pending'   => 'Eingereicht',
        'in_review' => 'In Prüfung',
        'approved'  => 'Genehmigt',
        'rejected'  => 'Abgelehnt',
        'canceled'  => 'Storniert',
    ][$status] ?? ucfirst($status);
@endphp

{{-- 0: Typ --}}
<div class="px-2 py-2 flex items-center justify-between gap-2 pr-4 {{ $hc(0) }}">
    <div class="font-semibold truncate">{{ $typeLabel }}</div>
</div>

{{-- 1: Zeitraum/Termin --}}
<div class="px-2 py-2 text-xs text-gray-600 {{ $hc(1) }}">
    @if($isAbsence)
        @if($dateFromLbl)
            <span class="text-gray-900">{{ $dateFromLbl }}</span>
            <div class="text-xs text-gray-500">
                @if(!$fullDay && ($late || $leftEarly))
                    @if($late) später ab {{ $late }} @endif
                    @if($late && $leftEarly) · @endif
                    @if($leftEarly) früher bis {{ $leftEarly }} @endif
                @else
                    ganztägig
                @endif
            </div>
        @else
            <span class="text-gray-400">—</span>
        @endif
    @else
        @if($schedLbl)
            <span class="text-gray-900">{{ $schedLbl }}</span>
        @elseif($dateFrom || $dateTo)
            <span class="text-gray-900">{{ $dateFromLbl ?? '—' }}</span>
            <span>–</span>
            <span class="text-gray-900">{{ $dateToLbl ?? '—' }}</span>
        @else
            <span class="text-gray-400">—</span>
        @endif
    @endif
</div>

{{-- 2: Status --}}
<div class="px-2 py-2 flex items-center gap-2 {{ $hc(2) }}">
    <div class="">
        <span class="px-2 py-1 text-xs font-semibold rounded
            @class([
                'bg-yellow-100 text-yellow-800' => $status === 'pending',
                'bg-blue-100 text-blue-800'     => $status === 'in_review',
                'bg-green-100 text-green-700'   => $status === 'approved',
                'bg-red-100 text-red-700'       => $status === 'rejected',
                'bg-gray-100 text-gray-700'     => $status === 'canceled',
            ])">
            {{ $statusLabel }}
        </span>
    </div>
</div>
