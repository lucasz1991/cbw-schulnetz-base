<?php

namespace App\Services\Maintenance;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/** Retire only an empty duplicate; never merge or delete its related records. */
class RetireEmptyDuplicateUser
{
    public function preview(int $keepId, int $duplicateId, string $personId): array
    {
        $state = $this->inspect($keepId, $duplicateId, $personId);

        return $this->summary($state);
    }

    public function execute(int $keepId, int $duplicateId, string $personId, string $expectedHash, bool $writesPaused): array
    {
        if (! $writesPaused) {
            throw new RuntimeException('Anwendungs-, Queue- und Scheduler-Schreibzugriffe müssen während der Bereinigung pausieren.');
        }

        return DB::transaction(function () use ($keepId, $duplicateId, $personId, $expectedHash) {
            $state = $this->inspect($keepId, $duplicateId, $personId, true);
            $summary = $this->summary($state);
            if (! hash_equals($summary['hash'], $expectedHash)) {
                throw new RuntimeException('Der Datenstand weicht von der Vorschau ab. Neue Vorschau erforderlich.');
            }
            if ($state['blockers'] !== []) {
                throw new RuntimeException('Das Zweitkonto besitzt Daten oder ungeklärte Verweise. Keine automatische Löschung.');
            }

            $backupPath = $this->backup($state);
            // Query builder intentionally avoids photo/token deletion and model side effects.
            if (DB::table('users')->where('id', $duplicateId)->delete() !== 1) {
                throw new RuntimeException('Das exakt geprüfte Zweitkonto konnte nicht entfernt werden.');
            }
            if ((array) DB::table('users')->where('id', $keepId)->first() !== $state['keep']
                || (array) DB::table('persons')->where('id', $state['person']['id'])->first() !== $state['person']) {
                throw new RuntimeException('Das ursprüngliche Konto oder seine Person wurde verändert; Transaktion zurückgerollt.');
            }

            return $summary + ['retired' => true, 'backup_path' => $backupPath];
        }, 1);
    }

    protected function backup(array $state): string
    {
        $directory = storage_path('app/private/maintenance/user-orphan-snapshots');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Privates Sicherungsverzeichnis konnte nicht angelegt werden.');
        }
        $plaintext = json_encode(['version' => 1, 'created_at' => now()->toIso8601String(), 'state' => $state], JSON_THROW_ON_ERROR);
        $encrypted = Crypt::encryptString($plaintext);
        $path = $directory.'/'.now()->format('Ymd_His').'_'.Str::uuid().'.json.enc';
        $handle = fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Private Sicherungsdatei konnte nicht angelegt werden.');
        }
        try {
            if (! chmod($path, 0600) || fwrite($handle, $encrypted) !== strlen($encrypted) || ! fflush($handle)) {
                throw new RuntimeException('Private Sicherung konnte nicht vollständig geschrieben werden.');
            }
            if (function_exists('fsync') && ! fsync($handle)) {
                throw new RuntimeException('Private Sicherung konnte nicht dauerhaft geschrieben werden.');
            }
        } finally {
            fclose($handle);
        }
        $saved = file_get_contents($path);
        if ($saved === false || ! hash_equals(hash('sha256', $plaintext), hash('sha256', Crypt::decryptString($saved)))) {
            throw new RuntimeException('Die private Sicherung hat die Entschlüsselungs-/Hashprüfung nicht bestanden.');
        }

        return $path;
    }

    private function inspect(int $keepId, int $duplicateId, string $personId, bool $locking = false): array
    {
        if ($keepId <= 1 || $duplicateId <= 1 || $keepId === $duplicateId || trim($personId) === '') {
            throw new RuntimeException('Zwei unterschiedliche, ungeschützte Benutzer und die genaue UVS-Personen-ID sind erforderlich.');
        }
        $usersQuery = DB::table('users')->whereIn('id', [$keepId, $duplicateId])->orderBy('id');
        $personsQuery = DB::table('persons')->where('person_id', $personId)->orderBy('id');
        if ($locking) {
            $usersQuery->lockForUpdate();
            $personsQuery->lockForUpdate();
        }
        $users = $usersQuery->get()->keyBy('id');
        $keep = (array) $users->get($keepId);
        $duplicate = (array) $users->get($duplicateId);
        $persons = $personsQuery->get();
        if (! $keep || ! $duplicate || $persons->count() !== 1 || (int) $persons->first()->user_id !== $keepId) {
            throw new RuntimeException('Beide Konten und genau eine mit dem ursprünglichen Konto verknüpfte UVS-Person müssen vorhanden sein.');
        }
        if (! in_array($keep['role'] ?? null, ['guest', 'tutor'], true) || ($duplicate['role'] ?? null) !== $keep['role']) {
            throw new RuntimeException('Geschützte oder unterschiedliche Benutzerrollen dürfen hier nicht bereinigt werden.');
        }
        if (empty($keep['created_at']) || empty($duplicate['created_at']) || $keep['created_at'] > $duplicate['created_at']) {
            throw new RuntimeException('Das beizubehaltende Konto muss das ursprüngliche, ältere Konto sein.');
        }
        $person = (array) $persons->first();
        $contactEmails = array_map(fn ($email) => mb_strtolower(trim((string) $email)),
            [$person['email_priv'] ?? '', $person['email_cbw'] ?? '']);
        if (empty($duplicate['email']) || ! in_array(mb_strtolower(trim($duplicate['email'])), array_filter($contactEmails), true)) {
            throw new RuntimeException('Die Zweitkonto-Adresse muss einer aktuellen Kontaktadresse der genau geprüften UVS-Person entsprechen.');
        }
        $blockers = [];
        foreach (['profile_photo_path', 'current_team_id', 'two_factor_secret', 'two_factor_recovery_codes'] as $field) {
            if (! empty($duplicate[$field])) {
                $blockers['users.'.$field] = 1;
            }
        }

        $tables = Schema::getTableListing();
        sort($tables);
        $schema = [];
        $userTypes = array_unique(array_merge([User::class, (new User)->getMorphClass(), 'user', 'users'],
            array_keys(array_filter(Relation::morphMap(), fn ($class) => $class === User::class))));
        foreach ($tables as $table) {
            $columns = Schema::getColumns($table);
            $names = array_column($columns, 'name');
            $foreignKeys = Schema::getForeignKeys($table);
            $schema[$table] = ['columns' => $columns, 'foreign_keys' => $foreignKeys];
            $referenceColumns = [];
            foreach ($foreignKeys as $foreignKey) {
                if ($foreignKey['foreign_table'] === 'users') {
                    if (count($foreignKey['columns']) !== 1 || $foreignKey['foreign_columns'] !== ['id']) {
                        $blockers[$table.'.unsupported_users_foreign_key'] = 1;
                        continue;
                    }
                    $referenceColumns[] = $foreignKey['columns'][0];
                }
            }
            foreach ($names as $column) {
                if ($column === 'user_id' || str_ends_with($column, '_user_id') || str_ends_with($column, '_by')
                    || in_array($column, ['from_user', 'to_user', 'assigned_to', 'participant_id', 'actor_id',
                        'owner_id', 'author_id', 'recipient_id', 'sender_id', 'reviewer_id'], true)) {
                    $referenceColumns[] = $column;
                }
            }
            foreach (array_unique($referenceColumns) as $column) {
                $this->record($blockers, $table.'.'.$column, DB::table($table)->where($column, $duplicateId), $locking);
            }
            foreach ($names as $column) {
                $idColumn = substr($column, 0, -5).'_id';
                if (str_ends_with($column, '_type') && in_array($idColumn, $names, true)) {
                    $this->record($blockers, $table.'.'.$column, DB::table($table)
                        ->whereIn($column, $userTypes)->where($idColumn, $duplicateId), $locking);
                }
            }
            // Include serialized queues, JSON recipients/settings, historical IDs and ID-based paths.
            // Any textual mention is deliberately a blocker, even if its meaning is uncertain.
            $textColumns = $table === 'users' ? [] : array_filter($columns,
                fn ($column) => preg_match('/char|text|json|blob|binary|string/i', $column['type_name']));
            if ($textColumns !== []) {
                // One combined existence scan per table; no payloads are loaded or unserialized.
                $query = DB::table($table)->where(function ($query) use ($textColumns, $duplicateId, $duplicate, $table, $person) {
                    foreach ($textColumns as $column) {
                        $query->orWhere($column['name'], 'like', '%'.$duplicateId.'%');
                        // The canonical Person contact address may legitimately be the new UVS address.
                        $query->orWhere(function ($query) use ($column, $duplicate, $table, $person) {
                            $query->where($column['name'], 'like', '%'.$duplicate['email'].'%');
                            if ($table === 'persons') {
                                $query->where('id', '<>', $person['id']);
                            }
                        });
                    }
                });
                $this->record($blockers, $table.'.text_candidate', $query, $locking);
            }
        }
        if (DB::connection()->getDriverName() === 'mysql') {
            $database = DB::connection()->getDatabaseName();
            $grants = array_map(fn ($row) => implode(' ', (array) $row), DB::select('SHOW GRANTS FOR CURRENT_USER'));
            $canInspectTables = false;
            $canInspectTriggers = false;
            $canInspectExternalReferences = false;
            foreach ($grants as $grant) {
                if (! preg_match('/^GRANT (.+) ON (.+) TO /i', $grant, $matches)) {
                    continue;
                }
                $scope = $matches[2];
                $databaseScope = '*.*' === $scope || '`'.str_replace('`', '``', $database).'`.*' === $scope;
                $usersScope = $databaseScope || '`'.str_replace('`', '``', $database).'`.`users`' === $scope;
                $privileges = array_map('trim', explode(',', strtoupper($matches[1])));
                $all = in_array('ALL PRIVILEGES', $privileges, true);
                $canInspectTables = $canInspectTables || ($databaseScope && ($all || in_array('SELECT', $privileges, true)));
                $canInspectTriggers = $canInspectTriggers || ($usersScope && ($all || in_array('TRIGGER', $privileges, true)));
                $canInspectExternalReferences = $canInspectExternalReferences || ($scope === '*.*'
                    && ($all || in_array('SELECT', $privileges, true)));
            }
            if (! $canInspectTables || ! $canInspectTriggers) {
                $blockers['schema.metadata_visibility_unconfirmed'] = 1;
            }
            // Schema-scoped accounts cannot prove that hidden schemas have no cascading FK.
            if (! $canInspectExternalReferences) {
                $blockers['schema.external_reference_visibility_unconfirmed'] = 1;
            }
            if (DB::table('information_schema.KEY_COLUMN_USAGE')->where('REFERENCED_TABLE_SCHEMA', $database)
                ->where('REFERENCED_TABLE_NAME', 'users')->where('TABLE_SCHEMA', '<>', $database)->exists()) {
                $blockers['users.cross_schema_foreign_keys'] = 1;
            }
            $triggers = DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', $database)
                ->where('EVENT_OBJECT_TABLE', 'users')->count();
            if ($triggers > 0) {
                $blockers['users.triggers'] = $triggers;
            }
            foreach (Schema::getTables() as $table) {
                if (strtolower((string) ($table['engine'] ?? '')) !== 'innodb') {
                    $blockers['schema.non_transactional_tables'] = ($blockers['schema.non_transactional_tables'] ?? 0) + 1;
                }
            }
        } elseif (DB::connection()->getDriverName() === 'sqlite') {
            if (DB::table('sqlite_master')->where('type', 'trigger')->where('tbl_name', 'users')->exists()) {
                $blockers['users.triggers'] = 1;
            }
        } else {
            throw new RuntimeException('Die Datenbankplattform wird von dieser begrenzten Reparatur nicht unterstützt.');
        }
        ksort($blockers);

        return ['keep' => $keep, 'duplicate' => $duplicate, 'person' => $person,
            'schema' => $schema, 'blockers' => $blockers];
    }

    private function record(array &$blockers, string $key, $query, bool $locking): void
    {
        if ($locking) {
            $query->lockForUpdate();
        }
        if ($query->exists()) {
            // Limit output to presence; never expose a row, email, queue payload or credential.
            $blockers[$key] = 1;
        }
    }

    private function summary(array $state): array
    {
        return ['keep_user_id' => $state['keep']['id'], 'duplicate_user_id' => $state['duplicate']['id'],
            'person_pk' => $state['person']['id'], 'hash' => hash('sha256', json_encode($state, JSON_THROW_ON_ERROR)),
            'eligible' => $state['blockers'] === [], 'blockers' => $state['blockers'], 'tables_checked' => count($state['schema'])];
    }
}
