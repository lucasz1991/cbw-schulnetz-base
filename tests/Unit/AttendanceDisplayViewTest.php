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
        $this->assertStringContainsString('fad fa-play-circle', $source);
        $this->assertStringContainsString('fad fa-flag-checkered', $source);
        $this->assertStringContainsString('w-64 flex-col', $source);
        $this->assertStringContainsString('border-t border-slate-300', $source);
        $this->assertStringContainsString('divide-x divide-slate-200', $source);
        $this->assertStringContainsString('Gekommen um', $source);
        $this->assertStringContainsString('Gegangen um', $source);
        $this->assertStringNotContainsString('Keine Zusatzangabe', $source);
        $this->assertStringNotContainsString('rounded-md border border-blue-200', $source);
        $this->assertStringNotContainsString('>Start</span>', $source);
        $this->assertStringNotContainsString('>Ende</span>', $source);
        $this->assertStringNotContainsString('min spät', $source);
        $this->assertStringNotContainsString('min früher', $source);
    }
}
