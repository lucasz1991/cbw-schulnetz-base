<?php

namespace App\Services\Coaching;

use App\Models\CoachingContract;
use App\Models\CoachingMessage;
use App\Models\CoachingPlan;
use App\Models\Course;
use App\Models\CourseDay;
use App\Models\CourseMaterialAcknowledgement;
use App\Models\CourseParticipantEnrollment;
use App\Models\FilePool;
use App\Models\Message;
use App\Models\Person;
use App\Models\ReportBook;
use App\Models\ReportBookEntry;
use App\Models\User;
use App\Models\UserRequest;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\WhitespacePathNormalizer;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TestCleanupService
{
    private array $rows = [];

    private array $schema = [];

    private array $columns = [];

    private bool $locking = false;

    /** Called by the existing authenticated UVS synchronization under its lock. */
    public function apply(array $payload): array
    {
        validator($payload, [
            'contract_ids' => 'required|array|min:1|max:50', 'contract_ids.*' => 'required|integer|min:1|distinct',
            'contracts' => 'required|array|min:1|max:50', 'contracts.*.id' => 'required|integer|min:1|distinct',
            'contracts.*.institut_id' => 'required|integer|min:1', 'contracts.*.person_id' => 'required|string|max:255',
            'contracts.*.beratung_id' => 'required|string|max:255', 'delete_participant' => 'required|boolean',
            'delete_tutors' => 'required|boolean', 'participant_person_ids' => 'present|array|max:50',
            'participant_person_ids.*' => 'required|string|max:255|distinct', 'tutor_person_ids' => 'present|array|max:100',
            'tutor_person_ids.*' => 'required|string|max:255|distinct',
        ])->validate();
        $ids = array_map('intval', $payload['contract_ids']);
        $identityIds = array_map('intval', array_column($payload['contracts'], 'id'));
        sort($ids);
        sort($identityIds);
        if ($ids !== $identityIds || array_diff($payload['participant_person_ids'], array_column($payload['contracts'], 'person_id')) !== []) {
            throw new ConflictHttpException('Der Löschauftrag enthält ungebundene Vertrags- oder Personenidentitäten.');
        }
        foreach (array_merge($payload['participant_person_ids'], $payload['tutor_person_ids']) as $personId) {
            if (! preg_match('/^([0-9]+)-[A-Za-z0-9]+$/D', $personId, $match)
                || ! in_array((int) $match[1], array_map('intval', array_column($payload['contracts'], 'institut_id')), true)) {
                throw new ConflictHttpException('Der Löschauftrag enthält eine Person außerhalb des Vertragsinstituts.');
            }
        }
        $plan = DB::transaction(function () use ($payload) {
            $plan = $this->plan($payload, true);
            if ($plan['blockers'] !== []) {
                throw new ConflictHttpException('Die Vertragsbereinigung ist wegen widersprüchlicher Zuordnungen gesperrt.');
            }
            foreach (['files', 'file_pools', 'admin_tasks', 'report_book_entries', 'report_books',
                'coaching_notices', 'coaching_messages', 'coaching_outbox', 'coaching_assignment_history', 'coaching_plans',
                'coaching_contracts', 'course_material_acknowledgements', 'course_results', 'course_ratings',
                'course_participant_enrollments', 'course_days', 'courses', 'messages', 'user_requests',
                'personal_access_tokens', 'notifications', 'sessions', 'password_reset_tokens',
                'onboarding_video_views', 'team_invitations', 'team_user', 'teams', $this->activityTable(), 'persons', 'users'] as $table) {
                $rows = $plan['rows'][$table] ?? [];
                if ($rows === []) {
                    continue;
                }
                $key = $table === 'password_reset_tokens' ? 'email' : 'id';
                DB::table($table)->whereIn($key, array_column($rows, $key))->delete();
            }

            return $plan;
        }, 3);

        $warnings = [];
        foreach ($plan['persons'] as $person) {
            if (! $person['eligible']) {
                $warnings[] = 'Person '.$person['person_id'].' bleibt erhalten: '.implode(' ', $person['blockers']);
            }
        }
        // Storage cannot be rolled back with SQL. Remove only unshared,
        // validated paths after the database transaction has committed.
        foreach ($plan['storage'] as [$disk, $path]) {
            if ($this->pathStillReferenced($disk, $path)) {
                $warnings[] = 'Eine gemeinsam verwendete Datei bleibt erhalten.';

                continue;
            }
            try {
                if (Storage::disk($disk)->exists($path) && ! Storage::disk($disk)->delete($path)) {
                    $warnings[] = 'Eine Datei konnte nach der Datenbereinigung nicht entfernt werden.';
                }
            } catch (\Throwable $error) {
                $warnings[] = 'Eine Datei konnte nach der Datenbereinigung nicht entfernt werden.';
            }
        }

        return ['counts' => $plan['counts'], 'missing_ids' => $plan['missing_ids'],
            'already_missing' => count($plan['missing_ids']) === count($payload['contracts']),
            'warnings' => array_values(array_unique($warnings))];
    }

    private function plan(array $payload, bool $locking): array
    {
        $identities = $payload['contracts'];
        $deleteParticipant = (bool) $payload['delete_participant'];
        $deleteTutors = (bool) $payload['delete_tutors'];
        $this->rows = $this->schema = $this->columns = [];
        $this->locking = $locking;
        usort($identities, fn ($a, $b) => $a['id'] <=> $b['id']);
        $ids = array_column($identities, 'id');
        $contracts = $this->read('coaching_contracts', fn ($q) => $q->whereIn('uvs_contract_id', $ids));
        $found = array_column($contracts, 'uvs_contract_id');
        $missing = array_values(array_diff($ids, $found));
        $blockers = $this->schema['coaching_contracts'] ? [] : ['Die Coaching-Vertragstabelle fehlt.'];
        $requested = array_column($identities, null, 'id');
        foreach ($contracts as $contract) {
            $expected = $requested[$contract['uvs_contract_id']];
            if ((int) $contract['institut_id'] !== (int) $expected['institut_id']
                || $contract['uvs_person_id'] !== $expected['person_id'] || $contract['beratung_id'] !== $expected['beratung_id']) {
                throw new ConflictHttpException('Die Schulnetz-Vertragsidentität stimmt nicht mit dem ausgewählten UVS-Vertrag überein.');
            }
        }
        $localIds = array_column($contracts, 'id');
        foreach (['coaching_plans', 'coaching_messages', 'coaching_outbox', 'coaching_assignment_history', 'coaching_notices'] as $table) {
            $this->read($table, fn ($q) => $q->whereIn('coaching_contract_id', $localIds));
            if ($contracts !== [] && ! $this->schema[$table]) {
                $blockers[] = 'Eine benötigte Coaching-Verknüpfung fehlt: '.$table.'.';
            }
        }
        $courseIds = array_values(array_filter(array_column($contracts, 'course_id')));
        if ($this->read('coaching_contracts', fn ($q) => $q->whereNotIn('id', $localIds)->whereIn('course_id', $courseIds), false) !== []) {
            $blockers[] = 'Ein zugeordneter Kurs wird von einem weiteren Coaching-Vertrag verwendet.';
        }
        $courses = $this->read('courses', fn ($q) => $q->whereIn('id', $courseIds));
        foreach ($contracts as $contract) {
            if (! $contract['course_id']) {
                continue;
            }
            $course = collect($courses)->firstWhere('id', $contract['course_id']);
            $settings = $course ? $this->decode($course['settings'] ?? null) : [];
            if (! $course || $course['type'] !== 'coaching' || $course['klassen_id'] !== 'ec-'.$contract['uuid']
                || (int) ($settings['coaching_contract_id'] ?? 0) !== (int) $contract['id']
                || (int) $course['institut_id'] !== (int) $contract['institut_id']) {
                $blockers[] = 'Ein zugeordneter Kurs gehört nicht eindeutig zum ausgewählten Coaching-Vertrag.';
            }
        }
        foreach (['course_days', 'course_participant_enrollments', 'course_results', 'course_ratings', 'course_material_acknowledgements', 'report_books'] as $table) {
            $this->read($table, fn ($q) => $q->whereIn('course_id', $courseIds));
        }
        foreach ($contracts as $contract) {
            foreach ($this->rows['course_participant_enrollments'] ?? [] as $enrollment) {
                if ((int) $enrollment['course_id'] === (int) $contract['course_id']
                    && (int) $enrollment['person_id'] !== (int) $contract['participant_person_id']) {
                    $blockers[] = 'Ein zugeordneter Kurs enthält eine weitere Teilnehmeridentität.';
                }
            }
        }
        $books = $this->ids('report_books');
        $this->read('report_book_entries', fn ($q) => $q->whereIn('report_book_id', $books));
        if (Schema::hasTable('report_book_entries') && Schema::hasColumn('report_book_entries', 'course_day_id')
            && $this->read('report_book_entries', fn ($q) => $q->whereIn('course_day_id', $this->ids('course_days'))
                ->whereNotIn('id', $this->ids('report_book_entries')), false) !== []) {
            $blockers[] = 'Ein fremdes Berichtsheft verwendet einen ausgewählten Kurstag.';
        }

        $allowedPersonIds = array_merge($deleteParticipant ? $payload['participant_person_ids'] : [], $deleteTutors ? $payload['tutor_person_ids'] : []);
        // These identities also cover registrations which predate the first
        // contract import. Participant IDs are bound to the source contracts;
        // tutor IDs come only from the authenticated, scoped UVS cleanup graph.
        $candidates = $this->read('persons', function ($q) use ($allowedPersonIds) {
            $q->where(function ($q) use ($allowedPersonIds) {
                $q->whereRaw('1 = 0');
                foreach ($allowedPersonIds as $personId) {
                    $q->orWhere(fn ($q) => $q->where('person_id', $personId)->where('institut_id', (int) explode('-', $personId, 2)[0]));
                }
            });
        }, false);
        $candidateIds = array_column($candidates, 'id');
        $persons = [];
        $eligible = [];
        foreach ($candidates as $person) {
            try {
                $reasons = $this->personBlockers($person, $candidateIds, $localIds, $courseIds);
            } catch (QueryException|ConflictHttpException $error) {
                $reasons = ['Die vorhandenen Personenverknüpfungen können nicht sicher geprüft werden.'];
            }
            if (count(array_filter($candidates, fn ($p) => $p['person_id'] === $person['person_id'])) !== 1) {
                $reasons[] = 'Die Personenidentität ist nicht eindeutig.';
            }
            $persons[] = ['id' => $person['id'], 'person_id' => $person['person_id'], 'user_id' => $person['user_id'],
                'eligible' => $reasons === [], 'blockers' => $reasons];
            if ($reasons === []) {
                $eligible[] = $person['id'];
            }
        }
        // Never delete half of an account when another linked identity is retained.
        $retainedUserIds = array_filter(array_column(array_filter($persons, fn ($p) => ! $p['eligible']), 'user_id'));
        foreach ($persons as &$person) {
            if ($person['eligible'] && $person['user_id']) {
                $siblings = $this->read('persons', fn ($q) => $q->where('user_id', $person['user_id'])->whereNotIn('id', $eligible), false);
                if ($siblings !== [] || in_array($person['user_id'], $retainedUserIds)) {
                    $person['eligible'] = false;
                    $person['blockers'][] = 'Das Benutzerkonto besitzt eine weitere oder geschützte Personenidentität.';
                }
            }
        }
        unset($person);
        $eligible = array_column(array_filter($persons, fn ($p) => $p['eligible']), 'id');
        $this->read('persons', fn ($q) => $q->whereIn('id', $eligible));
        $userIds = array_values(array_unique(array_filter(array_column($this->rows['persons'] ?? [], 'user_id'))));
        $users = $this->read('users', fn ($q) => $q->whereIn('id', $userIds));
        $noticeMessageIds = array_values(array_filter(array_column($this->rows['coaching_notices'] ?? [], 'message_id')));
        if ($this->read('coaching_notices', fn ($q) => $q->whereNotIn('coaching_contract_id', $localIds)->whereIn('message_id', $noticeMessageIds), false) !== []) {
            $blockers[] = 'Eine zugeordnete Nachricht wird von einem weiteren Coaching-Vertrag verwendet.';
        }
        $this->read('messages', fn ($q) => $q->where(function ($q) use ($noticeMessageIds, $userIds) {
            $q->whereIn('id', $noticeMessageIds)->orWhereIn('from_user', $userIds)->orWhereIn('to_user', $userIds);
        }));
        if ($this->read('coaching_notices', fn ($q) => $q->whereNotIn('coaching_contract_id', $localIds)
            ->whereIn('message_id', $this->ids('messages')), false) !== []) {
            $blockers[] = 'Eine zu löschende Kontonachricht gehört zu einem weiteren Coaching-Vertrag.';
        }
        $this->read('user_requests', fn ($q) => $q->whereIn('user_id', $userIds));
        $this->read('onboarding_video_views', fn ($q) => $q->whereIn('user_id', $userIds));
        $this->read('sessions', fn ($q) => $q->whereIn('user_id', $userIds));
        $this->read('personal_access_tokens', fn ($q) => $q->where('tokenable_type', $this->morph(User::class))->whereIn('tokenable_id', $userIds));
        $this->read('notifications', fn ($q) => $q->where('notifiable_type', $this->morph(User::class))->whereIn('notifiable_id', $userIds));
        $this->read('password_reset_tokens', fn ($q) => $q->whereIn('email', array_column($users, 'email')));
        $this->read('teams', fn ($q) => $q->whereIn('user_id', $userIds));
        $teamIds = $this->ids('teams');
        $this->read('team_user', fn ($q) => $q->whereIn('user_id', $userIds)->orWhereIn('team_id', $teamIds));
        $this->read('team_invitations', fn ($q) => $q->whereIn('team_id', $teamIds));

        $owners = [CoachingContract::class => $localIds, CoachingPlan::class => $this->ids('coaching_plans'),
            CoachingMessage::class => $this->ids('coaching_messages'), Course::class => $courseIds,
            CourseDay::class => $this->ids('course_days'), CourseParticipantEnrollment::class => $this->ids('course_participant_enrollments'),
            CourseMaterialAcknowledgement::class => $this->ids('course_material_acknowledgements'), ReportBook::class => $books,
            ReportBookEntry::class => $this->ids('report_book_entries'), Person::class => $eligible, User::class => $userIds,
            Message::class => $this->ids('messages'), UserRequest::class => $this->ids('user_requests')];
        $this->read('admin_tasks', fn ($q) => $this->whereMorph($q, 'context', $owners));
        $activityConnection = config('activitylog.database_connection');
        if ($activityConnection && $activityConnection !== config('database.default')) {
            // An external audit database cannot participate in this transaction.
            if ($userIds !== [] || $eligible !== []) {
                throw new ConflictHttpException('Die Aktivitätsdaten liegen außerhalb der bereinigten Datenbank.');
            }
        } else {
            $this->read($this->activityTable(), function ($q) use ($owners) {
                $q->where(function ($q) use ($owners) {
                    $this->whereMorph($q, 'subject', $owners);
                    $q->orWhere(fn ($q) => $this->whereMorph($q, 'causer', $owners));
                });
            });
        }
        $this->read('file_pools', fn ($q) => $this->whereMorph($q, 'filepoolable', $owners));
        $poolIds = $this->ids('file_pools');
        $owners[FilePool::class] = $poolIds;
        $this->read('files', function ($q) use ($owners, $poolIds) {
            $q->where(function ($q) use ($owners, $poolIds) {
                $this->whereMorph($q, 'fileable', $owners);
                $q->orWhereIn('filepool_id', $poolIds);
            });
        });
        $storage = [];
        foreach ($this->rows['files'] ?? [] as $file) {
            $owned = false;
            foreach ($owners as $model => $ownerIds) {
                $owned = $owned || ($file['fileable_type'] === $this->morph($model) && in_array($file['fileable_id'], $ownerIds));
            }
            if (! $owned) {
                $blockers[] = 'Eine Pool-Datei gehört zusätzlich zu einem anderen Objekt.';
            }
            $this->storagePath($storage, $blockers, ($file['disk'] ?? null) ?: 'private', $file['path']);
        }
        foreach ($this->rows['course_material_acknowledgements'] ?? [] as $ack) {
            if (! empty($ack['signature_path'])) {
                $this->storagePath($storage, $blockers, 'private', $ack['signature_path']);
            }
        }
        foreach ($users as $user) {
            if (! empty($user['profile_photo_path'])) {
                $this->storagePath($storage, $blockers, config('jetstream.profile_photo_disk', 'public'), $user['profile_photo_path']);
            }
        }
        foreach ($this->rows['user_requests'] ?? [] as $request) {
            if (! empty($request['attachment_path'])) {
                $this->storagePath($storage, $blockers, 'private', $request['attachment_path']);
            }
        }
        ksort($this->rows);
        ksort($this->schema);
        $counts = array_map('count', $this->rows);

        return ['counts' => $counts, 'persons' => $persons, 'blockers' => array_values(array_unique($blockers)),
            'missing_ids' => $missing, 'contract_ids' => $ids, 'contracts' => $identities,
            'rows' => $this->rows, 'schema' => $this->schema, 'storage' => array_values($storage)];
    }

    private function personBlockers(array $person, array $candidateIds, array $contractIds, array $courseIds): array
    {
        $id = $person['id'];
        $reasons = [];
        if (! in_array($person['role'] ?? null, [null, 'guest', 'participant', 'tutor'], true)) {
            $reasons[] = 'Diese Personenrolle ist geschützt.';
        }
        $program = $this->decode($person['programdata'] ?? null);
        $status = $this->decode($person['statusdata'] ?? null);
        if (! empty($program['tn_baust']) || ! empty($status['vertraege']) || ! empty($program['teilnehmer_id'])
            || ! empty($status['teilnehmer_id']) || ! empty($status['teilnehmer_nr'])) {
            $reasons[] = 'Die Person besitzt weitere reguläre Teilnehmerdaten.';
        }
        $outside = $this->read('coaching_contracts', fn ($q) => $q->whereNotIn('id', $contractIds)->where(function ($q) use ($id, $person) {
            $q->where('participant_person_id', $id)->orWhere('tutor_person_id', $id)
                ->orWhere('uvs_person_id', $person['person_id'])->orWhere('uvs_tutor_person_id', $person['person_id']);
        }), false);
        foreach (['coaching_plans' => ['participant_person_id', 'tutor_person_id'], 'coaching_assignment_history' => ['tutor_person_id']] as $table => $columns) {
            $outside = array_merge($outside, $this->read($table, fn ($q) => $q->whereNotIn('coaching_contract_id', $contractIds)->where(function ($q) use ($id, $columns) {
                foreach ($columns as $column) {
                    $q->orWhere($column, $id);
                }
            }), false));
        }
        if ($outside !== []) {
            $reasons[] = 'Die Person gehört zu weiteren Coaching-Verträgen.';
        }
        if ($this->read('coaching_notices', fn ($q) => $q->whereNotIn('coaching_contract_id', $contractIds)
            ->where('uvs_person_id', $person['person_id']), false) !== []) {
            $reasons[] = 'Die Person besitzt Benachrichtigungen zu weiteren Coaching-Verträgen.';
        }
        foreach (['course_participant_enrollments' => 'person_id', 'course_results' => 'person_id', 'course_material_acknowledgements' => 'person_id'] as $table => $column) {
            if ($this->read($table, fn ($q) => $q->where($column, $id)->whereNotIn('course_id', $courseIds), false) !== []) {
                $reasons[] = 'Die Person besitzt weitere Kursdaten.';
            }
        }
        if ($this->read('courses', fn ($q) => $q->where('primary_tutor_person_id', $id)->whereNotIn('id', $courseIds), false) !== []) {
            $reasons[] = 'Der Dozent gehört zu weiteren Kursen.';
        }
        if ($person['user_id']) {
            $users = $this->read('users', fn ($q) => $q->where('id', $person['user_id']), false);
            $user = $users[0] ?? null;
            if ($user && ((int) $user['id'] === 1 || ! in_array($user['role'], ['guest', 'tutor'], true))) {
                $reasons[] = 'Dieses Benutzerkonto ist geschützt.';
            }
            if ($this->read('persons', fn ($q) => $q->where('user_id', $person['user_id'])->whereNotIn('id', $candidateIds), false) !== []) {
                $reasons[] = 'Das Benutzerkonto besitzt weitere Personenidentitäten.';
            }
            if ($this->read('report_books', fn ($q) => $q->where('user_id', $person['user_id'])->where(fn ($q) => $q->whereNull('course_id')->orWhereNotIn('course_id', $courseIds)), false) !== []) {
                $reasons[] = 'Das Benutzerkonto besitzt weitere Berichtshefte.';
            }
            if ($this->read('customers', fn ($q) => $q->where('user_id', $person['user_id']), false) !== []) {
                $reasons[] = 'Das Benutzerkonto besitzt weitere Kundendaten.';
            }
            if ($this->read('course_ratings', fn ($q) => $q->whereNotIn('course_id', $courseIds)->where(fn ($q) => $q->where('user_id', $person['user_id'])->orWhere('participant_id', $person['user_id'])), false) !== []) {
                $reasons[] = 'Das Benutzerkonto besitzt weitere Kursbewertungen.';
            }
            $teams = $this->read('teams', fn ($q) => $q->where('user_id', $person['user_id']), false);
            if ($this->read('team_user', fn ($q) => $q->whereIn('team_id', array_column($teams, 'id'))->where('user_id', '<>', $person['user_id']), false) !== []) {
                $reasons[] = 'Das Benutzerkonto besitzt ein gemeinsam verwendetes Team.';
            }
        }

        if ($this->hasAdditionalPersonReferences($person)) {
            $reasons[] = 'Die Person oder ihr Konto wird außerhalb der ausgewählten Daten verwendet.';
        }

        return array_values(array_unique($reasons));
    }

    private function hasAdditionalPersonReferences(array $person): bool
    {
        $personalTables = ['persons', 'users', 'messages', 'user_requests', 'onboarding_video_views', 'sessions',
            'personal_access_tokens', 'notifications', 'password_reset_tokens', 'teams', 'team_user', 'team_invitations', $this->activityTable()];
        $owners = [Person::class => [$person['id']], User::class => array_filter([$person['user_id']]),
            CoachingContract::class => $this->ids('coaching_contracts'), CoachingPlan::class => $this->ids('coaching_plans'),
            CoachingMessage::class => $this->ids('coaching_messages'), Course::class => $this->ids('courses'),
            CourseDay::class => $this->ids('course_days'), CourseParticipantEnrollment::class => $this->ids('course_participant_enrollments'),
            CourseMaterialAcknowledgement::class => $this->ids('course_material_acknowledgements'),
            ReportBook::class => $this->ids('report_books'), ReportBookEntry::class => $this->ids('report_book_entries')];
        $requests = $this->read('user_requests', fn ($q) => $q->where('user_id', $person['user_id']), false);
        $owners[UserRequest::class] = array_column($requests, 'id');
        $pools = $this->read('file_pools', fn ($q) => $this->whereMorph($q, 'filepoolable', $owners), false);
        $owners[FilePool::class] = array_column($pools, 'id');
        foreach (Schema::getTableListing() as $table) {
            if (in_array($table, $personalTables, true)) {
                continue;
            }
            $columns = $this->tableColumns($table);
            $query = DB::table($table);
            $hasReference = false;
            $query->where(function ($q) use ($columns, $person, &$hasReference) {
                $q->whereRaw('1 = 0');
                foreach ($columns as $column) {
                    if ($column === 'person_id' || str_ends_with($column, '_person_id')) {
                        if (str_starts_with($column, 'uvs_')) {
                            continue;
                        }
                        $q->orWhere($column, $person['id']);
                        $hasReference = true;
                    } elseif ($person['user_id'] && ($column === 'user_id' || str_ends_with($column, '_user_id')
                        || in_array($column, ['created_by', 'assigned_by', 'assigned_to', 'from_user', 'to_user', 'participant_id'], true))) {
                        $q->orWhere($column, $person['user_id']);
                        $hasReference = true;
                    }
                    if (str_ends_with($column, '_type') && in_array($idColumn = substr($column, 0, -5).'_id', $columns, true)) {
                        $q->orWhere(fn ($q) => $q->where($column, $this->morph(Person::class))->where($idColumn, $person['id']));
                        if ($person['user_id']) {
                            $q->orWhere(fn ($q) => $q->where($column, $this->morph(User::class))->where($idColumn, $person['user_id']));
                        }
                        $hasReference = true;
                    }
                }
            });
            if (! $hasReference) {
                continue;
            }
            if (in_array('id', $columns, true)) {
                $query->whereNotIn('id', $this->ids($table));
            }
            // A file/task referring to the person itself or this contract graph
            // is removed below. A user merely uploading a foreign file is retained.
            $prefix = ['files' => 'fileable', 'file_pools' => 'filepoolable', 'admin_tasks' => 'context'][$table] ?? null;
            if ($prefix) {
                $query->where(fn ($q) => $q->whereNull($prefix.'_type')->orWhereNull($prefix.'_id')
                    ->orWhereNot(fn ($q) => $this->whereMorph($q, $prefix, $owners)));
            }
            if ($this->locking) {
                $query->lockForUpdate();
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function tableColumns(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }

    private function activityTable(): string
    {
        return config('activitylog.table_name', 'activity_log');
    }

    private function read(string $table, Closure $scope, bool $selected = true): array
    {
        $this->schema[$table] = $this->schema[$table] ?? Schema::hasTable($table);
        if (! $this->schema[$table]) {
            return [];
        }
        $query = DB::table($table);
        $scope($query);
        $key = $table === 'password_reset_tokens' ? 'email' : 'id';
        if (! in_array($key, $this->tableColumns($table), true)) {
            throw new ConflictHttpException('Eine vorhandene Datentabelle besitzt keinen sicher löschbaren Schlüssel.');
        }
        if ($this->locking) {
            $query->lockForUpdate();
        }
        $rows = $query->orderBy($key)->get()->map(fn ($row) => (array) $row)->all();
        if ($selected) {
            $this->rows[$table] = $rows;
        }

        return $rows;
    }

    private function ids(string $table): array
    {
        return array_column($this->rows[$table] ?? [], 'id');
    }

    private function morph(string $model): string
    {
        return (new $model)->getMorphClass();
    }

    private function whereMorph(Builder $query, string $prefix, array $owners): void
    {
        $query->where(function ($query) use ($prefix, $owners) {
            $query->whereRaw('1 = 0');
            foreach ($owners as $model => $ids) {
                if ($ids !== []) {
                    $query->orWhere(fn ($q) => $q->where($prefix.'_type', $this->morph($model))->whereIn($prefix.'_id', $ids));
                }
            }
        });
    }

    private function decode(mixed $value): array
    {
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function storagePath(array &$storage, array &$blockers, string $disk, string $path): void
    {
        if (! in_array($disk, ['private', 'public'], true) || $path === '' || preg_match('~(^/|^[A-Za-z]:|\\\\|[\x00-\x1f]|(?:^|/)\.{1,2}(?:/|$)|//|/$)~', $path)) {
            $blockers[] = 'Eine zugeordnete Datei besitzt einen unsicheren Speicherpfad.';

            return;
        }
        $storage[$disk.'|'.$path] = [$disk, $path];
    }

    private function pathStillReferenced(string $disk, string $path): bool
    {
        $references = [];
        if (Schema::hasTable('files')) {
            $query = DB::table('files');
            if (Schema::hasColumn('files', 'disk')) {
                $query->where(fn ($q) => $q->where('disk', $disk)->when($disk === 'private', fn ($q) => $q->orWhereNull('disk')));
            } elseif ($disk !== 'private') {
                $query->whereRaw('1 = 0');
            }
            $references = $query->pluck('path')->all();
        }
        foreach (['course_material_acknowledgements' => ['signature_path', 'private'], 'user_requests' => ['attachment_path', 'private'],
            'users' => ['profile_photo_path', config('jetstream.profile_photo_disk', 'public')]] as $table => [$column, $referenceDisk]) {
            if ($disk === $referenceDisk && Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $references = array_merge($references, DB::table($table)->whereNotNull($column)->pluck($column)->all());
            }
        }
        foreach ($references as $reference) {
            try {
                if ((new WhitespacePathNormalizer)->normalizePath((string) $reference) === $path) {
                    return true;
                }
            } catch (\Throwable $error) {
                // An invalid surviving path cannot safely establish isolation.
                return true;
            }
        }

        return false;
    }
}
