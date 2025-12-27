<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Fluent;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Jobs\ApiUpdates\SyncCourseDayAttendanceJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\File;
use Illuminate\Support\Facades\Route;


class CourseDay extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'date',
        'start_time',
        'end_time',
        'std',
        'day_sessions',
        'attendance_data',
        'attendance_updated_at',
        'attendance_last_synced_at',
        'topic',
        'notes',
        'note_status',
        'settings',
        'type',

    ];

    protected $casts = [
        'date'            => 'date',
        'start_time'      => 'datetime:H:i',
        'end_time'        => 'datetime:H:i',
        'day_sessions'    => 'array', // wichtig für JSON
        'attendance_data' => 'array', // wichtig für JSON
        'attendance_updated_at' => 'datetime',
        'attendance_last_synced_at' => 'datetime',
        'note_status'     => 'integer',
        'settings'       => 'array',
    ];


    public const NOTE_STATUS_MISSING   = 0;
    public const NOTE_STATUS_DRAFT     = 1;
    public const NOTE_STATUS_COMPLETED = 2;


    public const AUTO_SYNC_THRESHOLD_MINUTES = 15;

    protected static function booted(): void
    {
        static::creating(function (CourseDay $day) {
            if (empty($day->day_sessions)) {
                $day->day_sessions = self::makeDefaultSessions($day);
            }

            if (empty($day->attendance_data)) {
                $day->attendance_data = self::makeDefaultAttendance($day);
            }

            if ($day->note_status === null) {
                $day->note_status = self::NOTE_STATUS_MISSING;
            }

            if ($day->settings === null) {
                $day->settings = [];
            }
        });


    }



    public static function makeDefaultSessions(self $day): array
    {
        return [];
    }

    public static function makeDefaultAttendance(self $day): array
    {
        return [
            // Wichtig: leer lassen, damit "kein Eintrag" = Default "anwesend" im UI bedeuten kann
            'participants' => [],
            'status' => [
                'start'      => 0,
                'end'        => 0,
                'state' => null,
                'created_at' => null,
                'updated_at' => null,
            ],
        ];
    }


    /**
     * Attendance für einen Teilnehmer gemäß Default-Struktur updaten.
     *
     * Erwartete Keys in $data (alle optional):
     * - present (bool)
     * - late_minutes (int)
     * - left_early_minutes (int)
     * - excused (bool)
     * - note (string|null)
     * - timestamps => ['in' => datetime|null, 'out' => datetime|null]
     */
public function setAttendance(int $participantId, array $data): void
{
    $att = $this->attendance_data ?? [];

    if (!isset($att['participants']) || !is_array($att['participants'])) {
        $att['participants'] = [];
    }

    $defaultRow = [
        'present'            => true, 
        'excused'            => false,
        'late_minutes'       => 0,
        'left_early_minutes' => 0,
        'note'               => '',
        'timestamps'         => ['in' => null, 'out' => null],
        'arrived_at'         => null,
        'left_at'            => null,
        'src_api_id'         => null,
        'state'              => null,
        'created_at'         => null,
        'updated_at'         => null,
    ];

    $row = $att['participants'][$participantId] ?? $defaultRow;

    if (isset($data['timestamps']) && is_array($data['timestamps'])) {
        $row['timestamps'] = array_merge(
            $row['timestamps'] ?? ['in' => null, 'out' => null],
            $data['timestamps']
        );
        unset($data['timestamps']);
    }

    $row = array_merge($row, $data);

    if (!array_key_exists('state', $data)) {
        // wenn irgendwas geändert wird -> dirty
        $row['state'] = $row['state'] ?: 'dirty';
    }

    $now = now()->toDateTimeString();
    if (empty($row['created_at'])) $row['created_at'] = $now;
    $row['updated_at'] = $now;

    $att['participants'][$participantId] = $row;

    $this->attendance_data = $att;
    $this->attendance_updated_at = now();

    $this->save();
}



    public function getSessions()
    {
        return collect($this->day_sessions ?? [])
            ->map(function ($session, $key) {
                return new Fluent(array_merge(['id' => $key], $session));
            });

    }

    /** Notes lesen für eine Session-ID */
    public function getSessionNotes(string|int $sessionId): ?string
    {
        return data_get($this->day_sessions, [(string)$sessionId, 'notes']);
    }

    /** Notes setzen + in day_sessions JSON zurückschreiben */
    public function setSessionNotes(string|int $sessionId, ?string $notes): void
    {
        $data = $this->day_sessions ?? [];
        $sid = (string)$sessionId;
        $data[$sid] = array_merge([
            'label' => null, 'start' => null, 'end' => null, 'break' => null,
            'room' => null, 'topic' => null, 'notes' => null,
        ], $data[$sid] ?? []);
        $data[$sid]['notes'] = $notes;
        $this->day_sessions = $data; // <- wichtig: Feld am Model setzen
        $this->save(); // Speichern, damit Änderungen persistiert werden
    }

    /** Topic lesen für eine Session-ID */
    public function getSessionTopic(string|int $sessionId): ?string
    {
        return data_get($this->day_sessions, [(string)$sessionId, 'topic']);
    }

    public function setSessionTopic(string|int $sessionId, ?string $topic): void
    {
        $data = $this->day_sessions ?? [];
        $sid = (string)$sessionId;
        $data[$sid] = array_merge([
            'label' => null, 'start' => null, 'end' => null, 'break' => null,
            'room' => null, 'topic' => null, 'notes' => null,
        ], $data[$sid] ?? []);
        $data[$sid]['topic'] = $topic;
        $this->day_sessions = $data; // <- wichtig: Feld am Model setzen
        $this->save(); // Speichern, damit Änderungen persistiert werden
    }



    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }

    /** Tutor-Signaturen für diesen Tag (Typ z. B. sign_courseday_tutor) */
    public function tutorSignatures()
    {
        return $this->files()->where('type', 'sign_courseday_doku_tutor');
    }

    public function latestTutorSignature(): ?File
    {
        return $this->tutorSignatures()->latest()->first();
    }

    public function getNoteStatusLabelAttribute(): string
    {
        return match ($this->note_status) {
            self::NOTE_STATUS_DRAFT     => 'Entwurf',
            self::NOTE_STATUS_COMPLETED => 'Fertig & unterschrieben',
            default                     => 'Fehlend',
        };
    }
}
