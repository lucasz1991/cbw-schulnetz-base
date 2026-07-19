<?php

namespace Tests\Unit;

use App\Livewire\Auth\Login;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class LoginParticipantContractWindowTest extends TestCase
{
    public function test_adjacent_contract_access_windows_are_merged_without_a_login_gap(): void
    {
        $windows = $this->loginComponent()->windows(collect([
            $this->personWithContracts([
                [
                    'teilnehmer_id' => '5-004570200',
                    'vertrag_beginn' => '2025/07/21',
                    'vertrag_ende' => '2026/07/17',
                    'kuendig_zum' => '',
                    'is_active' => false,
                ],
                [
                    'teilnehmer_id' => '5-004570201',
                    'vertrag_beginn' => '2026/08/03',
                    'vertrag_ende' => '2028/07/19',
                    'kuendig_zum' => '',
                    'is_active' => true,
                ],
            ]),
        ]), 14, 7);

        $this->assertCount(1, $windows);
        $this->assertSame('2025-07-07', $windows->first()['access_from']->toDateString());
        $this->assertSame('2028-07-26', $windows->first()['access_until']->toDateString());
    }

    public function test_unrelated_contract_periods_remain_separate(): void
    {
        $windows = $this->loginComponent()->windows(collect([
            $this->personWithContracts([
                [
                    'teilnehmer_id' => '5-10000',
                    'vertrag_beginn' => '2025/01/01',
                    'vertrag_ende' => '2025/02/01',
                ],
                [
                    'teilnehmer_id' => '5-10001',
                    'vertrag_beginn' => '2026/01/01',
                    'vertrag_ende' => '2026/02/01',
                ],
            ]),
        ]), 14, 7);

        $this->assertCount(2, $windows);
    }

    private function loginComponent(): object
    {
        return new class extends Login
        {
            public function windows(Collection $persons, int $openBeforeDays, int $closeAfterDays): Collection
            {
                return $this->resolveParticipantContractAccessWindows(
                    $persons,
                    $openBeforeDays,
                    $closeAfterDays
                );
            }
        };
    }

    private function personWithContracts(array $contracts): object
    {
        return new class($contracts)
        {
            public array $programdata = [];

            public array $statusdata;

            public function __construct(array $contracts)
            {
                $this->statusdata = ['vertraege' => $contracts];
            }
        };
    }
}
