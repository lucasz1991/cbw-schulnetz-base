<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Support\CurrentParticipantCourseScope;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ParticipantContractSelectionTest extends TestCase
{
    private const OPEN_BEFORE_DAYS = 14;

    private const CLOSE_AFTER_DAYS = 7;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 18, 12, 0, 0, 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_selects_the_first_open_contract_by_begin_date_instead_of_array_order_or_latest_end(): void
    {
        $person = $this->personWithContracts([
            [
                'teilnehmer_id' => '5-004570201',
                'vertrag_beginn' => '2026/02/01',
                'vertrag_ende' => '2027/07/31',
                'is_active' => true,
            ],
            [
                'teilnehmer_id' => '5-004570200',
                'vertrag_beginn' => '2026/01/01',
                'vertrag_ende' => '2028/07/31',
                'is_active' => true,
            ],
        ]);

        $this->assertSame('5-004570200', $this->currentContract($person)['teilnehmer_id']);
        $this->assertSame(
            '5-004570200',
            CurrentParticipantCourseScope::identifiersFor(
                $person,
                self::OPEN_BEFORE_DAYS,
                self::CLOSE_AFTER_DAYS
            )['teilnehmer_id']
        );
    }

    public function test_it_keeps_the_old_contract_through_its_configured_grace_period(): void
    {
        $person = $this->personWithContracts([
            [
                'teilnehmer_id' => '5-004570201',
                'vertrag_beginn' => '2026/08/03',
                'vertrag_ende' => '2028/07/19',
                'is_active' => true,
            ],
            [
                'teilnehmer_id' => '5-004570200',
                'vertrag_beginn' => '2024/07/18',
                'vertrag_ende' => '2026/07/17',
                'is_active' => true,
            ],
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 17, 12, 0, 0, 'Europe/Berlin'));
        $this->assertSame('5-004570200', $this->currentContract($person)['teilnehmer_id']);

        Carbon::setTestNow(Carbon::create(2026, 7, 18, 12, 0, 0, 'Europe/Berlin'));
        $this->assertSame('5-004570200', $this->currentContract($person)['teilnehmer_id']);

        Carbon::setTestNow(Carbon::create(2026, 7, 20, 12, 0, 0, 'Europe/Berlin'));
        $this->assertSame('5-004570200', $this->currentContract($person)['teilnehmer_id']);

        Carbon::setTestNow(Carbon::create(2026, 7, 25, 12, 0, 0, 'Europe/Berlin'));
        $this->assertSame('5-004570201', $this->currentContract($person)['teilnehmer_id']);
    }

    public function test_api_current_flag_cannot_open_a_future_contract_before_the_configured_window(): void
    {
        $person = $this->personWithContracts([
            [
                'teilnehmer_id' => 'FIRST',
                'vertrag_beginn' => '2026/01/01',
                'vertrag_ende' => '2027/01/01',
                'is_active' => true,
            ],
            [
                'teilnehmer_id' => 'API-CURRENT',
                'vertrag_beginn' => '2026/08/03',
                'vertrag_ende' => '2028/07/19',
                'is_active' => false,
                'is_current' => true,
            ],
        ]);

        $this->assertSame('FIRST', $this->currentContract($person)['teilnehmer_id']);
    }

    public function test_a_real_gap_has_no_contract_and_does_not_fall_back_to_the_future_top_level_id(): void
    {
        $person = $this->personWithContracts(
            [
                [
                    'teilnehmer_id' => 'OLD',
                    'vertrag_beginn' => '2024/07/01',
                    'vertrag_ende' => '2026/07/01',
                    'is_active' => false,
                ],
                [
                    'teilnehmer_id' => 'FUTURE',
                    'vertrag_beginn' => '2026/08/03',
                    'vertrag_ende' => '2028/07/19',
                    'is_active' => true,
                    'is_current' => true,
                ],
            ],
            [
                'teilnehmer_id' => 'FUTURE',
                'tn_baust' => [[
                    'tn_baustein_id' => 'FUTURE-BLOCK',
                    'klassen_id' => 'FUTURE-CLASS',
                ]],
            ]
        );

        $this->assertNull($this->currentContract($person));

        $identifiers = CurrentParticipantCourseScope::identifiersFor(
            $person,
            self::OPEN_BEFORE_DAYS,
            self::CLOSE_AFTER_DAYS
        );

        $this->assertNull($identifiers['teilnehmer_id']);
        $this->assertSame([], $identifiers['tn_baustein_ids']);
        $this->assertTrue($identifiers['restrict_to_none']);
    }

    public function test_it_uses_the_earliest_effective_end_when_begin_dates_are_missing(): void
    {
        $person = $this->personWithContracts([
            [
                'teilnehmer_id' => 'LATER',
                'vertrag_ende' => '2028/07/19',
                'is_active' => true,
            ],
            [
                'teilnehmer_id' => 'FIRST',
                'vertrag_ende' => '2029/07/19',
                'kuendig_zum' => '2027/07/19',
                'is_active' => true,
            ],
        ]);

        $this->assertSame('FIRST', $this->currentContract($person)['teilnehmer_id']);
        $this->assertSame(
            Carbon::create(2027, 7, 19, 23, 59, 59, 'Europe/Berlin')->timestamp,
            $person->portalRoleSortTimestamp(
                self::OPEN_BEFORE_DAYS,
                self::CLOSE_AFTER_DAYS
            )
        );
    }

    public function test_it_does_not_mix_program_identifiers_from_another_contract(): void
    {
        $person = $this->personWithContracts(
            [[
                'teilnehmer_id' => '5-004570200',
                'teilnehmer_nr' => '004570200',
                'vertrag_beginn' => '2025/08/01',
                'vertrag_ende' => '2026/07/18',
                'is_active' => true,
                'is_current' => true,
            ]],
            [
                'teilnehmer_id' => '5-004570201',
                'teilnehmer_nr' => '004570201',
                'tn_baust' => [[
                    'tn_baustein_id' => 'BLOCK-201',
                    'klassen_id' => 'CLASS-201',
                    'baustein_id' => 'MODULE-201',
                ]],
            ],
        );

        $identifiers = CurrentParticipantCourseScope::identifiersFor(
            $person,
            self::OPEN_BEFORE_DAYS,
            self::CLOSE_AFTER_DAYS
        );

        $this->assertSame('5-004570200', $identifiers['teilnehmer_id']);
        $this->assertSame([], $identifiers['tn_baustein_ids']);
        $this->assertSame([], $identifiers['klassen_ids']);
        $this->assertSame([], $identifiers['baustein_ids']);
    }

    private function personWithContracts(array $contracts, array $programData = []): Person
    {
        $person = new Person;
        $person->statusdata = [
            'status' => 'Teilnehmer',
            'teilnehmer_id' => 'STALE-TOP-LEVEL-ID',
            'vertraege' => $contracts,
        ];
        $person->programdata = $programData;

        return $person;
    }

    private function currentContract(Person $person): ?array
    {
        return $person->currentParticipantContract(
            self::OPEN_BEFORE_DAYS,
            self::CLOSE_AFTER_DAYS
        );
    }
}
