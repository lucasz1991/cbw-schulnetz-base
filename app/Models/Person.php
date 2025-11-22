<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Facades\Log;
use App\Jobs\ApiUpdates\PersonApiUpdate;
use Illuminate\Support\Carbon;



class Person extends Model
{
    use HasFactory;

    protected $table = 'persons';

    protected $fillable = [
        'user_id',
        'person_id',
        'institut_id',
        'person_nr',
        'role',
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

        static::created(function (Person $person) {
            // nur wenn mit User verknüpft
            if (empty($person->user_id)) {
                return;
            }

            // Referenzzeit: bevorzugt updated_at, sonst created_at
            $ref = $person->updated_at ?? $person->created_at ?? now();

            $thresholdSec = 10 * 60;
            $ageSec = now()->diffInSeconds($ref);

            if ($ageSec >= $thresholdSec) {
                // älter als 10 Min -> sofort
                $person->apiupdate();
            }
        });
        static::updated(function (Person $person) {
            // nur wenn mit User verknüpft
            if (empty($person->user_id)) {
                return;
            }

            // Referenzzeit: bevorzugt updated_at, sonst created_at
            $ref = $person->updated_at ?? $person->created_at ?? now();

            $thresholdSec = 10 * 60;
            $ageSec = now()->diffInSeconds($ref);

            if ($ageSec >= $thresholdSec) {
                // älter als 10 Min -> sofort
                $person->apiupdate();
            }
        });
        static::retrieved(function (Person $person) {
        // nur sinnvoll, wenn mit User verknüpft
        if (empty($person->user_id)) {
            return;
        }

        // Wenn noch nie via API aktualisiert wurde, nimm updated_at/created_at als Fallback
        $last = $person->last_api_update
            ?? $person->updated_at
            ?? $person->created_at;

        if (!$last instanceof \Illuminate\Support\Carbon) {
            return;
        }

        // Schwelle: z.B. 60 Minuten seit letztem API-Update
        $thresholdMinutes = 60;

        if ($last->lt(now()->subMinutes($thresholdMinutes))) {
            // Debug optional
            // Log::info('Person: last_api_update älter als Threshold, apiupdate()', [
            //     'person_id' => $person->person_id,
            //     'id'        => $person->id,
            //     'last_api_update' => $person->last_api_update,
            // ]);

            $person->apiupdate();
        }
    });
    }

    public function apiupdate()
    {
        PersonApiUpdate::dispatch($this->id);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function enrollments()
    {
        return $this->hasMany(CourseParticipantEnrollment::class);
    }

    public function tutorCourses()
    {
        return $this->hasMany(Course::class, 'primary_tutor_person_id');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_participant_enrollments', 'person_id', 'course_id')
            ->withPivot([
                'id','teilnehmer_id','tn_baustein_id','baustein_id',
                'klassen_id','termin_id','vtz','kurzbez_ba',
                'status','is_active','results','notes',
                'source_snapshot','source_last_upd','last_synced_at','deleted_at'
            ])
            ->as('enrollment')
            ->wherePivotNull('deleted_at')
            ->wherePivot('is_active', true);
    }

    /** Kurse, in denen die Person primärer Tutor ist */
    public function taughtCourses()
    {
        return $this->hasMany(Course::class, 'primary_tutor_person_id');
    }

}
