<?php

namespace App\Services\ApiUvs;

use App\Models\Person;
use Illuminate\Support\Facades\Log;

class PersonUvsSyncService
{
    public function sync(Person $person): array
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

        $statusResp = $api->getPersonStatus($person->person_id) ?? null;
        $statusData = $statusResp['data']['data'] ?? [];
        if (! is_array($statusData)) {
            $statusData = [];
        }

        $mitarbeiterVertragKy = strtoupper(trim((string) ($statusData['mitarbeiter_vertrag_ky'] ?? '')));
        $isTutor = filter_var($statusData['is_tutor'] ?? false, FILTER_VALIDATE_BOOL) || $mitarbeiterVertragKy === 'IS';
        $mitarbeiterIdFromStatus = trim((string) ($statusData['mitarbeiter_id'] ?? '')) ?: null;
        $teilnehmerIdFromStatus = $statusData['teilnehmer_id'] ?? data_get($statusData, 'vertraege.0.teilnehmer_id');
        $hasParticipantContext = ! empty($statusData['teilnehmer_nr']) || ! empty($teilnehmerIdFromStatus);
        $hasActiveParticipantContract = $this->hasActiveParticipantContract($statusData);
        $keepParticipantIdentity = ! $isTutor || $hasActiveParticipantContract;

        $role = $isTutor ? 'tutor' : 'guest';

        if (! $isTutor && ! $hasParticipantContext) {
            $programData = null;

            if (config('api_sync.debug_logs', false)) {
                Log::info("PersonUvsSyncService: Kein Teilnehmer- oder Tutor-Kontext fuer person_id={$person->person_id}");
            }
        } else {
            if ($isTutor) {
                if (! $this->looksLikeTutorProgramData($programData)) {
                    $programData = null;
                }

                $apiResponse = $api->getTutorProgramDataByPersonId($person->person_id);
                if (($apiResponse['ok'] ?? false) === true) {
                    $data = $apiResponse['data'] ?? null;
                    $programDataRaw = ! empty($data['data']) ? $data['data'] : null;
                } else {
                    $programDataRaw = null;
                }

                if ($programDataRaw) {
                    $programData = $programDataRaw;
                } elseif (config('api_sync.debug_logs', false)) {
                    Log::info('PersonUvsSyncService: No Tutor program data found.', [
                        'person_id' => $person->person_id,
                        'api_response' => $apiResponse ?? null,
                    ]);
                }
            } else {
                $apiResponse = $api->getParticipantAndQualiprogrambyId($person->person_id);
                if (($apiResponse['ok'] ?? false) === true) {
                    $data = $apiResponse['data'] ?? null;
                    $qualiData = ! empty($data['quali_data']) ? $data['quali_data'] : null;
                } else {
                    $qualiData = null;
                }

                if ($qualiData) {
                    $programData = $qualiData;
                } elseif (config('api_sync.debug_logs', false)) {
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
            ? ($statusData['teilnehmer_nr'] ?? data_get($programData, 'teilnehmer_nr'))
            : null;
        $teilnehmerIdFallback = $statusData['teilnehmer_id']
            ?? ($teilnehmerNr
                ? (($statusData['institut_id'] ?? null) ? $statusData['institut_id'] . '-' . $teilnehmerNr : null)
                : data_get($statusData, 'vertraege.0.teilnehmer_id'));
        $teilnehmerId = ($keepParticipantIdentity && $hasParticipantContext)
            ? (data_get($programData, 'teilnehmer_id') ?? $teilnehmerIdFallback)
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
        ])->save();

        $shouldDispatchCourseSync = $person->user_id != null
            && $person->programdata != null
            && ($programDataChanged || $lastApiUpdate == null || $lastApiUpdate->lt(now()->subDays(2)));

        if ($shouldDispatchCourseSync) {
            $checkPersonsCoursesClass = 'App\\Jobs\\ApiUpdates\\CheckPersonsCourses';
            if (class_exists($checkPersonsCoursesClass)) {
                $checkPersonsCoursesClass::dispatch($person->id);
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

    protected function hasActiveParticipantContract(array $statusData): bool
    {
        $status = strtolower(trim((string) ($statusData['status'] ?? '')));
        $statusShort = strtoupper(trim((string) ($statusData['status_short'] ?? '')));

        if ($status === 'teilnehmer' || $statusShort === 'TN') {
            return true;
        }

        $contracts = data_get($statusData, 'vertraege', []);
        if (! is_array($contracts)) {
            return false;
        }

        foreach ($contracts as $contract) {
            if (! is_array($contract)) {
                continue;
            }

            if (filter_var($contract['is_active'] ?? false, FILTER_VALIDATE_BOOL)) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikeTutorProgramData(?array $programData): bool
    {
        if (empty($programData)) {
            return false;
        }

        return isset($programData['tutor']) || isset($programData['courses']) || isset($programData['themes']);
    }
}
