<?php

namespace App\Services\ApiUvs;

use App\Models\Person;
use App\Support\ParticipantContractAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PersonUvsSyncService
{
    public function sync(
        Person $person,
        ?array $providedStatusData = null,
        bool $withoutCooldown = false,
    ): array
    {
        if (empty($person->person_id)) {
            return [
                'ok' => false,
                'reason' => 'missing_person_id',
                'person_pk' => $person->id,
            ];
        }

        $api = app(ApiUvsService::class);
        $programData = is_array($person->programdata) ? $person->programdata : null;
        $oldProgramHash = md5(json_encode($programData ?? []));

        if ($providedStatusData !== null) {
            $statusData = $providedStatusData;
        } else {
            $statusResp = $api->getPersonStatus($person->person_id) ?? null;
            if (($statusResp['ok'] ?? false) !== true) {
                Log::warning('PersonUvsSyncService: PersonStatus konnte nicht geladen werden.', [
                    'person_pk' => $person->id,
                    'uvs_person_id' => $person->person_id,
                    'status' => $statusResp['status'] ?? null,
                    'message' => $statusResp['message'] ?? null,
                ]);

                return [
                    'ok' => false,
                    'reason' => 'person_status_failed',
                    'person_pk' => $person->id,
                    'status' => $statusResp['status'] ?? null,
                ];
            }

            $statusData = $statusResp['data']['data'] ?? [];
            if (! is_array($statusData)) {
                $statusData = [];
            }
        }

        $mitarbeiterVertragKy = strtoupper(trim((string) ($statusData['mitarbeiter_vertrag_ky'] ?? '')));
        $isTutor = filter_var($statusData['is_tutor'] ?? false, FILTER_VALIDATE_BOOL) || $mitarbeiterVertragKy === 'IS';
        $mitarbeiterIdFromStatus = trim((string) ($statusData['mitarbeiter_id'] ?? '')) ?: null;
        $configuredDays = ParticipantContractAccess::configuredDays();
        $participantContracts = data_get($statusData, 'vertraege', []);
        $hasKnownParticipantContracts = is_array($participantContracts)
            && collect($participantContracts)->contains(fn ($contract) => is_array($contract));
        $selectedParticipantContract = ParticipantContractAccess::currentContract(
            $participantContracts,
            $configuredDays['open_before_days'],
            $configuredDays['close_after_days']
        );
        $teilnehmerIdFromStatus = data_get($selectedParticipantContract, 'teilnehmer_id')
            ?? ($hasKnownParticipantContracts ? null : ($statusData['teilnehmer_id']
                ?? data_get($statusData, 'vertraege.0.teilnehmer_id')));
        $hasParticipantContext = ! empty($statusData['teilnehmer_nr']) || ! empty($teilnehmerIdFromStatus);
        $hasActiveParticipantContract = $this->hasActiveParticipantContract(
            $statusData,
            $configuredDays['open_before_days'],
            $configuredDays['close_after_days']
        );
        $tutorProgramData = null;

        if ($isTutor || ! $hasActiveParticipantContract) {
            $tutorProgramData = $this->loadTutorProgramData($api, $person);

            if (! $isTutor && $this->looksLikeTutorProgramData($tutorProgramData)) {
                $isTutor = true;
                $mitarbeiterIdFromStatus = $mitarbeiterIdFromStatus ?: data_get($tutorProgramData, 'tutor.mitarbeiter_id');

                Log::warning('PersonUvsSyncService: Tutorstatus ueber Tutorprogramm-Fallback erkannt.', [
                    'person_pk' => $person->id,
                    'uvs_person_id' => $person->person_id,
                    'mitarbeiter_id' => $mitarbeiterIdFromStatus,
                    'status_is_tutor' => $statusData['is_tutor'] ?? null,
                    'mitarbeiter_vertrag_ky' => $mitarbeiterVertragKy ?: null,
                ]);
            }
        }

        $keepParticipantIdentity = ! $isTutor || $hasActiveParticipantContract;
        $role = $isTutor ? 'tutor' : 'guest';

        if (! $isTutor && ! $hasActiveParticipantContract) {
            $programData = null;

            if (config('api_sync.debug_logs', false)) {
                Log::info("PersonUvsSyncService: Kein Teilnehmer- oder Tutor-Kontext fuer person_id={$person->person_id}");
            }
        } else {
            if ($isTutor) {
                if (! $this->looksLikeTutorProgramData($programData)) {
                    $programData = null;
                }

                $programDataRaw = $tutorProgramData;

                if ($programDataRaw) {
                    $programData = $programDataRaw;
                } elseif (config('api_sync.debug_logs', false)) {
                    Log::info('PersonUvsSyncService: No Tutor program data found.', [
                        'person_id' => $person->person_id,
                    ]);
                }
            } else {
                // Bind the program request to the locally selected contract.
                // Access grace periods stay valid, but a newer contract that
                // has actually begun replaces the older program context.
                $selectedBeratungId = trim((string) (
                    data_get($selectedParticipantContract, 'beratung_id')
                    ?? ($hasKnownParticipantContracts ? null : ($statusData['beratung_id'] ?? null))
                    ?? ''
                )) ?: null;
                $selectedTeilnehmerId = trim((string) (
                    data_get($selectedParticipantContract, 'teilnehmer_id')
                    ?? ($hasKnownParticipantContracts ? null : ($statusData['teilnehmer_id'] ?? null))
                    ?? ''
                )) ?: null;
                $storedProgramTeilnehmerId = trim((string) data_get($programData, 'teilnehmer_id', '')) ?: null;

                if (
                    $selectedTeilnehmerId !== null
                    && $storedProgramTeilnehmerId !== null
                    && $selectedTeilnehmerId !== $storedProgramTeilnehmerId
                ) {
                    $programData = null;
                }

                $apiResponse = $api->getParticipantAndQualiprogrambyId(
                    $person->person_id,
                    $selectedBeratungId
                );
                if (($apiResponse['ok'] ?? false) === true) {
                    $data = $apiResponse['data'] ?? null;
                    $qualiData = ! empty($data['quali_data']) ? $data['quali_data'] : null;
                } else {
                    $qualiData = null;
                }

                $programTeilnehmerId = trim((string) data_get($qualiData, 'teilnehmer_id', '')) ?: null;
                $hasContractMismatch = $selectedTeilnehmerId !== null
                    && $programTeilnehmerId !== null
                    && $selectedTeilnehmerId !== $programTeilnehmerId;

                if ($hasContractMismatch) {
                    Log::warning('PersonUvsSyncService: Vertragsauswahl von Status und Qualiprogramm weicht ab.', [
                        'person_pk' => $person->id,
                        'uvs_person_id' => $person->person_id,
                        'status_teilnehmer_id' => $selectedTeilnehmerId,
                        'program_teilnehmer_id' => $programTeilnehmerId,
                        'beratung_id' => $selectedBeratungId,
                    ]);

                    $qualiData = null;

                    if (trim((string) data_get($programData, 'teilnehmer_id', '')) !== $selectedTeilnehmerId) {
                        $programData = null;
                    }
                }

                if ($qualiData) {
                    $programData = $qualiData;
                } elseif (! $hasContractMismatch && config('api_sync.debug_logs', false)) {
                    Log::info('PersonUvsSyncService: No Qualiprogram data found.', [
                        'person_id' => $person->person_id,
                        'api_response' => $apiResponse ?? null,
                    ]);
                }
            }
        }

        $newProgramHash = md5(json_encode($programData ?? []));
        $programDataChanged = $oldProgramHash !== $newProgramHash;
        $lastApiUpdate = $person->last_api_update;

        $teilnehmerNr = $keepParticipantIdentity
            ? (data_get($selectedParticipantContract, 'teilnehmer_nr')
                ?? $statusData['teilnehmer_nr']
                ?? data_get($programData, 'teilnehmer_nr'))
            : null;
        $teilnehmerIdFallback = data_get($selectedParticipantContract, 'teilnehmer_id')
            ?? $statusData['teilnehmer_id']
            ?? ($teilnehmerNr
                ? (($statusData['institut_id'] ?? null) ? $statusData['institut_id'].'-'.$teilnehmerNr : null)
                : data_get($statusData, 'vertraege.0.teilnehmer_id'));
        $teilnehmerId = ($keepParticipantIdentity && $hasParticipantContext)
            ? ($teilnehmerIdFallback ?? data_get($programData, 'teilnehmer_id'))
            : null;
        $mitarbeiterId = $isTutor ? ($mitarbeiterIdFromStatus ?: data_get($programData, 'tutor.mitarbeiter_id')) : null;
        $hasPortalIdentity = ! empty($teilnehmerId) || ! empty($mitarbeiterId);

        if (! $hasPortalIdentity) {
            $person->fill([
                'teilnehmer_nr' => null,
                'teilnehmer_id' => null,
                'role' => 'guest',
                'statusdata' => $statusData,
                'programdata' => null,
                'last_api_update' => now(),
            ]);

            $person->saveQuietly();
            $this->softDeleteIfSupported($person);

            if (config('api_sync.debug_logs', false)) {
                Log::info('PersonUvsSyncService: Person soft-deleted due to missing teilnehmer_id and mitarbeiter_id.', [
                    'person_pk' => $person->id,
                    'uvs_person_id' => $person->person_id,
                ]);
            }

            return [
                'ok' => true,
                'person_pk' => $person->id,
                'role' => 'guest',
                'soft_deleted' => true,
                'has_portal_identity' => false,
                'programdata_changed' => $programDataChanged,
                'course_sync_dispatched' => false,
            ];
        }

        $this->restoreIfSupported($person);

        $person->fill([
            'teilnehmer_nr' => $teilnehmerNr,
            'teilnehmer_id' => $teilnehmerId,
            'role' => $role,
            'statusdata' => $statusData,
            'programdata' => $programData ?? null,
            'last_api_update' => now(),
        ])->saveQuietly();

        $person->user?->syncPortalRoleFromPersons();

        $shouldDispatchCourseSync = $person->programdata != null
            && ($withoutCooldown || (
                $person->user_id != null
                && ($programDataChanged || $lastApiUpdate == null || $lastApiUpdate->lt(now()->subDays(2)))
            ));

        if ($shouldDispatchCourseSync) {
            $checkPersonsCoursesClass = 'App\\Jobs\\ApiUpdates\\CheckPersonsCourses';
            if (class_exists($checkPersonsCoursesClass)) {
                $checkPersonsCoursesClass::dispatch($person->id, $withoutCooldown);
            }
        }

        if (config('api_sync.debug_logs', false)) {
            Log::info('PersonUvsSyncService summary', [
                'person_pk' => $person->id,
                'uvs_person_id' => $person->person_id,
                'role' => $role,
                'is_tutor' => $isTutor,
                'mitarbeiter_vertrag_ky' => $mitarbeiterVertragKy ?: null,
                'programdata_changed' => $programDataChanged,
                'user_linked' => $person->user_id != null,
                'programdata_present' => $person->programdata != null,
                'dispatch_check_persons_courses' => $shouldDispatchCourseSync,
                'without_cooldown' => $withoutCooldown,
            ]);
        }

        return [
            'ok' => true,
            'person_pk' => $person->id,
            'role' => $role,
            'soft_deleted' => false,
            'has_portal_identity' => true,
            'programdata_changed' => $programDataChanged,
            'course_sync_dispatched' => $shouldDispatchCourseSync,
        ];
    }

    protected function softDeleteIfSupported(Person $person): void
    {
        if (! method_exists($person, 'trashed')) {
            return;
        }

        if (! $person->trashed()) {
            $person->delete();
        }
    }

    protected function restoreIfSupported(Person $person): void
    {
        if (! method_exists($person, 'trashed') || ! $person->trashed()) {
            return;
        }

        if (method_exists($person, 'restoreQuietly')) {
            $person->restoreQuietly();

            return;
        }

        $person->restore();
    }

    protected function loadTutorProgramData(ApiUvsService $api, Person $person): ?array
    {
        try {
            $apiResponse = $api->getTutorProgramDataByPersonId($person->person_id);
        } catch (\Throwable $e) {
            Log::warning('PersonUvsSyncService: Tutorprogramm konnte nicht geladen werden.', [
                'person_pk' => $person->id,
                'uvs_person_id' => $person->person_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (($apiResponse['ok'] ?? false) !== true) {
            if (config('api_sync.debug_logs', false)) {
                Log::info('PersonUvsSyncService: Tutorprogramm API lieferte keinen Erfolg.', [
                    'person_pk' => $person->id,
                    'uvs_person_id' => $person->person_id,
                    'status' => $apiResponse['status'] ?? null,
                    'message' => $apiResponse['message'] ?? null,
                ]);
            }

            return null;
        }

        $data = $apiResponse['data'] ?? null;
        $programData = is_array($data) && ! empty($data['data']) && is_array($data['data'])
            ? $data['data']
            : null;

        return $this->looksLikeTutorProgramData($programData) ? $programData : null;
    }

    protected function hasActiveParticipantContract(
        array $statusData,
        ?int $openBeforeDays = null,
        ?int $closeAfterDays = null
    ): bool {
        $contracts = data_get($statusData, 'vertraege', []);
        if (is_array($contracts) && collect($contracts)->contains(fn ($contract) => is_array($contract))) {
            if ($openBeforeDays === null || $closeAfterDays === null) {
                $configuredDays = ParticipantContractAccess::configuredDays();
                $openBeforeDays ??= $configuredDays['open_before_days'];
                $closeAfterDays ??= $configuredDays['close_after_days'];
            }

            return ParticipantContractAccess::currentContract(
                $contracts,
                $openBeforeDays,
                $closeAfterDays
            ) !== null;
        }

        $status = strtolower(trim((string) ($statusData['status'] ?? '')));
        $statusShort = strtoupper(trim((string) ($statusData['status_short'] ?? '')));

        return $status === 'teilnehmer' || $statusShort === 'TN';
    }

    protected function parseUvsDate(mixed $value): ?Carbon
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        foreach (['Y/m/d', 'Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw)->startOfDay();
            } catch (\Throwable) {
                // Try the next known UVS format.
            }
        }

        return null;
    }

    protected function looksLikeTutorProgramData(?array $programData): bool
    {
        if (empty($programData)) {
            return false;
        }

        return isset($programData['tutor']) || isset($programData['courses']) || isset($programData['themes']);
    }
}
