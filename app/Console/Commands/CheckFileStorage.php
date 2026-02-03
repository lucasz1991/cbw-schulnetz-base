<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckFileStorage extends Command
{
    protected $signature = 'files:check {--chunk=500 : Anzahl der Datensätze pro Chunk} {--show=20 : Anzahl fehlender Dateien zur Ausgabe}';
    protected $description = 'Prüft, ob Dateien aus der File-Tabelle im Storage auffindbar sind.';

    public function handle(): int
    {
        $chunkSize    = (int) $this->option('chunk');
        $showMissing  = (int) $this->option('show');
        $checked      = 0;
        $foundPublic  = 0;
        $foundPrivate = 0;
        $missing      = [];

        $this->info("Starte Storage-Check für Files (Chunkgröße {$chunkSize}) ...");

        File::query()
            ->select(['id', 'path', 'disk'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($files) use (&$checked, &$foundPublic, &$foundPrivate, &$missing) {
                foreach ($files as $file) {
                    $checked++;
                    $path = (string) $file->path;

                    if ($path !== '' && Storage::disk('public')->exists($path)) {
                        $foundPublic++;
                        continue;
                    }

                    if ($path !== '' && Storage::disk('private')->exists($path)) {
                        $foundPrivate++;
                        continue;
                    }

                    $missing[] = [
                        'id'   => $file->id,
                        'path' => $path,
                        'disk' => $file->disk ?? '',
                    ];
                }
            });

        $missingCount = count($missing);

        $this->info("Geprüft: {$checked}.");
        $this->info("Gefunden: public {$foundPublic}, private {$foundPrivate}.");
        $this->info("Fehlend: {$missingCount}.");

        if ($missingCount > 0) {
            $sample = array_slice($missing, 0, max(0, $showMissing));

            if (!empty($sample)) {
                $this->warn('Beispiel fehlender Dateien:');
                $this->table(['id', 'path', 'disk'], $sample);
            } else {
                $this->warn('Fehlende Dateien vorhanden, aber Ausgabe deaktiviert (--show=0).');
            }
        }

        $this->info('Check abgeschlossen.');
        return Command::SUCCESS;
    }
}