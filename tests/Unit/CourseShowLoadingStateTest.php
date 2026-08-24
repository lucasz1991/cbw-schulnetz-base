<?php

namespace Tests\Unit;

use App\Livewire\User\Program\Course\CourseShow;
use App\Models\Person;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * DB-loser Vertrag fuer den zeitlich begrenzten Kurs-Sync-Wartezustand.
 * Die lokale Entwicklungsdatenbank darf durch diesen Test nicht beruehrt werden.
 */
class CourseShowLoadingStateTest extends TestCase
{
    public function test_only_a_course_from_the_current_program_can_enter_the_wait_state(): void
    {
        $person = new Person;
        $person->programdata = [
            'tn_baust' => [[
                'klassen_id' => 'CLASS-42',
                'langbez' => 'Java Grundlagen',
            ]],
        ];
        $person->statusdata = [];

        $component = new CourseShow;
        $component->klassenId = 'CLASS-42';

        $this->assertTrue($this->invoke($component, 'isExpectedProgramCourse', $person));
        $this->assertSame('Java Grundlagen', $this->invoke($component, 'pendingCourseTitleFor', $person));

        $component->klassenId = 'FOREIGN-99';

        $this->assertFalse($this->invoke($component, 'isExpectedProgramCourse', $person));
        $this->assertSame('Baustein', $this->invoke($component, 'pendingCourseTitleFor', $person));
    }

    public function test_wait_deadline_uses_a_plausible_click_timestamp_and_never_exceeds_ten_seconds(): void
    {
        $component = new CourseShow;
        $nowMs = 2_000_000;

        $this->assertSame(
            $nowMs - 2_500,
            $this->invoke($component, 'normalizeLoadingStartedAtMilliseconds', $nowMs - 2_500, $nowMs)
        );
        $this->assertSame(
            $nowMs,
            $this->invoke($component, 'normalizeLoadingStartedAtMilliseconds', $nowMs + 1, $nowMs)
        );
        $this->assertSame(
            $nowMs,
            $this->invoke($component, 'normalizeLoadingStartedAtMilliseconds', $nowMs - 60_001, $nowMs)
        );

        $component->courseLoadingStartedAtMs = $nowMs - 9_999;
        $this->assertFalse($this->invoke($component, 'courseWaitTimedOut', $nowMs));

        $component->courseLoadingStartedAtMs = $nowMs - 10_000;
        $this->assertTrue($this->invoke($component, 'courseWaitTimedOut', $nowMs));
    }

    public function test_views_contain_the_responsive_timed_loader_and_friendly_fallback_actions(): void
    {
        $programView = file_get_contents(__DIR__.'/../../resources/views/livewire/user/program-show.blade.php');
        $courseView = file_get_contents(__DIR__.'/../../resources/views/livewire/user/program/course/course-show.blade.php');

        $this->assertIsString($programView);
        $this->assertIsString($courseView);
        $this->assertStringContainsString('data-course-navigation="true"', $programView);
        $this->assertStringContainsString('}, 3000)', $programView);
        $this->assertStringContainsString('}, 10000)', $programView);
        $this->assertStringContainsString('motion-reduce:animate-none', $programView);
        $this->assertStringContainsString('wire:poll.500ms="pollCourse"', $courseView);
        $this->assertStringContainsString('Seite wird geladen...', $courseView);
        $this->assertStringContainsString('Baustein noch nicht verfügbar', $courseView);
        $this->assertStringContainsString('Erneut laden', $courseView);
        $this->assertStringContainsString('Abbrechen', $courseView);
        $this->assertStringNotContainsString('404', $courseView);
    }

    private function invoke(CourseShow $component, string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod($component, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($component, ...$arguments);
    }
}
