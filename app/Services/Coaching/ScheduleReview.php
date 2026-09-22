<?php

namespace App\Services\Coaching;

use Carbon\CarbonImmutable;

/** Presentation of an unsaved schedule; dates are calendar days, without timezone conversion. */
class ScheduleReview
{
    public static function date(?string $value): ?CarbonImmutable
    {
        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
            return $date->format('Y-m-d') === $value ? $date->locale('de') : null;
        } catch (\Throwable) { return null; }
    }

    public static function card(array $item, int $unit): array
    {
        $date = self::date($item['date'] ?? null);
        $minutes = 0;
        if (preg_match('/^\d{2}:\d{2}$/D', $item['start'] ?? '') && preg_match('/^\d{2}:\d{2}$/D', $item['end'] ?? '')) {
            $start = explode(':', $item['start']); $end = explode(':', $item['end']);
            $minutes = max(0, ((int)$end[0] - (int)$start[0]) * 60 + (int)$end[1] - (int)$start[1]);
        }
        return ['day' => $date?->format('d') ?? '–', 'month' => $date?->translatedFormat('M') ?? 'offen',
            'date' => $date?->translatedFormat('l, d. F Y') ?? 'Datum festlegen',
            'time' => ($item['start'] ?? '–').'–'.($item['end'] ?? '–'),
            'duration' => $minutes > 0 ? rtrim(rtrim(number_format($minutes / max(1, $unit), 2, ',', '.'), '0'), ',').' UE · '.$minutes.' Min.' : 'Dauer offen'];
    }

    public static function calendar(array $items, string $month = '', string $selected = ''): array
    {
        $dates = array_values(array_filter(array_column($items, 'date'), fn ($date) => self::date($date)));
        sort($dates);
        $anchor = self::date($month.'-01') ?? self::date($dates[0] ?? '') ?? CarbonImmutable::today('UTC')->locale('de');
        $first = $anchor->startOfMonth();
        $selected = self::date($selected)?->format('Y-m-d') ?? ($dates[0] ?? $first->format('Y-m-d'));
        $start = $first->startOfWeek(CarbonImmutable::MONDAY);
        $days = [];
        $cells = (int) ceil(($first->dayOfWeekIso - 1 + $first->daysInMonth) / 7) * 7;
        for ($i = 0; $i < $cells; $i++) {
            $day = $start->addDays($i); $key = $day->format('Y-m-d');
            $events = array_values(array_filter($items, fn ($item) => ($item['date'] ?? '') === $key));
            usort($events, fn ($a, $b) => strcmp($a['start'] ?? '', $b['start'] ?? ''));
            $days[] = ['date' => $key, 'number' => $day->day, 'current' => $day->month === $first->month,
                'selected' => $key === $selected, 'events' => $events,
                'label' => $day->translatedFormat('l, d. F Y').' · '.count($events).' Termine'];
        }
        return ['month' => $first->format('Y-m'), 'title' => $first->translatedFormat('F Y'), 'selected' => $selected,
            'selectedLabel' => self::date($selected)->translatedFormat('l, d. F Y'), 'days' => $days];
    }
}
