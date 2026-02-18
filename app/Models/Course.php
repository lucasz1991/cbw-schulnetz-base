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
use App\Models\File;
use App\Models\CourseDay;
use App\Models\CourseResult;
use App\Models\CourseRating;
use App\Jobs\ApiUpdates\CreateOrUpdateCourse;

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
            // Course days + their files
            $days = $course->days()->withTrashed()->get();
            foreach ($days as $day) {
                $day->files()->delete();
                $day->forceDelete();
            }

            // Enrollments (pivot) with soft deletes
            $enrollments = $course->enrollments()->withTrashed()->get();
            foreach ($enrollments as $enrollment) {
                $enrollment->forceDelete();
            }

            // Course results
            $course->results()->delete();

            // Course ratings
            $course->ratings()->delete();

            // Material acknowledgements + their files
            $acks = $course->materialAcknowledgements()->get();
            foreach ($acks as $ack) {
                $ack->files()->delete();
                $ack->delete();
            }

            // Files attached directly to course
            $course->files()->delete();

            // File pool + its files
            $pool = $course->filePool;
            if ($pool) {
                $pool->files()->delete();
                $pool->delete();
            }
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

        $cacheKey = "course-sync-cooldown:{$course->klassen_id}";
        $payload = [
            'last'   => now()->toDateTimeString(),
            'source' => $source,
        ];

        if (! Cache::add($cacheKey, $payload, now()->addMinutes(self::API_UPDATE_COOLDOWN_MINUTES))) {
            return;
        }

        CreateOrUpdateCourse::dispatch((string) $course->klassen_id);
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
                    ->orWhereNotNull('status');
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
