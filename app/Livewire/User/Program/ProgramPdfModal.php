<?php

namespace App\Livewire\User\Program;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Jobs\DeleteTempFile;
use setasign\Fpdi\Fpdi;

class ProgramPdfModal extends Component
{
    public bool $show = false;

    public string $title = 'Qualifizierungsprogramm';
    public string $previewUrl = '';
    public string $downloadUrl = '';
    public string $downloadName = 'programm.pdf';

    /** Merkt die Temp-Datei (public) zum sofortigen Aufräumen */
    public ?string $tempPublicPath = null;

        public function mount (): void
    {
        $this->showModal = false;
        $this->reset();
    }
    
    #[On('open-program-pdf')]
    public function openForCurrentUser(): void
    {
        $user   = Auth::user();
        $person = $user?->person;
        abort_unless($user && $person, 404, 'Kein Person-Datensatz gefunden.');

        // --- Daten wie im Legacy-Skript ---
        // In deinem alten Skript hieß dieses Array $data (aus require ...switch_qualiprog_data.php).
        // Wir nehmen 1:1 die Keys aus programdata.
        $data = $person->programdata;
        if (is_string($data)) $data = json_decode($data, true) ?: [];
        if (!is_array($data)) $data = [];

        // Session/Umgebungswerte aus dem alten Script (falls gesetzt)
        $institutOrt   = session('institut_ort', '');      // entspricht $_SESSION['institut_ort']
        $persInstOrt   = session('pers_inst_ort', '');     // entspricht $_SESSION['pers_inst_ort']
        $sVertragVtz   = session('curr_vtz');              // entspricht $_SESSION['curr_vtz']
        if (!$sVertragVtz) $sVertragVtz = $data['vtz'] ?? null; // Fallback: aus Daten ableiten

        // Dateiname wie bisher
        $name = $data['name'] ?? 'Teilnehmer';
        $name = str_replace(', ', '_', $name);
        $this->downloadName = 'Qualifizierungsprogramm_'.$name.'_'.now()->format('Y').'.pdf';
        $this->title = 'TN-Qualifizierungsprogramm';

        // === PDF mit FPDI: identische Parameter wie dein Legacy-Skript ===
        $template = storage_path('app/private/pdfs/cbw_letter.pdf');
        abort_unless(is_file($template), 500, 'Template cbw_letter.pdf fehlt unter storage/app/private/pdfs/');

        $pdf = new Fpdi();
        $pdf->SetTitle($this->downloadName);
        $pdf->SetAuthor('CBW Schulnetz');
        $pdf->SetCreator('CBW Schulnetz');
        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile($template);
        $pageId    = $pdf->importPage(1);

        // --- PDF-Parameter wie im Original ---
        $fontsize    = 8;
        $lineheight  = 4;
        $leftBorder  = 20;
        $column1     = 20;
        $column2     = 80;
        $column3     = 140;

        $pdf->AddPage();                        // <- AddPage zuerst
        $pdf->useTemplate($pageId, 0, 0, 210);  // <- Briefbogen (A4 Breite 210mm)

        // Kopfzeile (Institut | Titel | Stand)
        $startY = 46;
        $pdf->SetFont('Arial', '', $fontsize);
        $pdf->setXY($column1, $startY);
        $pdf->Cell(60, 4.1, @utf8_decode('Institut: '.$institutOrt), 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', $fontsize+2);
        $pdf->setXY($column2, $startY);
        $pdf->Cell(60, 4.1, @utf8_decode('TN-Qualifizierungsprogramm'), 0, 0, 'C');

        $pdf->SetFont('Arial', '', $fontsize);
        $pdf->setXY($column3, $startY);
        $pdf->Cell(60, 4.1, 'Stand '.now()->format('d.m.Y, H:i').' Uhr', 0, 0, 'R');

        // Teilnehmer-Zeile (Labels)
        $height = 6;
        $startY = 56;
        $pdf->SetFont('Arial', 'B', $fontsize);
        $pdf->setXY($column1, $startY);
        $pdf->Cell(25, $height, @utf8_decode('Teilnehmer-Nr.'), 1, 0, 'C');
        $pdf->setXY($column1+25, $startY);
        $pdf->Cell(50, $height, @utf8_decode('Name'), 1, 0, 'C');
        $pdf->setXY($column1+75, $startY);
        $pdf->Cell(25, $height, @utf8_decode('Geburtsdatum'), 1, 0, 'C');
        $pdf->setXY($column1+100, $startY);
        $pdf->Cell(25, $height, @utf8_decode('Kunden-Nr.'), 1, 0, 'C');
        $pdf->setXY($column1+125, $startY);
        $pdf->Cell(25, $height, @utf8_decode('Stammklasse'), 1, 0, 'C');
        $pdf->setXY($column1+150, $startY);
        $pdf->Cell(30, $height, @utf8_decode('Eignungstest'), 1, 0, 'C');

        // Teilnehmer-Zeile (Werte)
        $startY = 62;
        $pdf->SetFont('Arial', '', $fontsize);
        $pdf->setXY($column1, $startY);
        $pdf->Cell(25, $height, @utf8_decode($data['teilnehmer_nr'] ?? ''), 1, 0, 'C');
        $pdf->setXY($column1+25, $startY);
        $pdf->Cell(50, $height, @utf8_decode($data['name'] ?? ''), 1, 0, 'C');
        $pdf->setXY($column1+75, $startY);
        $pdf->Cell(25, $height, @utf8_decode($data['geburt_datum'] ?? ''), 1, 0, 'C');
        $pdf->setXY($column1+100, $startY);
        $pdf->Cell(25, $height, @utf8_decode($data['stamm_nr_kst'] ?? ''), 1, 0, 'C');
        $pdf->setXY($column1+125, $startY);
        $pdf->Cell(25, $height, @utf8_decode($data['stammklasse'] ?? ''), 1, 0, 'C');
        $pdf->setXY($column1+150, $startY);
        $pdf->Cell(30, $height, @utf8_decode(($data['test_punkte'] ?? null) ? ($data['test_punkte'].' %') : 'nicht erforderlich'), 1, 0, 'C');

        // Maßnahme-Block (Zeile mit Maßnahme / Zeitraum / Anzahl Bausteine / Langbez)
        $startY = 72;
        $pdf->SetFont('Arial', 'B', $fontsize);
        $pdf->setXY($column1, $startY);
        $pdf->Cell(20, $height, @utf8_decode('  Maßnahme'), 1, 1, 'L');

        $pdf->SetFont('Arial', '', $fontsize);
        $pdf->setXY($column1+20, $startY);
        $pdf->Cell(18, $height, @utf8_decode('  '.($data['vtz'] ?? '').' '.($data['massn_kurz'] ?? '')), 1, 0, 'L');

        $pdf->setXY($column1+38, $startY);
        $pdf->Cell(34, $height, @utf8_decode('  '.($data['vertrag_beginn'] ?? '').' - '.($data['vertrag_ende'] ?? '')), 1, 0, 'L');

        // Anzahl Bausteine wie im Legacy
        if (($sVertragVtz ?? '') === 'E') {
            $iCountBausteine = is_array($data['u_tage'] ?? null) ? count($data['u_tage']) : 0;
        } else {
            $iCountBausteine = is_array($data['tn_baust'] ?? null) ? count($data['tn_baust']) : 0;
        }
        $pdf->setXY($column1+72, $startY);
        $pdf->Cell(22, $height, @utf8_decode('  Bausteine: '.$iCountBausteine), 1, 0, 'L');

        // langbez je Geschlecht wie im Original (beide Zweige nutzten m in deinem Snippet)
        $langbez = $data['langbez_m'] ?? ($data['langbez_w'] ?? '');
        $pdf->setXY($column1+94, $startY);
        $pdf->Cell(86, $height, @utf8_decode('  '.$langbez), 1, 0, 'L');

        // Vertrag-Zeile
        $startY = 82;
        $pdf->SetFont('Arial', 'B', $fontsize);
        $pdf->setXY($column1, $startY);
        $pdf->Cell(13, $height, @utf8_decode('Vertrag'), 1, 0, 'C');

        $pdf->SetFont('Arial', '', $fontsize);
        $pdf->setXY($column1+13, $startY);
        $pdf->Cell(5, $height, @utf8_decode($data['vtz'] ?? ''), 1, 0, 'C');

        $pdf->setXY($column1+18, $startY);
        $pdf->Cell(13, $height, @utf8_decode($data['massn_kurz'] ?? ''), 1, 0, 'C');

        $pdf->setXY($column1+31, $startY);
        $pdf->Cell(17, $height, @utf8_decode($data['vertrag_beginn'] ?? ''), 1, 0, 'C');

        $pdf->setXY($column1+48, $startY);
        $pdf->Cell(17, $height, @utf8_decode($data['vertrag_ende'] ?? ''), 1, 0, 'C');

        $pdf->SetFont('Arial', 'B', $fontsize);
        $pdf->setXY($column1+65, $startY);
        $pdf->Cell(10, $height, @utf8_decode('ReNr'), 1, 0, 'C');

        $pdf->SetFont('Arial', '', $fontsize);
        $pdf->setXY($column1+75, $startY);
        $pdf->Cell(15, $height, @utf8_decode($data['rechnung_nr'] ?? ''), 1, 0, 'C');

        $pdf->SetFont('Arial', 'B', $fontsize);
        $pdf->setXY($column1+90, $startY);
        $pdf->Cell(17, $height, @utf8_decode('Abschluss'), 1, 0, 'C');

        $pdf->SetFont('Arial', '', $fontsize);
        $pdf->setXY($column1+107, $startY);
        $pdf->Cell(17, $height, @utf8_decode($this->datumTransPunkt($data['vertrag_datum'] ?? null)), 1, 0, 'C');

        $pdf->SetFont('Arial', 'B', $fontsize);
        $pdf->setXY($column1+124, $startY);
        $pdf->Cell(10, $height, @utf8_decode('Künd'), 1, 0, 'C');

        $pdf->SetFont('Arial', '', $fontsize);
        $pdf->setXY($column1+134, $startY);
        $pdf->Cell(17, $height, @utf8_decode(($data['kuendig_zum'] ?? null) ? $data['kuendig_zum'] : '---'), 1, 0, 'C');

        $pdf->SetFont('Arial', 'B', $fontsize);
        $pdf->setXY($column1+151, $startY);
        $pdf->Cell(12, $height, @utf8_decode('Storno'), 1, 0, 'C');

        $pdf->SetFont('Arial', '', $fontsize);
        $pdf->setXY($column1+163, $startY);
        $pdf->Cell(17, $height, @utf8_decode(($data['storno_zum'] ?? null) ? $data['storno_zum'] : '---'), 1, 0, 'C');

        // Tabelle(n)
        $startY = 98;
        if (($sVertragVtz ?? '') === 'E') {
            $this->writeECUnits($pdf, $data, $leftBorder, $startY, $lineheight, $fontsize, $leftBorder);
        } else {
            $this->writeModulesTable($pdf, $data, $leftBorder, $startY, $lineheight, $fontsize, $leftBorder, $pageId);
        }

        // Footer wie im Legacy
        $this->writeFooterSimple($pdf, $persInstOrt, $leftBorder, 284, 7, $lineheight);

        // Binärdaten
        $pdfBinary = $pdf->Output('S');

        // --- Temp-Datei (public) schreiben & nach X Minuten löschen ---
        if ($this->tempPublicPath) {
            try { Storage::disk('public')->delete($this->tempPublicPath); } catch (\Throwable) {}
            $this->tempPublicPath = null;
        }
        $minutes = 10;
        $this->tempPublicPath = 'temp/pdfs/u_'.$user->id.'/program_'.Str::uuid().'.pdf';
        Storage::disk('public')->put($this->tempPublicPath, $pdfBinary);

        $url = Storage::disk('public')->url($this->tempPublicPath);
        $this->previewUrl  = $url;
        $this->downloadUrl = $url;

        DeleteTempFile::dispatch('public', $this->tempPublicPath)->delay(now()->addMinutes($minutes));

        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        if ($this->tempPublicPath) {
            try { Storage::disk('public')->delete($this->tempPublicPath); } catch (\Throwable) {}
            $this->tempPublicPath = null;
        }
    }

    /** ========== HILFSFUNKTIONEN — 1:1 an dein Legacy-Skript angelehnt ========== */

    protected function datumTransPunkt(?string $s): string
    {
        if (!$s) return '';
        // akzeptiere "Y/m/d" und "Y-m-d"
        try { return Carbon::createFromFormat('Y/m/d', $s)->format('d.m.Y'); } catch (\Throwable) {}
        try { return Carbon::parse($s)->format('d.m.Y'); } catch (\Throwable) {}
        return $s;
    }

    protected function switchToBold(Fpdi $pdf, int $fontsize): void
    {
        $pdf->SetFont('Arial', 'B', $fontsize);
    }

    protected function switchToNormal(Fpdi $pdf, int $fontsize): void
    {
        $pdf->SetFont('Arial', '', $fontsize);
    }

    protected function writeFooterSimple(Fpdi $pdf, string $text, int $x, int $y, int $height, int $lineheight): void
    {
        if ($text === '') return;
        $this->switchToNormal($pdf, 8);
        $pdf->setXY($x, $y);
        $pdf->Cell(0, $height, @utf8_decode($text), 0, 0, 'L');
    }

    protected function writeCell(Fpdi $pdf, float $x1, float $x2, string $value, float $startY, string $align = 'L', float $marginRight = 1): float
    {
        $pdf->setXY($x1, $startY - 3);
        // MultiCell: Breite = (x2 - x1) - Rand; Zeilenhöhe 4
        $pdf->MultiCell(($x2 - $x1) - $marginRight, 4, @utf8_decode($value ?? ''), 0, $align, '');
        return $pdf->GetY();
    }

    protected function writeECUnits(Fpdi $pdf, array $data, int $x, int $startY, int $lineheight, int $fontsize, int $leftBorder): void
    {
        $column1=$leftBorder;
        $column2=$leftBorder+10;
        $column3=$leftBorder+30;
        $column4=$leftBorder+50;
        $column5=$leftBorder+65;
        $column6=$leftBorder+85;
        $column7=$leftBorder+125;
        $column8=$leftBorder+172;

        $pdf->text($column1, $startY, @utf8_decode('Nr.'));
        $pdf->text($column2, $startY, @utf8_decode('Datum'));
        $pdf->text($column3, $startY, @utf8_decode('Wochentag'));
        $pdf->text($column4, $startY, @utf8_decode('Beginn'));
        $pdf->text($column5, $startY, @utf8_decode('Ende'));
        $pdf->text($column6, $startY, @utf8_decode('Inhalt'));
        $pdf->text($column7, $startY, @utf8_decode('Coach'));
        $pdf->text($column8, $startY, @utf8_decode('Raum'));

        $pdf->line($leftBorder, $startY+3, 200, $startY+3);
        $startY = $startY + 7;

        $i = 0;
        foreach (($data['u_tage'] ?? []) as $baustein) {
            $i++;
            $pdf->text($column1, $startY, @utf8_decode($i));
            $pdf->text($column2, $startY, @utf8_decode($baustein['datum'] ?? ''));
            $pdf->text($column3, $startY, @utf8_decode($baustein['wochentag'] ?? ''));
            $pdf->text($column4, $startY, @utf8_decode($baustein['beginn'] ?? ''));
            $pdf->text($column5, $startY, @utf8_decode($baustein['ende'] ?? ''));
            $pdf->text($column6, $startY, @utf8_decode($baustein['inhalt'] ?? ''));
            $pdf->text($column7, $startY, @utf8_decode($baustein['coach'] ?? ''));
            $pdf->text($column8, $startY, @utf8_decode($baustein['raum'] ?? ''));
            $startY += $lineheight;
        }

        $pdf->line($leftBorder, $startY-2, 200, $startY-2);
        $startY += $lineheight;

        $pdf->text($column1, $startY, @utf8_decode('Unterrichtsform:'));
        $pdf->text($column4, $startY, @utf8_decode($data['uform'] ?? ''));

        $startY += $lineheight;
        $pdf->text($column1, $startY, @utf8_decode('Unterrichtstage:'));
        $pdf->text($column4, $startY, @utf8_decode(($data['summen']['vertrag_tage'] ?? '').' Tage'));

        $startY += $lineheight;
        $pdf->text($column1, $startY, @utf8_decode('Unterrichtsstunden:'));
        $pdf->text($column4, $startY, @utf8_decode(($data['summen']['vertrag_std'] ?? '').' Std.'));
    }

    protected function writeModulesTable(Fpdi $pdf, array $data, int $x, int $startY, int $lineheight, int $fontsize, int $leftBorder, $pageId): void
    {
        $column1=$leftBorder;
        $column2=$column1+8;
        $column3=$column2+17;
        $column4=$column3+17;
        $column5=$column4+10;
        $column6=$column5+15;
        $column7=$column6+87;
        $column8=$column7+10;
        $column9=$column8+10;

        // Kopf (Spalten)
        $pdf->text($column1, $startY, @utf8_decode('Nr.'));
        $pdf->text($column2, $startY, @utf8_decode('Beginn'));
        $pdf->text($column3, $startY, @utf8_decode('Ende'));
        $pdf->text($column4, $startY, @utf8_decode('Tage'));
        $pdf->text($column5, $startY, @utf8_decode('Ul.-Kl.'));
        $pdf->text($column6, $startY, @utf8_decode('Baustein'));
        $pdf->text($column7, $startY, @utf8_decode('Ø-Kl.'));
        $pdf->text($column8, $startY, @utf8_decode('TN'));
        $pdf->text($column9, $startY, @utf8_decode('Fehlt.'));

        $pdf->line($leftBorder, $startY+3, 200, $startY+3);
        $startY += 7;

        $i = 0;
        $aMaxY = [];

        foreach (($data['tn_baust'] ?? []) as $baustein) {
            $i++;

            // Zeilenwerte
            $pdf->text($column1, $startY, @utf8_decode($i));
            $pdf->text($column2, $startY, @utf8_decode($baustein['beginn_baustein'] ?? ''));
            $pdf->text($column3, $startY, @utf8_decode($baustein['ende_baustein'] ?? ''));
            $pdf->text($column4, $startY, @utf8_decode((string)($baustein['baustein_tage'] ?? '')));
            $pdf->text($column5, $startY, @utf8_decode($baustein['klassen_co_ks'] ?? ''));

            // Baustein: MultiCell zwischen column6..column7
            $aMaxY[6] = $this->writeCell($pdf, $column6, $column7, (string)($baustein['langbez'] ?? ''), $startY, 'L');

            $pdf->text($column7, $startY, @utf8_decode((string)($baustein['klassenschnitt'] ?? '')));
            $pdf->text($column8, $startY, @utf8_decode((string)($baustein['tn_punkte'] ?? '')));
            $pdf->text($column9, $startY, @utf8_decode((string)($baustein['fehltage'] ?? '')));

            $startY = max($aMaxY ?: [$startY]);

            // Seitenumbruch wie im Legacy; zusätzlich Briefbogen erneut auf Folgeseiten
            if ($startY > 270) {
                $pdf->AddPage();
                $pdf->useTemplate($pageId, 0, 0, 210);
                $startY = 20;
            }

            $startY += $lineheight;
        }

        $pdf->line($leftBorder, $startY-2, 200, $startY-2);

        // Summenzeile (unterhalb der Tabelle)
        $startY += $lineheight;
        $pdf->text($column7, $startY, @utf8_decode((string)($data['summen']['klassen_schnitt'] ?? '')));
        $pdf->text($column8, $startY, @utf8_decode((string)($data['summen']['tn_schnitt'] ?? '')));
        $pdf->text($column9, $startY, @utf8_decode((string)($data['summen']['fehltage'] ?? '')));

        // Abschlussblöcke (Unterrichtsform/…)
        $pdf->text($column1, $startY, @utf8_decode('Unterrichtsform:'));
        $pdf->text($column4, $startY, @utf8_decode($data['uform'] ?? ''));

        $startY += $lineheight;
        $pdf->text($column1, $startY, @utf8_decode('Unterrichtstage:'));
        $pdf->text($column4, $startY, @utf8_decode((string)($data['summen']['u_tage'] ?? '').' Tage'));

        $startY += $lineheight;
        $pdf->text($column1, $startY, @utf8_decode('Unterrichtsstunden:'));
        $pdf->text($column4, $startY, @utf8_decode((string)($data['summen']['u_std'] ?? '').' Std.'));

        $startY += $lineheight;
        $pdf->text($column1, $startY, @utf8_decode('Praktikumstage:'));
        $pdf->text($column4, $startY, @utf8_decode((string)($data['summen']['prak_tage'] ?? '').' Tage'));

        $startY += $lineheight;
        $pdf->text($column1, $startY, @utf8_decode('Praktikumsstunden:'));
        $pdf->text($column4, $startY, @utf8_decode((string)($data['summen']['prak_std'] ?? '').' Std.'));

        $startY += $lineheight;
        $pdf->text($column1, $startY, @utf8_decode('Note:'));
        $this->switchToBold($pdf, $fontsize);
        $pdf->text($column4, $startY, @utf8_decode((string)($data['summen']['note_lang'] ?? '')));
        $this->switchToNormal($pdf, $fontsize);
    }

    public function render()
    {
        return view('livewire.user.program.program-pdf-modal');
    }
}
