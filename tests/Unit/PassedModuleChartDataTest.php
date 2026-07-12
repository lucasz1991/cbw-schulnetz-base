<?php

namespace Tests\Unit;

use App\Support\PassedModuleChartData;
use PHPUnit\Framework\TestCase;

class PassedModuleChartDataTest extends TestCase
{
    public function test_it_includes_all_passed_modules_without_the_old_nine_bar_limit(): void
    {
        $blocks = [];

        for ($number = 12; $number >= 1; $number--) {
            $blocks[] = [
                'kurzbez' => sprintf('MOD%02d', $number),
                'ende_baustein' => sprintf('%02d.01.2026', $number),
                'tn_punkte' => 50 + $number,
            ];
        }

        $blocks[] = [
            'kurzbez' => 'EXT',
            'ende_baustein' => '13.01.2026',
            'tn_punkte' => 'passed',
            'klassenschnitt' => 'extern',
        ];

        $data = (new PassedModuleChartData)->build(['tn_baust' => $blocks]);

        $this->assertCount(13, $data['series']);
        $this->assertSame(
            array_merge(array_map(fn (int $number) => sprintf('MOD%02d', $number), range(1, 12)), ['EXT']),
            $data['labels']
        );
        $this->assertSame(51, $data['series'][0]);
        $this->assertSame(100, $data['series'][12]);
        $this->assertSame('51 Pkt.', $data['tooltips'][0]);
        $this->assertSame('extern bestanden – keine Punktzahl', $data['tooltips'][12]);
        $this->assertCount(13, $data['colors']);
    }

    public function test_it_excludes_failed_open_low_score_and_non_course_blocks(): void
    {
        $data = (new PassedModuleChartData)->build([
            'tn_baust' => [
                ['kurzbez' => 'PASS', 'ende_baustein' => '2026/02/01', 'tn_punkte' => 50],
                ['kurzbez' => 'LOW', 'ende_baustein' => '2026/02/02', 'tn_punkte' => 49],
                ['kurzbez' => 'FAIL', 'ende_baustein' => '2026/02/03', 'tn_punkte' => 'failed'],
                ['kurzbez' => 'OPEN', 'ende_baustein' => '2026/02/04', 'tn_punkte' => 'pending'],
                ['kurzbez' => 'ABSENT', 'ende_baustein' => '2026/02/05', 'tn_punkte' => 'not att'],
                ['kurzbez' => 'FERI', 'ende_baustein' => '2026/02/06', 'tn_punkte' => 100],
                ['kurzbez' => 'PRAK', 'ende_baustein' => '2026/02/07', 'tn_punkte' => 100],
                ['kurzbez' => 'PRUE1', 'ende_baustein' => '2026/02/08', 'tn_punkte' => 100],
            ],
        ]);

        $this->assertSame(['PASS'], $data['labels']);
        $this->assertSame([50], $data['series']);
        $this->assertSame(['50 Pkt.'], $data['tooltips']);
    }
}
