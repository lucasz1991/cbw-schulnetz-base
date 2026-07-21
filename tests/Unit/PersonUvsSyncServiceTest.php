<?php

namespace Tests\Unit;

use App\Services\ApiUvs\PersonUvsSyncService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PersonUvsSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 19, 12, 0, 0, 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ended_participant_contract_is_not_active_even_when_api_flag_is_true(): void
    {
        $service = new class extends PersonUvsSyncService
        {
            public function hasActiveContract(array $statusData): bool
            {
                return $this->hasActiveParticipantContract($statusData, 14, 7);
            }
        };

        $this->assertFalse($service->hasActiveContract([
            'status' => 'Absolvent',
            'status_short' => 'AB',
            'vertraege' => [[
                'vertrag_ende' => '2023/07/21',
                'kuendig_zum' => '',
                'is_active' => true,
            ]],
        ]));
    }

    public function test_future_participant_contract_is_active_inside_its_pre_opening_window(): void
    {
        $service = new class extends PersonUvsSyncService
        {
            public function hasActiveContract(array $statusData): bool
            {
                return $this->hasActiveParticipantContract($statusData, 14, 7);
            }
        };

        $this->assertTrue($service->hasActiveContract([
            'status' => 'Absolvent',
            'status_short' => 'AB',
            'vertraege' => [[
                'vertrag_beginn' => '2026/08/01',
                'vertrag_ende' => '2028/07/21',
                'kuendig_zum' => '',
                'is_active' => true,
            ]],
        ]));
    }

    public function test_ended_contract_remains_active_during_its_grace_period(): void
    {
        $service = new class extends PersonUvsSyncService
        {
            public function hasActiveContract(array $statusData): bool
            {
                return $this->hasActiveParticipantContract($statusData, 14, 7);
            }
        };

        $this->assertTrue($service->hasActiveContract([
            'status' => 'Absolvent',
            'status_short' => 'AB',
            'vertraege' => [[
                'vertrag_beginn' => '2024/07/18',
                'vertrag_ende' => '2026/07/17',
                'kuendig_zum' => '',
                'is_active' => false,
            ]],
        ]));
    }
}
