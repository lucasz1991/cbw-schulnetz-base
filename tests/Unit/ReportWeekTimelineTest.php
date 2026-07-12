<?php

namespace Tests\Unit;

use App\Services\ReportBook\ReportWeekTimeline;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst ohne Laravel-App/DB (phpunit.xml hat keine sqlite-Override,
 * RefreshDatabase wuerde die lokale Dev-DB treffen).
 */
class ReportWeekTimelineTest extends TestCase
{
    protected function programdata(array $blocks): array
    {
        return ['tn_baust' => $blocks];
    }

    protected function kursBlock(string $beginn, string $ende, string $kurzbez = 'PYT1', ?string $klassenId = '25V26PYT1'): array
    {
        return [
            'kurzbez'         => $kurzbez,
            'klassen_id'      => $klassenId,
            'beginn_baustein' => $beginn,
            'ende_baustein'   => $ende,
        ];
    }

    protected function feriBlock(string $beginn, string $ende): array
    {
        return [
            'kurzbez'         => 'FERI',
            'klassen_id'      => null,
            'beginn_baustein' => $beginn,
            'ende_baustein'   => $ende,
        ];
    }

    public function test_leeres_programm_ergibt_leere_map(): void
    {
        $this->assertSame([], ReportWeekTimeline::weekMap(null));
        $this->assertSame([], ReportWeekTimeline::weekMap([]));
        $this->assertSame([], ReportWeekTimeline::weekMap($this->programdata([])));
    }

    public function test_kurswochen_werden_fortlaufend_nummeriert(): void
    {
        // Mo 05.01.2026 - Fr 16.01.2026 = KW 2 und KW 3
        $map = ReportWeekTimeline::weekMap($this->programdata([
            $this->kursBlock('2026/01/05', '2026/01/16'),
        ]));

        $this->assertSame(['2026-02' => 1, '2026-03' => 2], $map);
    }

    public function test_ferienwochen_zaehlen_als_berichtszeitraeume_und_verschieben_folgewochen(): void
    {
        $map = ReportWeekTimeline::weekMap($this->programdata([
            // Kurs: KW 10 + 11
            $this->kursBlock('2026/03/02', '2026/03/13'),
            // Ferien: KW 12 + 13
            $this->feriBlock('2026/03/16', '2026/03/27'),
            // Folgekurs: KW 14 + 15
            $this->kursBlock('2026/03/30', '2026/04/10', 'PYT2', '25V26PYT2'),
        ]));

        $this->assertSame([
            '2026-10' => 1,
            '2026-11' => 2,
            '2026-12' => 3, // Ferienwoche 1
            '2026-13' => 4, // Ferienwoche 2
            '2026-14' => 5, // Folgekurs beginnt NACH den Ferien bei 5, nicht bei 3
            '2026-15' => 6,
        ], $map);
    }

    public function test_bloecke_ohne_klassen_id_ausser_feri_werden_ignoriert(): void
    {
        $map = ReportWeekTimeline::weekMap($this->programdata([
            $this->kursBlock('2026/01/05', '2026/01/09'),
            // PRAK ohne Klassenzuordnung -> kein Berichtszeitraum
            $this->kursBlock('2026/01/12', '2026/01/16', 'PRAK', null),
        ]));

        $this->assertSame(['2026-02' => 1], $map);
    }

    public function test_jahresend_ferien_sortieren_ueber_jahresgrenze(): void
    {
        $map = ReportWeekTimeline::weekMap($this->programdata([
            // Kurs bis Mitte Dezember 2025: KW 50 + 51
            $this->kursBlock('2025/12/08', '2025/12/19'),
            // Jahresend-Ferien 15.12.2025 - 02.01.2026: KW 51, 52, 1
            $this->feriBlock('2025/12/15', '2026/01/02'),
            // Folgekurs ab Januar: KW 2
            $this->kursBlock('2026/01/05', '2026/01/09', 'PYT2', '25V26PYT2'),
        ]));

        $this->assertSame([
            '2025-50' => 1,
            '2025-51' => 2, // Ueberlappung Kurs/Ferien -> EINE Woche, EINE Nummer
            '2025-52' => 3,
            '2026-01' => 4,
            '2026-02' => 5,
        ], $map);
    }

    public function test_woche_ohne_werktag_im_zeitraum_zaehlt_nicht(): void
    {
        // Sa 10.01.2026 - So 11.01.2026: keine Werktage -> keine Woche
        $map = ReportWeekTimeline::weekMap($this->programdata([
            $this->kursBlock('2026/01/10', '2026/01/11'),
        ]));

        $this->assertSame([], $map);
    }

    public function test_number_for_date(): void
    {
        $map = ReportWeekTimeline::weekMap($this->programdata([
            $this->kursBlock('2026/01/05', '2026/01/16'),
        ]));

        $this->assertSame(1, ReportWeekTimeline::numberForDate(Carbon::parse('2026-01-07'), $map));
        $this->assertSame(2, ReportWeekTimeline::numberForDate(Carbon::parse('2026-01-16'), $map));
        // Wochenende innerhalb der Kurswoche gehoert zur selben ISO-Woche
        $this->assertSame(1, ReportWeekTimeline::numberForDate(Carbon::parse('2026-01-10'), $map));
        // Woche ausserhalb aller Berichtszeitraeume
        $this->assertNull(ReportWeekTimeline::numberForDate(Carbon::parse('2026-02-02'), $map));
    }

    public function test_ungueltige_und_defekte_bloecke_werden_uebersprungen(): void
    {
        $map = ReportWeekTimeline::weekMap($this->programdata([
            'kein-array',
            ['kurzbez' => 'FERI'], // ohne Daten
            $this->feriBlock('2026/03/27', '2026/03/16'), // Ende vor Beginn
            $this->kursBlock('2026/01/05', '2026/01/09'),
        ]));

        $this->assertSame(['2026-02' => 1], $map);
    }
}
