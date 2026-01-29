<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncFileDisks extends Command
{
    protected $signature = 'files:sync-disk {--chunk=500 : Anzahl der Datensätze pro Chunk}';
    protected $description = 'Prüft vorhandene Dateien im Storage und setzt das disk-Feld auf public oder private.';

    public function handle(): int
    {
        $chunkSize   = (int) $this->option('chunk');
        $updated     = 0;
        $checked     = 0;
        $missing     = [];

        $this->info("Starte Sync der file.disk-Felder (Chunkgröße {$chunkSize}) ...");

        File::query()
            ->select(['id', 'path', 'disk'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($files) use (&$updated, &$checked, &$missing) {
                foreach ($files as $file) {
                    $checked++;
                    $path = (string) $file->path;
                    $newDisk = null;

                    if ($path !== '' && Storage::disk('public')->exists($path)) {
                        $newDisk = 'public';
                    } elseif ($path !== '' && Storage::disk('private')->exists($path)) {
                        $newDisk = 'private';
                    } else {
                        $newDisk   = 'private'; // Fallback
                        $missing[] = $file->id;
                    }

                    if ($file->disk !== $newDisk) {
                        $file->disk = $newDisk;
                        $file->save();
                        $updated++;
                    }
                }
            });

        $this->info("Geprüft: {$checked}, aktualisiert: {$updated}.");

        if (!empty($missing)) {
            $sample = array_slice($missing, 0, 20);
            $this->warn('Keine Datei gefunden für IDs: ' . implode(', ', $sample) . (count($missing) > 20 ? ' ...' : ''));
        }

        $this->info('Sync abgeschlossen.');
        return Command::SUCCESS;
    }
}
