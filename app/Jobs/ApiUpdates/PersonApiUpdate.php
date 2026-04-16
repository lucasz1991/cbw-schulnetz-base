<?php

namespace App\Jobs\ApiUpdates;

use App\Jobs\ApiUpdates\CheckPersonsCourses;
use App\Models\Person;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PersonApiUpdate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $personPk)
    {
        $this->personPk = $personPk;
    }

    public function uniqueId(): string
    {
        return 'person-api-update:' . (string) $this->personPk;
    }

    public function handle(): void
    {
        $api = app(ApiUvsService::class);
        $person = Person::find($this->personPk);

        if (! $person) {
            if (config('api_sync.debug_logs', false)) {
                Log::warning("PersonApiUpdate: Person {$this->personPk} nicht gefunden.");
            }
            return;

        }

        if (empty($person->person_id)) {
            if (config('api_sync.debug_logs', false)) {
                Log::warning("PersonApiUpdate: 'person_id' leer fuer persons.id={$person->id}.");
            }
            return;
        }

        $programData = is_array($person->programdata) ? $person->programdata : null;
        $oldProgramHash = md5(json_encode($programData ?? []));

        // 1) Status 
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

        $role = $isTutor ? 'tutor' : 'guest';

        // 2) Programmdaten
        if (! $isTutor && ! $hasParticipantContext) {
            $programData = null;

            if (config('api_sync.debug_logs', false)) {
                Log::info("PersonApiUpdate: Kein Teilnehmer- oder Tutor-Kontext fuer person_id={$person->person_id}");
            }
        } else {
            if ($isTutor) {
                $apiResponse = $api->getTutorProgramDataByPersonId($person->person_id);
                if (($apiResponse['ok'] ?? false) === true) {
                    $data = $apiResponse['data'] ?? null;
                    $programDataRaw = ! empty($data['data']) ? $data['data'] : null;
                } else {
                    $programDataRaw = null;
                }

                if ($programDataRaw) {
                    $programData = $programDataRaw;
                } else {
                    if (config('api_sync.debug_logs', false)) {
                        Log::info('PersonApiUpdate: No Tutor program data found.', [
                            'person_id' => $person->person_id,
                            'api_response' => $apiResponse ?? null,
                        ]);
                    }
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
                } else {
                    if (config('api_sync.debug_logs', false)) {
                        Log::info('PersonApiUpdate: No Qualiprogram data found.', [
                            'person_id' => $person->person_id,
                            'api_response' => $apiResponse ?? null,
                        ]);
                    }
                }
            }
        }

        $newProgramHash = md5(json_encode($programData ?? []));
        $programDataChanged = $oldProgramHash !== $newProgramHash;
        $lastApiUpdate = $person->last_api_update;

        $teilnehmerNr = $statusData['teilnehmer_nr'] ?? ($programData['teilnehmer_nr'] ?? null);
        $teilnehmerIdFallback = $statusData['teilnehmer_id']
            ?? ($teilnehmerNr
                ? (($statusData['institut_id'] ?? null) ? $statusData['institut_id'] . '-' . $teilnehmerNr : null)
                : data_get($statusData, 'vertraege.0.teilnehmer_id'));
        $teilnehmerId = $hasParticipantContext ? ($programData['teilnehmer_id'] ?? $teilnehmerIdFallback) : null;
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

            if (! $person->trashed()) {
                $person->delete();
            }

            if (config('api_sync.debug_logs', false)) {
                Log::info('PersonApiUpdate: Person soft-deleted due to missing teilnehmer_id and mitarbeiter_id.', [
                    'person_pk' => $this->personPk,
                    'uvs_person_id' => $person->person_id,
                ]);
            }

            return;
        }

        // 3) Persist
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
            && $programDataChanged
            || $lastApiUpdate == null || $lastApiUpdate->lt(now()->subDays(2)); // Immer Course-Sync dispatchen, wenn es das erste API-Update ist (z.B. nach Anlegen der Person)


        if (config('api_sync.debug_logs', false)) {
            Log::info('PersonApiUpdate summary', [
                'person_pk' => $this->personPk,
                'person_id' => $person->id,
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

        if ($shouldDispatchCourseSync) {
            CheckPersonsCourses::dispatch($person->id);
        }
    }
}
