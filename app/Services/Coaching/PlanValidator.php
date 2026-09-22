<?php

namespace App\Services\Coaching;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlanValidator
{
    /** Validate the entire version, never an individual booking in isolation. */
    public function validate(array $items, int $agreedMinutes, ?string $from = null, ?string $until = null): array
    {
        Validator::make(['items' => $items], [
            'items' => 'required|array|min:1|max:255',
            'items.*.id' => 'required|uuid|distinct',
            'items.*.date' => 'required|date_format:Y-m-d',
            'items.*.start' => 'required|date_format:H:i',
            'items.*.end' => 'required|date_format:H:i',
            'items.*.topic' => 'required|string|max:255',
            'items.*.format' => 'required|in:online,presence',
            'items.*.location' => 'required|string|max:255',
        ])->validate();

        $normalized = [];
        $total = 0;
        $timezone = new DateTimeZone('Europe/Berlin');
        foreach ($items as $item) {
            if (($from && $item['date'] < $from) || ($until && $item['date'] > $until)) {
                $this->fail('Alle Termine müssen innerhalb des Vertragszeitraums liegen.');
            }
            $times = [];
            foreach (['start', 'end'] as $key) {
                $text = $item['date'].' '.$item[$key];
                $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $text, $timezone);
                if (! $time || $time->format('Y-m-d H:i') !== $text) {
                    $this->fail('Eine Uhrzeit existiert wegen der Zeitumstellung nicht. Bitte einen eindeutigen Zeitpunkt wählen.');
                }
                // During the repeated hour the local clock alone cannot identify the intended instant.
                foreach ($timezone->getTransitions($time->getTimestamp() - 7200, $time->getTimestamp() + 7200) ?: [] as $transition) {
                    if ($transition['ts'] !== $time->getTimestamp() - 7200 && $item[$key] >= '02:00' && $item[$key] < '03:00' && ! $transition['isdst']) {
                        $this->fail('Termine in der doppelten Stunde der Zeitumstellung sind nicht eindeutig. Bitte eine andere Uhrzeit wählen.');
                    }
                }
                $times[$key] = $time;
            }
            $minutes = (int) (($times['end']->getTimestamp() - $times['start']->getTimestamp()) / 60);
            if ($minutes <= 0 || $minutes > 720) $this->fail('Das Ende muss nach dem Beginn liegen; maximal 12 Stunden pro Termin.');
            $normalized[] = array_intersect_key($item, array_flip(['id', 'date', 'start', 'end', 'topic', 'format', 'location'])) + [
                'starts_at' => $times['start']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'ends_at' => $times['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'minutes' => $minutes,
            ];
            $total += $minutes;
        }
        usort($normalized, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
        for ($i = 1; $i < count($normalized); $i++) {
            if ($normalized[$i]['starts_at'] < $normalized[$i - 1]['ends_at']) $this->fail('Termine innerhalb des Gesamtplans überschneiden sich.');
        }
        if ($total !== $agreedMinutes) {
            $this->fail("Der Gesamtplan umfasst {$total} Minuten. Vereinbart sind {$agreedMinutes} Minuten; alle Termine müssen vor Beginn vollständig geplant sein.");
        }
        return $normalized;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['items' => $message]);
    }
}
