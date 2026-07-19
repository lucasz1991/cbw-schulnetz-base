<?php

namespace Tests\Unit;

use Tests\TestCase;

class AttendanceDisplayViewTest extends TestCase
{
    public function test_tutor_attendance_view_compiles_with_uniform_icon_status_badges(): void
    {
        $path = resource_path('views/components/ui/tutor/course/show-date-attendance.blade.php');
        $source = file_get_contents($path);
        $compiled = app('blade.compiler')->compileString($source);

        $this->assertNotSame('', trim($compiled));
        $this->assertStringContainsString('fad fa-sunrise', $source);
        $this->assertStringContainsString('fad fa-sunset', $source);
        $this->assertStringContainsString('w-28 shrink-0', $source);
        $this->assertStringContainsString('w-44 shrink-0', $source);
        $this->assertStringContainsString('Gekommen um', $source);
        $this->assertStringContainsString('Gegangen um', $source);
        $this->assertStringNotContainsString('>Start</span>', $source);
        $this->assertStringNotContainsString('>Ende</span>', $source);
        $this->assertStringNotContainsString('min spät', $source);
        $this->assertStringNotContainsString('min früher', $source);
    }
}
