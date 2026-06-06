<?php

namespace Tests\Unit;

use App\Services\ApiUvs\PersonUvsSyncService;
use PHPUnit\Framework\TestCase;

class PersonUvsSyncServiceTest extends TestCase
{
    public function test_ended_participant_contract_is_not_active_even_when_api_flag_is_true(): void
    {
        $service = new class extends PersonUvsSyncService {
            public function hasActiveContract(array $statusData): bool
            {
                return $this->hasActiveParticipantContract($statusData);
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

    public function test_future_participant_contract_remains_active(): void
    {
        $service = new class extends PersonUvsSyncService {
            public function hasActiveContract(array $statusData): bool
            {
                return $this->hasActiveParticipantContract($statusData);
            }
        };

        $this->assertTrue($service->hasActiveContract([
            'status' => 'Absolvent',
            'status_short' => 'AB',
            'vertraege' => [[
                'vertrag_ende' => '2099/07/21',
                'kuendig_zum' => '',
                'is_active' => true,
            ]],
        ]));
    }
}
