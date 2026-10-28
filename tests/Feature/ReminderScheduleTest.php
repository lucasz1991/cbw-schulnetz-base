<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReminderScheduleTest extends TestCase
{
    public static function reminderSchedules(): array
    {
        return [
            'report books' => ['reportbooks:dispatch-missing-reminders', '30 10 * * 1'],
            'lecturers' => ['courses:dispatch-lecturer-reminders', '30 9 * * *'],
        ];
    }

    #[DataProvider('reminderSchedules')]
    public function test_reminders_keep_their_schedule_and_skip_an_active_run(string $command, string $expression): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', $command));

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame($expression, $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertFalse($event->shouldSkipDueToOverlapping());

        try {
            $this->assertTrue($event->shouldSkipDueToOverlapping());
        } finally {
            $event->mutex->forget($event);
        }

        $this->assertFalse($event->shouldSkipDueToOverlapping());
        $event->mutex->forget($event);
    }
}
