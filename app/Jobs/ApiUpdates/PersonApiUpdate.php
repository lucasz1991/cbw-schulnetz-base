<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Person;
use App\Services\ApiUvs\ApiUvsService;
use App\Jobs\ApiUpdates\CheckPersonsCourses;


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
    public $tries = 3;
    public $backoff = [10, 60, 180];

    // ← Constructor Property Promotion: garantiert initialisiert
    public function __construct(public int $personPk) 
    {
        $this->personPk = $personPk;
    }


    public function uniqueId(): string
    {
        // sicher casten, falls PHP strikte Typen anmeckert
        return 'person-api-update:' . (string) $this->personPk;
    }

    public function handle(): void
    {
        $api = app(ApiUvsService::class);
        $person = Person::find($this->personPk);
        if (!$person) {
            Log::warning("PersonApiUpdate: Person {$this->personPk} nicht gefunden.");
            return;
        }
        if (empty($person->person_id)) {
            Log::warning("PersonApiUpdate: 'person_id' leer für persons.id={$person->id}.");
            return;
        } 

        // 1) Status
        $statusResp = $api->getPersonStatus($person->person_id) ?? null;
        $statusData = $statusResp['data']['data'] ?? null;
        $role = (is_array($statusData) && !empty($statusData['mitarbeiter_nr'])) ? 'tutor' : 'guest';

        // 2) Programmdaten
        if ($statusData['teilnehmer_nr'] == null && $statusData['mitarbeiter_nr'] == null) {
            Log::info("PersonApiUpdate: Keine Teilnehmer- oder Mitarbeiternummer für person_id={$person->person_id}");
        } else {
            if ($role === 'guest') {
                $apiResponse = $api->getParticipantAndQualiprogrambyId($person->person_id);
                if ($apiResponse['ok']) {
                    $data = $apiResponse['data'] ? $apiResponse['data'] : null;
                    $quali_data = !empty($data['quali_data']) ? $data['quali_data'] : null;
                } else {
                    $quali_data = null;
                }
                if ($quali_data) {
                    $programData = $quali_data;
                } else {
                    Log::info("No Qualiprogram data found for person_id {$person->person_id }. API response: " . json_encode($apiResponse));
                }
            } else {
                $apiResponse = $api->getTutorProgramDataByPersonId($person->person_id);
                if ($apiResponse['ok']) {
                    $data = $apiResponse['data'] ? $apiResponse['data'] : null;
                    $program_data = !empty($data['data']) ? $data['data'] : null;
                } else {
                    $program_data = null;
                }
                if ($program_data) {
                    $programData = $program_data;
                } else {
                    Log::info("No Tutor program data found for person_id {$person->person_id }. API response: " . json_encode($apiResponse));
                }
            }
        }


        // 3) Persist
        $person->fill([
            'teilnehmer_nr'   => $programData['teilnehmer_nr'] ?? null,
            'teilnehmer_id'   => $programData['teilnehmer_id'] ?? null,
            'role'            => $role,
            'statusdata'      => $statusData,
            'programdata'     => $programData ?? null,
            'last_api_update' => now(),
        ])->save();
        if ($person->user_id != null && $person->programdata != null) {
            CheckPersonsCourses::dispatch($person->id);
        }
    }
}
