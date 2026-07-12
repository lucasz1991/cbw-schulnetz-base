<?php

namespace Tests\Unit;

use App\Livewire\User\ProgramShow;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Normalisierung der UVS-Ergebniswerte (tn_punkte/klassenschnitt) zu
 * ergebnis_status. Kennwoerter siehe uvs-api ParticipantApiController:
 * pruef_kennz B->'passed', D->'failed', X->'not att', E/XO->'pending', I->'---'.
 *
 * Bewusst ohne Laravel-App/DB (phpunit.xml hat keine sqlite-Override,
 * RefreshDatabase wuerde die lokale Dev-DB treffen).
 */
class ProgramShowErgebnisStatusTest extends TestCase
{
    protected function normalize(mixed $tnPunkte, mixed $klassenschnitt): string
    {
        $method = new ReflectionMethod(ProgramShow::class, 'ergebnisStatus');
        $method->setAccessible(true);

        return $method->invoke(new ProgramShow(), $tnPunkte, $klassenschnitt);
    }

    public function test_failed_wird_nicht_bestanden_statt_ergebnis_offen(): void
    {
        // Ticket-Fall: externe Java-Zertifizierung failed (pruef_kennz D)
        $this->assertSame('failed', $this->normalize('failed', 'extern'));
    }

    public function test_extern_bestanden(): void
    {
        $this->assertSame('passed', $this->normalize('passed', 'extern'));
    }

    public function test_extern_ausstehend_bleibt_offen(): void
    {
        $this->assertSame('open', $this->normalize('pending', 'extern'));
    }

    public function test_nicht_teilgenommen(): void
    {
        $this->assertSame('not_attended', $this->normalize('not att', 'extern'));
    }

    public function test_ignorierte_pruefung_bleibt_offen(): void
    {
        $this->assertSame('open', $this->normalize('---', '---'));
    }

    public function test_numerisch_null_null_ist_offen(): void
    {
        $this->assertSame('open', $this->normalize(0, 0));
        $this->assertSame('open', $this->normalize('0', '0'));
    }

    public function test_numerische_bewertung(): void
    {
        $this->assertSame('passed', $this->normalize(78, 65));
        $this->assertSame('passed', $this->normalize('50', 60));
        $this->assertSame('failed', $this->normalize(42, 60));
        // 0 Punkte bei vorhandenem Klassenschnitt = mitgeschrieben und durchgefallen
        $this->assertSame('failed', $this->normalize(0, 55));
    }

    public function test_fehlende_werte_sind_offen(): void
    {
        $this->assertSame('open', $this->normalize(null, null));
        $this->assertSame('open', $this->normalize(null, 62));
    }
}
