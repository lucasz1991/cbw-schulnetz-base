<?php

namespace Tests\Unit;

use App\Services\ApiUvs\ApiUvsService;
use App\Services\ApiUvs\CourseApiServices\CourseResultsSyncService;
use PHPUnit\Framework\TestCase;

class CourseResultStatusRequirementTest extends TestCase
{
    public function test_participated_status_requires_points(): void
    {
        $service = $this->service();

        $this->assertTrue($service->localStatusRequiresResult('+'));
        $this->assertTrue($service->localStatusRequiresResult('an_pruefung_teilgenommen'));
    }

    public function test_zero_is_a_valid_entered_result(): void
    {
        $service = $this->service();

        $this->assertSame(0, $service->normalizeLocalResult(0, '+'));
        $this->assertNull($service->normalizeLocalResult(null, '+'));
        $this->assertNull($service->normalizeLocalResult('', '+'));
    }

    public function test_statuses_without_results_keep_their_existing_rules(): void
    {
        $service = $this->service();

        $this->assertFalse($service->localStatusRequiresResult('-'));
        $this->assertFalse($service->localStatusRequiresResult('V'));
        $this->assertFalse($service->localStatusRequiresResult('I'));
        $this->assertSame(0, $service->normalizeLocalResult(null, '-'));
        $this->assertSame(0, $service->normalizeLocalResult(null, 'V'));
        $this->assertNull($service->normalizeLocalResult(null, 'I'));
    }

    private function service(): CourseResultsSyncService
    {
        return new CourseResultsSyncService($this->createStub(ApiUvsService::class));
    }
}
