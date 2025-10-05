<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Facades\Log;


class Person extends Model
{
    use HasFactory;

    protected $table = 'persons';

    protected $fillable = [
        'user_id',
        'person_id',
        'institut_id',
        'person_nr',
        'status',
        'upd_date',
        'nachname',
        'vorname',
        'geschlecht',
        'titel_kennz',
        'nationalitaet',
        'familien_stand',
        'geburt_datum',
        'geburt_name',
        'geburt_land',
        'geburt_ort',
        'lkz',
        'plz',
        'ort',
        'strasse',
        'adresszusatz1',
        'adresszusatz2',
        'plz_pf',
        'postfach',
        'plz_gk',
        'telefon1',
        'telefon2',
        'person_kz',
        'plz_alt',
        'ort_alt',
        'strasse_alt',
        'telefax',
        'kunden_nr',
        'stamm_nr_aa',
        'stamm_nr_bfd',
        'stamm_nr_sons',
        'stamm_nr_kst',
        'kostentraeger',
        'bkz',
        'email_priv',
        'email_cbw',
        'geb_mmtt',
        'org_zeichen',
        'personal_nr',
        'kred_nr',
        'angestellt_von',
        'angestellt_bis',
        'leer',
        'programdata',
        'statusdata',
        'last_api_update',
    ];

    protected $casts = [
        'upd_date' => 'datetime',
        'geburt_datum' => 'date',
        'angestellt_von' => 'datetime',
        'angestellt_bis' => 'datetime',
        'programdata' => 'array',
        'statusdata' => 'array',
        'last_api_update' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($person) {
            $person->apiupdate();
        });
    }

    public function apiupdate()
    {
        $apiService = app(ApiUvsService::class);
        $personStatus = $apiService->getPersonStatus($this->person_id) ?? null;
        if (! $personStatus) {
            Log::warning("Failed to fetch person status for person_id {$this->person_id }.");
        }
        if (! array_key_exists('data', $personStatus) || ! array_key_exists('data', $personStatus['data'])) {
            Log::warning("Invalid person status structure for person_id {$this->person_id }.");
        }
        // Bestimme Rolle basierend auf personStatus
        $this->statusData = $personStatus['data']['data'] ?? null;

        if($this->user && $this->user->role === 'guest') {
            $apiResponse = app(ApiUvsService::class)->getParticipantAndQualiprogrambyId($this->person_id);
            if ($apiResponse['ok']) {
                $data = $apiResponse['data'] ? $apiResponse['data'] : null;
                $quali_data = !empty($data['quali_data']) ? $data['quali_data'] : null;
            } else {
                $quali_data = null;
            }
            if ($quali_data) {
                $this->programdata = $quali_data;
            } else {
                Log::warning("No Qualiprogram data found for person_id {$this->person_id }. API response: " . json_encode($apiResponse));
            }
        }else{
            $apiResponse = app(ApiUvsService::class)->getTutorProgramDataByPersonId($this->person_id);
            if ($apiResponse['ok']) {
                $data = $apiResponse['data'] ? $apiResponse['data'] : null;
                $program_data = !empty($data['tutor']) ? $data['tutor'] : null;
            } else {
                $program_data = null;
            }
            if ($program_data) {
                $this->programdata = $program_data;
            } else {
                Log::warning("No Tutor program data found for person_id {$this->person_id }. API response: " . json_encode($apiResponse));
            }
        }
        $this->last_api_update = now();
        $this->save();

        if (empty($this->person_id)) {
            Log::warning("Cannot update Person API data: person_id is empty for Person ID {$this->id}");
            return;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
