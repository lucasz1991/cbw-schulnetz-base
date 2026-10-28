<?php

namespace App\Console\Commands;

use App\Services\Maintenance\RetireEmptyDuplicateUser as Retirement;
use Illuminate\Console\Command;

class RetireEmptyDuplicateUser extends Command
{
    protected $signature = 'users:retire-empty-duplicate
        {keep : ID des ursprünglichen Benutzerkontos}
        {duplicate : ID des nachweislich leeren Zweitkontos}
        {person : Genaue UVS-Personen-ID des ursprünglichen Kontos}
        {--execute : Geprüftes leeres Zweitkonto nach privater Sicherung entfernen}
        {--expect-hash= : Hash einer aktuellen identischen Vorschau}
        {--writes-paused : Operator bestätigt pausierte Anwendung, Queueworker und Scheduler}';

    protected $description = 'Prüft ein leeres Zweitkonto; keine allgemeine Kontenzusammenführung. Standard: nur Vorschau.';

    public function handle(Retirement $retirement): int
    {
        foreach (['keep', 'duplicate'] as $argument) {
            if (! ctype_digit((string) $this->argument($argument))) {
                $this->error('Benutzer-IDs müssen positive Ganzzahlen sein.');

                return self::FAILURE;
            }
        }
        try {
            $keep = (int) $this->argument('keep');
            $duplicate = (int) $this->argument('duplicate');
            $person = (string) $this->argument('person');
            $result = $this->option('execute')
                ? $retirement->execute($keep, $duplicate, $person, (string) $this->option('expect-hash'), (bool) $this->option('writes-paused'))
                : $retirement->preview($keep, $duplicate, $person);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $result['eligible'] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $error) {
            // Database errors can contain sensitive SQL bindings; do not echo them.
            $this->error($error instanceof \RuntimeException && ! $error instanceof \PDOException
                ? $error->getMessage() : 'Prüfung oder Sicherung fehlgeschlagen; keine Bereinigung bestätigt.');

            return self::FAILURE;
        }
    }
}
