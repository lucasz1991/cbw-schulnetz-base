<?php

namespace App\Services\Coaching;

use App\Models\CoachingContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScheduleGenerator
{
    /** Build a complete draft only. Publishing and both consents still use PlanService. */
    public function generate(CoachingContract $contract, array $options): array
    {
        $data = validator(['schedule' => $options], [
            'schedule.start_date' => 'required|date_format:Y-m-d',
            'schedule.weekdays' => 'required|array|min:1|max:7',
            'schedule.weekdays.*' => 'required|integer|between:1,7|distinct',
            'schedule.start_time' => 'required|date_format:H:i',
            'schedule.units_per_appointment' => 'required|integer|min:1|max:720',
            'schedule.every_weeks' => 'required|integer|between:1,12',
            'schedule.topic' => 'required|string|max:255',
            'schedule.format' => 'required|in:online,presence',
            'schedule.location' => 'required|string|max:255',
        ], [], [
            'schedule.start_date' => 'Startdatum', 'schedule.weekdays' => 'Wochentage',
            'schedule.start_time' => 'Beginn', 'schedule.units_per_appointment' => 'UE je Termin',
            'schedule.every_weeks' => 'Wochenrhythmus', 'schedule.topic' => 'Thema / Inhalt',
            'schedule.location' => 'Ort / Besprechungslink',
        ])->validate()['schedule'];

        $remaining = $contract->agreed_minutes;
        $duration = (int)$data['units_per_appointment'] * $contract->unit_minutes;
        if ($remaining < 1 || $duration < 1 || $duration > 720) {
            $this->fail('units_per_appointment', 'Ein Termin muss zwischen einer Unterrichtseinheit und 12 Stunden dauern.');
        }
        if ((int)ceil($remaining / $duration) > 255) {
            $this->fail('units_per_appointment', 'Es wären mehr als 255 Termine nötig. Bitte mehr UE je Termin wählen.');
        }
        $startMinutes = (int)substr($data['start_time'], 0, 2) * 60 + (int)substr($data['start_time'], 3, 2);
        if ($startMinutes + min($duration, $remaining) >= 1440) {
            $this->fail('start_time', 'Jeder Termin muss vor Mitternacht enden. Bitte früher beginnen oder weniger UE je Termin wählen.');
        }

        // Calendar arithmetic in UTC avoids DST changing a weekday or the weekly rhythm.
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['start_date'], 'UTC');
        if ($data['start_date'] < now('Europe/Berlin')->toDateString()
            || ($contract->valid_from && $data['start_date'] < $contract->valid_from->toDateString())) {
            $this->fail('start_date', 'Das Startdatum muss ab heute und innerhalb des Vertragszeitraums liegen.');
        }
        $anchor = $date->startOfWeek();
        $weekdays = array_map('intval', $data['weekdays']);
        $items = [];
        while ($remaining > 0) {
            if ($contract->valid_until && $date->toDateString() > $contract->valid_until->toDateString()) {
                $this->fail('start_date', 'Der vollständige Umfang passt mit dieser Verteilung nicht in den Vertragszeitraum. Bitte früher beginnen, mehr Wochentage oder mehr UE je Termin wählen.');
            }
            $week = intdiv((int)$anchor->diffInDays($date), 7);
            if ($week % (int)$data['every_weeks'] === 0 && in_array($date->dayOfWeekIso, $weekdays, true)) {
                $minutes = min($duration, $remaining);
                $end = $startMinutes + $minutes;
                $items[] = ['id' => (string)Str::uuid(), 'date' => $date->toDateString(),
                    'start' => $data['start_time'], 'end' => sprintf('%02d:%02d', intdiv($end, 60), $end % 60),
                    'topic' => trim($data['topic']), 'format' => $data['format'], 'location' => trim($data['location'])];
                $remaining -= $minutes;
            }
            $date = $date->addDay();
        }

        // Reuse the same date, total-duration and DST rules as manual planning.
        $items = app(PlanValidator::class)->validate($items, $contract->agreed_minutes,
            $contract->valid_from?->toDateString(), $contract->valid_until?->toDateString());
        if ($items[0]['starts_at'] <= now('UTC')->format('Y-m-d H:i:s')) {
            $this->fail('start_time', 'Der erste Vorschlag liegt bereits in der Vergangenheit. Bitte Startdatum oder Beginn ändern.');
        }
        return $items;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages(['schedule.'.$field => $message]);
    }
}
