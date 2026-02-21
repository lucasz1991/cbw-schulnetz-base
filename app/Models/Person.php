<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Facades\Log;
use App\Jobs\ApiUpdates\PersonApiUpdate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'persons';

    public const API_UPDATE_COOLDOWN_MINUTES = 30;

    protected $fillable = [
        'user_id',
        'person_id',
        'institut_id',
        'person_nr',
        'teilnehmer_nr',
        'teilnehmer_id',
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
        'upd_date'        => 'datetime',
        'geburt_datum'    => 'date',
        'angestellt_von'  => 'datetime',
        'angestellt_bis'  => 'datetime',
        'programdata'     => 'array',
        'statusdata'      => 'array',
        'last_api_update' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function (Person $person) {
            // nur wenn mit User verknüpft, aktuell kein Auto-Update
            if (empty($person->user_id)) {
                return;
            }
            // Falls du bei neuen Personen sofort syncen willst:
            // static::dispatchApiUpdateIfNotThrottled($person, 'created');
        });

        static::updated(function (Person $person) {
            // nur wenn mit User verknüpft
            if (empty($person->user_id)) {
                return;
            }

            // Throttle über Cache (25 Minuten)
            static::dispatchApiUpdateIfNotThrottled($person, 'updated');
        });

        static::retrieved(function (Person $person) {
            // nur sinnvoll, wenn mit User verknüpft
            if (empty($person->user_id) || !empty($person->programdata)) {
                return;
            }

            static::dispatchApiUpdateIfNotThrottled($person, 'retrieved');
        });
    }

    /**
     * Zentraler Throttle für API-Updates:
     * - 25 Minuten Cooldown per Cache
     * - nur, wenn user_id vorhanden
     * - keine Schleifen in Jobs/CLI
     */
    protected static function dispatchApiUpdateIfNotThrottled(Person $person, string $source): void
    {
        if (empty($person->user_id) || empty($person->id)) {
            return;
        }

        $cacheKey = "person_apiupdate_cooldown:{$person->id}";

        // add() legt den Key nur an, wenn er noch nicht existiert
        $payload = [
            'last'   => now()->toDateTimeString(),
            'source' => $source,
        ];

        // Wenn Key bereits existiert -> wir sind im Cooldown -> nichts tun
        if (! Cache::add($cacheKey, $payload, now()->addMinutes(self::API_UPDATE_COOLDOWN_MINUTES))) {
            return;
        }

        // Außerhalb des Cooldowns: Job dispatchen
        PersonApiUpdate::dispatch($person->id);
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

    /** Kurse, in denen der User (über alle zugeordneten Personen) Tutor ist */
    public function taughtCourses()
    {
        $personIds = $this->user_id
            ? static::where('user_id', $this->user_id)->pluck('id')
            : collect([$this->id]);

        return Course::whereIn('primary_tutor_person_id', $personIds);
    }

    /**
     * Kritische Felder, deren Änderung geloggt werden muss.
     */
    protected static array $criticalIdentityFields = [
        'person_id',
        'institut_id',
        'person_nr',
        'email_priv',
        'role',
        'status',
    ];

    /**
     * Determines whether the person's program is an education (Weiterbildung)
     * based on the number of tn_baust entries in programdata.
     *
     * Rule:
     * - more than 20 tn_baust => education (true)
     * - otherwise => retraining (false)
     */
    public function isEducation(): bool
    {
        $programData = $this->programdata ?? [];

        if (!isset($programData['tn_baust']) || !is_array($programData['tn_baust'])) {
            return false;
        }

        return count($programData['tn_baust']) >= 20;
    }


    /** 
     * Instanzbasierte Mapping-Funktion.
     */
    public function mapFromUvsPayloadWithCheck(object $p, string $role): array
    {
        $mapped = static::mapFromUvsPayload($p, $role);

        if (!$this->exists) {
            return $mapped;
        }

        $changes = [];

        foreach (static::$criticalIdentityFields as $field) {
            $old = $this->{$field} ?? null;
            $new = $mapped[$field] ?? null;

            if ($old !== $new) {
                $changes[$field] = [
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        if (!empty($changes)) {
            \Log::warning('Person: Kritische Identitätsdaten haben sich geändert (UVS Sync).', [
                'person_pk' => $this->id,
                'person_id' => $this->person_id,
                'changes'   => $changes,
            ]);
        }
        return $mapped;
    }

    public static function mapFromUvsPayload(object $p, string $role): array
    {
        return [
            'person_id'        => $p->person_id,
            'institut_id'      => $p->institut_id ?? null,
            'person_nr'        => $p->person_nr ?? null,
            'role'             => $role,
            'status'           => $p->status ?? null,
            'upd_date'         => static::safeDate($p->upd_date ?? null, 'datetime'),
            'nachname'         => $p->nachname ?? null,
            'vorname'          => $p->vorname ?? null,
            'geschlecht'       => $p->geschlecht ?? null,
            'titel_kennz'      => $p->titel_kennz ?? null,
            'nationalitaet'    => $p->nationalitaet ?? null,
            'familien_stand'   => $p->familien_stand ?? null,
            'geburt_datum'     => static::safeDate($p->geburt_datum ?? null, 'date'),
            'geburt_name'      => $p->geburt_name ?? null,
            'geburt_land'      => $p->geburt_land ?? null,
            'geburt_ort'       => $p->geburt_ort ?? null,
            'lkz'              => $p->lkz ?? null,
            'plz'              => $p->plz ?? null,
            'ort'              => $p->ort ?? null,
            'strasse'          => $p->strasse ?? null,
            'adresszusatz1'    => $p->adresszusatz1 ?? null,
            'adresszusatz2'    => $p->adresszusatz2 ?? null,
            'plz_pf'           => $p->plz_pf ?? null,
            'postfach'         => $p->postfach ?? null,
            'plz_gk'           => $p->plz_gk ?? null,
            'telefon1'         => $p->telefon1 ?? null,
            'telefon2'         => $p->telefon2 ?? null,
            'person_kz'        => $p->person_kz ?? null,
            'plz_alt'          => $p->plz_alt ?? null,
            'ort_alt'          => $p->ort_alt ?? null,
            'strasse_alt'      => $p->strasse_alt ?? null,
            'telefax'          => $p->telefax ?? null,
            'kunden_nr'        => $p->kunden_nr ?? null,
            'stamm_nr_aa'      => $p->stamm_nr_aa ?? null,
            'stamm_nr_bfd'     => $p->stamm_nr_bfd ?? null,
            'stamm_nr_sons'    => $p->stamm_nr_sons ?? null,
            'stamm_nr_kst'     => $p->stamm_nr_kst ?? null,
            'kostentraeger'    => $p->kostentraeger ?? null,
            'bkz'              => $p->bkz ?? null,
            'email_priv'       => $p->email_priv ?? null,
            'email_cbw'        => $p->email_cbw ?? null,
            'geb_mmtt'         => $p->geb_mmtt ?? null,
            'org_zeichen'      => $p->org_zeichen ?? null,
            'personal_nr'      => $p->personal_nr ?? null,
            'kred_nr'          => $p->kred_nr ?? null,
            'angestellt_von'   => static::safeDate($p->angestellt_von ?? null, 'datetime'),
            'angestellt_bis'   => static::safeDate($p->angestellt_bis ?? null, 'datetime'),
            'leer'             => $p->leer ?? null,
            'last_api_update'  => now(),
        ];
    }

    protected static function safeDate(?string $value, string $mode = 'date'): ?string
    {
        try {
            if (!$value) {
                return null;
            }

            return $mode === 'datetime'
                ? Carbon::parse($value)->toDateTimeString()
                : Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
