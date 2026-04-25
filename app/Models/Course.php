<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Services\Helper\DateParser;
use App\Models\File;
use App\Models\CourseDay;
use App\Models\CourseResult;
use App\Models\CourseRating;
use App\Models\Person;
use App\Jobs\ApiUpdates\CreateOrUpdateCourse;
use App\Services\ApiUvs\CourseApiServices\CourseResultsLoadService;
use App\Services\ApiUvs\CourseApiServices\CourseResultsSyncService;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    public const API_UPDATE_COOLDOWN_MINUTES = 20;

    /**
     * Nur auf diesen Routen wird beim Laden eines Kurses ein API-Update angestoßen.
     */
    private const API_UPDATE_ROUTES = [
        'user.program.course.show',
        'dashboard',
        'tutor.courses.show',
        'tutor.courses',
    ];

    /**
     * Wenn du lieber alles freigeben willst:
     * protected $guarded = [];
     * Ich liste hier explizit die Felder aus deiner Migration.
     */
    protected $fillable = [
        // Externe Identität
        'klassen_id',
        'termin_id',

        // Kontext/Filter
        'institut_id',
        'vtz',
        'room',

        // Anzeige/Meta
        'title',
        'description',
        'educational_materials',

        // Grobe Plan-Daten
        'planned_start_date',
        'planned_end_date',

        // Sync/Offline
        'source_snapshot',
        'source_last_upd',
        'sync_status',

        'type',
        'settings',
        'is_active',

        // Komfort: primärer Tutor (Person, nicht User)
        'primary_tutor_person_id',
    ];

    protected $casts = [
        'planned_start_date'    => 'date',
        'planned_end_date'      => 'date',
        'source_last_upd'       => 'datetime',
        'is_active'             => 'boolean',
        'settings'              => 'array',
        'source_snapshot'       => 'array',
        'educational_materials' => 'array',
    ];

    /**
     * Für dein UI: dynamische Counter.
     * participants_count = Anzahl Personen vom Typ 'participant'
     * dates_count        = Anzahl CourseDays
     */
    protected $appends = ['participants_count', 'dates_count'];

    protected static function booted(): void
    {
        static::retrieved(function (Course $course) {
            if (app()->runningInConsole()) {
                return;
            }

            if (! $course->id || empty($course->klassen_id)) {
                return;
            }

            $routeName = request()?->route()?->getName();
            if (! $routeName || ! in_array($routeName, self::API_UPDATE_ROUTES, true)) {
                return;
            }

            static::dispatchApiUpdateIfNotThrottled($course, 'retrieved');
        });

        static::deleting(function (Course $course) {
            if ($course->isForceDeleting()) {
                $course->forceDeleteRelatedData();
                return;
            }

            $course->softDeleteRelatedData();
        });

        
    }

    /**
     * Zentraler Throttle für Course-API-Updates.
     */
    protected static function dispatchApiUpdateIfNotThrottled(Course $course, string $source): void
    {
        if (! $course->id || empty($course->klassen_id)) {
            return;
        }

        CreateOrUpdateCourse::dispatch((string) $course->klassen_id);
    }

    /**
     * UVS ist Master:
     * Laedt Pruefungsergebnisse fuer diesen Kurs hart aus UVS nach lokal.
     */
    public function loadResultsFromUvs(): bool
    {
        return app(CourseResultsSyncService::class)->loadFromRemote($this);
    }

    /**
     * Stellt das harte Nachladen der Pruefungsergebnisse in die Queue.
     */
    public function queueLoadResultsFromUvs(): void
    {
        app(CourseResultsLoadService::class)->queue($this);
    }

    public function softDeleteForMissingApi(): array
    {
        if ($this->trashed()) {
            return [
                'course_soft_deleted' => false,
                'days_soft_deleted' => 0,
                'enrollments_soft_deleted' => 0,
            ];
        }

        $summary = [
            'days_soft_deleted' => $this->days()->whereNull('deleted_at')->count(),
            'enrollments_soft_deleted' => $this->enrollments()->whereNull('deleted_at')->count(),
        ];

        $this->delete();

        return $summary + ['course_soft_deleted' => true];
    }

    public function restoreFromApiPayload(array $participantsData = [], array $daysData = []): array
    {
        $summary = [
            'course_restored' => false,
            'persons_restored' => 0,
            'enrollments_restored' => 0,
            'days_restored' => 0,
        ];

        if (! $this->exists) {
            return $summary;
        }

        if ($this->trashed()) {
            $this->restore();
            $summary['course_restored'] = true;
        }

        $summary['persons_restored'] = $this->restoreParticipantsFromApiPayload($participantsData);
        $summary['enrollments_restored'] = $this->restoreEnrollmentsFromApiPayload($participantsData);
        $summary['days_restored'] = $this->restoreDaysFromApiPayload($daysData);

        return $summary;
    }

    public function softDeleteRelatedData(): array
    {
        $days = $this->days()
            ->whereNull('deleted_at')
            ->get();

        foreach ($days as $day) {
            $day->delete();
        }

        $enrollments = $this->enrollments()
            ->whereNull('deleted_at')
            ->get();

        foreach ($enrollments as $enrollment) {
            $enrollment->delete();
        }

        return [
            'days_soft_deleted' => $days->count(),
            'enrollments_soft_deleted' => $enrollments->count(),
        ];
    }

    public function forceDeleteRelatedData(): void
    {
        $days = $this->days()->withTrashed()->get();
        foreach ($days as $day) {
            $day->files()->delete();
            $day->forceDelete();
        }

        $enrollments = $this->enrollments()->withTrashed()->get();
        foreach ($enrollments as $enrollment) {
            $enrollment->forceDelete();
        }

        $this->results()->delete();
        $this->ratings()->delete();

        $acks = $this->materialAcknowledgements()->get();
        foreach ($acks as $ack) {
            $ack->files()->delete();
            $ack->delete();
        }

        $this->files()->delete();

        $pool = $this->filePool;
        if ($pool) {
            $pool->files()->delete();
            $pool->delete();
        }
    }

    protected function restoreParticipantsFromApiPayload(array $participantsData): int
    {
        $uvsPersonIds = collect($participantsData)
            ->pluck('person_id')
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();

        if ($uvsPersonIds->isEmpty()) {
            return 0;
        }

        $persons = Person::withTrashed()
            ->whereIn('person_id', $uvsPersonIds->all())
            ->get();

        $restored = 0;

        foreach ($persons as $person) {
            if (! $person->trashed()) {
                continue;
            }

            $person->restore();
            $restored++;
        }

        return $restored;
    }

    protected function restoreEnrollmentsFromApiPayload(array $participantsData): int
    {
        $uvsPersonIds = collect($participantsData)
            ->pluck('person_id')
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();

        if ($uvsPersonIds->isEmpty()) {
            return 0;
        }

        $personIds = Person::withTrashed()
            ->whereIn('person_id', $uvsPersonIds->all())
            ->pluck('id');

        if ($personIds->isEmpty()) {
            return 0;
        }

        $enrollments = $this->enrollments()
            ->withTrashed()
            ->whereIn('person_id', $personIds->all())
            ->get();

        $restored = 0;

        foreach ($enrollments as $enrollment) {
            if (! $enrollment->trashed()) {
                continue;
            }

            $enrollment->restore();
            $restored++;
        }

        return $restored;
    }

    protected function restoreDaysFromApiPayload(array $daysData): int
    {
        $dates = collect($daysData)
            ->pluck('datum')
            ->map(fn ($value) => DateParser::date($value))
            ->filter()
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $days = $this->days()
            ->withTrashed()
            ->whereIn('date', $dates->all())
            ->get();

        $restored = 0;

        foreach ($days as $day) {
            if (! $day->trashed()) {
                continue;
            }

            $day->restore();
            $restored++;
        }

        return $restored;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    | $course->course_short_name 
    |--------------------------------------------------------------------------
    */
    public function getCourseShortNameAttribute(): string
    {
        return data_get($this->source_snapshot, 'course.kurzbez', '');
    }

    public function getCourseClassNameAttribute(): string
    {
        return data_get($this->source_snapshot, 'course.klassen_co_ks', '');
    }


    public function getParticipantsCountAttribute(): int
    {
        // zählt nur Teilnehmer (people.type = 'participant')
        return $this->participants()->count();
    }

    public function getDatesCountAttribute(): int
    {
        return $this->days()->count();
    }

    public function getMaterialsAttribute(): array
    {
        $raw = $this->educational_materials;
        return is_array($raw) ? $raw : [];
    }
    
    /**
     * Alle Material-Bestätigungen zu diesem Kurs
     */
    public function materialAcknowledgements()
    {
        return $this->hasMany(\App\Models\CourseMaterialAcknowledgement::class);
    }

    /**
     * Prüft, ob eine bestimmte Person (Teilnehmer) die Bereitstellung bestätigt hat.
     */
    public function isMaterialsAcknowledgedBy(int $personId): bool
    {
        return $this->materialAcknowledgements()
                    ->where('person_id', $personId)
                    ->whereNotNull('acknowledged_at')
                    ->exists();
    }


    public function getZeitraumFmtAttribute(): ?string
    {
        $s = $this->planned_start_date ? Carbon::parse($this->planned_start_date) : null;
        $e = $this->planned_end_date   ? Carbon::parse($this->planned_end_date)   : null;
        return ($s && $e) ? $s->locale('de')->isoFormat('ll').' – '.$e->locale('de')->isoFormat('ll') : null;
    }

    public function getStatusLabelAttribute(): string
    {
        $s = $this->planned_start_date ? Carbon::parse($this->planned_start_date) : null;
        $e = $this->planned_end_date   ? Carbon::parse($this->planned_end_date)   : null;
        $now = Carbon::now('Europe/Berlin');
        if ($s && $now->lt($s)) return 'Geplant';
        if ($s && $e && $now->between($s, $e)) return 'Laufend';
        if ($e && $now->gt($e)) return 'Abgeschlossen';
        return 'Offen';
    }



    public function scopeTaughtBy(Builder $q, int $personId): Builder
    {
        return $q->whereHas('tutors', fn($qq) => $qq->where('people.id', $personId));
    }

    public function scopeActiveAt(Builder $q, Carbon $at): Builder
    {
        return $q->where('planned_start_date', '<=', $at)
                ->where('planned_end_date', '>=', $at);
    }

    public function scopeCompletedBefore(Builder $q, Carbon $at): Builder
    {
        return $q->where('planned_end_date', '<', $at);
    }

    public function scopeUpcomingAfter(Builder $q, Carbon $at): Builder
    {
        return $q->where('planned_start_date', '>', $at);
    }


    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, $value): void
    {
        $s = $this->settings ?? [];
        $s[$key] = $value;
        $this->settings = $s;
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic
    |--------------------------------------------------------------------------
    */
    public function hasRoterFaden(): bool
    {
        return $this->files()
            ->where('type', 'roter_faden')
            ->exists();
    }

    public function hasResultsForAllParticipants(): bool
    {
        $participantIds = $this->participants()
            ->pluck('persons.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($participantIds)) {
            return false;
        }

        $withResults = CourseResult::query()
            ->where('course_id', $this->id)
            ->whereIn('person_id', $participantIds)
            ->where(function ($q) {
                $q->whereNotNull('result')
                    ->orWhere(function ($qq) {
                        $qq->whereNotNull('status')
                           ->where('status', '<>', '');
                    });
            })
            ->pluck('person_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return empty(array_diff($participantIds, $withResults));
    }

    public function hasParticipantDocumentationSignature(): bool
    {
        return $this->files()
            ->where('type', 'sign_course_doku_participant')
            ->exists();
    }

    public function areAllCourseDaysDocumentationCompleted(): bool
    {
        $totalDays = $this->days()->count();
        if ($totalDays === 0) {
            return false;
        }

        $completedDays = $this->days()
            ->where('note_status', CourseDay::NOTE_STATUS_COMPLETED)
            ->count();

        return $completedDays === $totalDays;
    }

    public function hasDocumentationWithParticipantSignature(): bool
    {
        return $this->areAllCourseDaysDocumentationCompleted()
            && $this->hasParticipantDocumentationSignature();
    }

    public function hasAttendanceForAllCourseDays(): bool
    {
        $days = $this->days()->get();
        if ($days->isEmpty()) {
            return false;
        }

        foreach ($days as $day) {
            if (! $day->isAttendanceCompletelyRecorded()) {
                return false;
            }
        }

        return true;
    }

    public function hasResultsForAllParticipantsOrExternalExam(): bool
    {
        if ((bool) $this->getSetting('isExternalExam', false)) {
            return true;
        }

        return $this->hasResultsForAllParticipants();
    }

    public function isReadyForInvoice(): bool
    {
        return $this->hasRoterFaden()
            && $this->hasDocumentationWithParticipantSignature()
            && $this->hasAttendanceForAllCourseDays()
            && $this->hasResultsForAllParticipantsOrExternalExam();
    } 

    /*
    |--------------------------------------------------------------------------
    | Beziehungen
    |--------------------------------------------------------------------------
    */

    // Primärer Tutor (Komfort, optional)
    public function tutor()
    {
        return $this->belongsTo(Person::class, 'primary_tutor_person_id');
    }

    // Unterrichtstage (CourseDay)
    public function days()
    {
        return $this->hasMany(CourseDay::class);
    }

    // Alias, falls du im Code schon "dates()" benutzt hast:
    public function dates()
    {
        return $this->days();
    }


    public function enrollments()
    {
        return $this->hasMany(CourseParticipantEnrollment::class);
    }

    /**
     * Teilnehmer als Personen (nur aktive, nicht gelöschte Pivot-Reihen)
     */
    public function participants()
    {
        return $this->belongsToMany(Person::class, 'course_participant_enrollments', 'course_id', 'person_id')
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


    // Falls du Kursbewertungen behalten willst
    public function ratings()
    {
        return $this->hasMany(CourseRating::class);
    }

    public function results()
    {
        return $this->hasMany(CourseResult::class);
    }

    // FilePool (morphable) – lässt du wie gehabt
    public function filePool()
    {
        return $this->morphOne(FilePool::class, 'filepoolable');
    }


    public function files(): MorphMany
    {
        return $this->morphMany(\App\Models\File::class, 'fileable');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (praktisch fürs Admin-UI)
    |--------------------------------------------------------------------------
    */
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByKlassenId($q, string $klassenId)
    {
        return $q->where('klassen_id', $klassenId);
    }

    public function scopeOfInstitut($q, int $institutId)
    {
        return $q->where('institut_id', $institutId);
    }

    public function scopeWithCounts($q)
    {
        return $q
            ->withCount(['days as dates_count'])
            ->withCount([
                'participants as participants_count' => function ($sub) {
                    $sub->where('people.type', 'participant');
                }
            ]);
    }
}
