<?php

namespace App\Console\Commands;

use App\Jobs\ApiUpdates\SyncPersonFerienCourses;
use App\Models\Person;
use Illuminate\Console\Command;

/**
 * Backfill: legt fuer alle Personen mit FERI-Bloecken im Qualiprogramm die
 * synthetischen Ferien-Kurse an (Berichtsheft fuer Ferienwochen).
 *
 * Regulaer haelt CheckPersonsCourses die Ferien-Kurse beim Person-Sync aktuell;
 * dieser Command dient dem einmaligen rueckwirkenden Aufbau nach dem Deploy.
 */
class SyncFerienCourses extends Command
{
    protected $signature = 'ferien:sync-courses
                            {--person= : Nur diese Person (persons.id)}
                            {--now : Jobs synchron ausfuehren statt zu queuen}';

    protected $description = 'Synthetisiert Ferien-Kurse (FERI) aus dem Qualiprogramm fuer das Berichtsheft';

    public function handle(): int
    {
        $query = Person::query()
            ->whereNotNull('programdata')
            ->where('role', 'guest');

        if ($personId = $this->option('person')) {
            $query->whereKey((int) $personId);
        }

        $dispatched = 0;
        $skipped = 0;

        $query->chunkById(100, function ($persons) use (&$dispatched, &$skipped) {
            foreach ($persons as $person) {
                if (! $this->hasFerienBlock($person->programdata)) {
                    $skipped++;
                    continue;
                }

                if ($this->option('now')) {
                    SyncPersonFerienCourses::dispatchSync($person->id);
                } else {
                    SyncPersonFerienCourses::dispatch($person->id);
                }

                $dispatched++;
            }
        });

        $mode = $this->option('now') ? 'synchron verarbeitet' : 'in die Queue gestellt';
        $this->info("Ferien-Sync: {$dispatched} Person(en) {$mode}, {$skipped} ohne FERI-Bloecke uebersprungen.");

        return self::SUCCESS;
    }

    protected function hasFerienBlock(mixed $programdata): bool
    {
        if (! is_array($programdata)) {
            return false;
        }

        foreach ((array) ($programdata['tn_baust'] ?? []) as $block) {
            if (is_array($block) && mb_strtoupper(trim((string) ($block['kurzbez'] ?? ''))) === 'FERI') {
                return true;
            }
        }

        return false;
    }
}
