<?php

namespace Tests\Unit;

use Tests\TestCase;

class AttendanceDisplayViewTest extends TestCase
{
    public function test_tutor_attendance_view_compiles_with_split_status_and_clock_labels(): void
    {
        $path = resource_path('views/components/ui/tutor/course/show-date-attendance.blade.php');
        $source = file_get_contents($path);
        $compiled = app('blade.compiler')->compileString($source);

        $this->assertNotSame('', trim($compiled));
        $this->assertStringContainsString('Start:', $source);
        $this->assertStringContainsString('Ende:', $source);
        $this->assertStringContainsString('Verspätet:', $source);
        $this->assertStringContainsString('Gegangen:', $source);
        $this->assertStringNotContainsString('min spät', $source);
        $this->assertStringNotContainsString('min früher', $source);
    }
}
