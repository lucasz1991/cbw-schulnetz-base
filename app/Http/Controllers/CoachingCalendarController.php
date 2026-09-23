<?php

namespace App\Http\Controllers;

use App\Models\CoachingContract;
use App\Services\Coaching\Access;
use Carbon\Carbon;

class CoachingCalendarController extends Controller
{
    public function __invoke(int $contract)
    {
        abort_unless(Access::available(), 404);
        $case = CoachingContract::forUser(auth()->user())->findOrFail($contract);
        abort_unless($case->course_id && $case->confirmedPlan, 404);
        $escape = fn ($value) => str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", "\\;", "\\,", '', '\\n'], $value);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//CBW//Einzelcoaching//DE', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
        foreach ($case->confirmedPlan->items as $item) {
            array_push($lines, 'BEGIN:VEVENT', 'UID:'.$item['id'].'@coaching.cbw', 'SEQUENCE:'.$case->confirmedPlan->revision,
                'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
                'DTSTART:'.Carbon::parse($item['starts_at'], 'UTC')->format('Ymd\THis\Z'),
                'DTEND:'.Carbon::parse($item['ends_at'], 'UTC')->format('Ymd\THis\Z'),
                'SUMMARY:'.$escape($case->title.' – '.$item['topic']), 'LOCATION:'.$escape($item['location']),
                'STATUS:'.($case->contract_status !== 'active' || !$case->hasCurrentConfirmedPlan()
                    || ($case->cancelled_on && $item['date'] > $case->cancelled_on->toDateString())
                    || ($case->valid_until && $item['date'] > $case->valid_until->toDateString()) ? 'CANCELLED' : 'CONFIRMED'), 'BEGIN:VALARM', 'TRIGGER:-PT15M', 'ACTION:DISPLAY',
                'DESCRIPTION:Einzelcoaching beginnt in 15 Minuten', 'END:VALARM', 'END:VEVENT');
        }
        $lines[] = 'END:VCALENDAR';
        // Fold at 75 octets without breaking UTF-8 characters (RFC 5545).
        $folded = [];
        foreach ($lines as $line) {
            while (strlen($line) > 75) { $part = mb_strcut($line, 0, 75, 'UTF-8'); $folded[] = $part; $line = ' '.substr($line, strlen($part)); }
            $folded[] = $line;
        }
        return response(implode("\r\n", $folded)."\r\n", 200, ['Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="einzelcoaching.ics"', 'Cache-Control' => 'private, no-store']);
    }
}
