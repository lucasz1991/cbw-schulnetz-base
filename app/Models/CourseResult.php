<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseResult extends Model
{
    use HasFactory;

    // Sync-States als Konstanten
    public const SYNC_STATE_DIRTY  = 'dirty';   // lokal geändert, noch nicht zu UVS gepusht
    public const SYNC_STATE_SYNCED = 'synced';  // mit UVS abgeglichen
    public const SYNC_STATE_REMOTE = 'remote';  // aus UVS importiert, lokal nicht verändert

    protected $fillable = [
        'course_id',
        'person_id',
        'result',
        'status',
        'updated_by',

        'remote_uid',
        'sync_state',
        'remote_upd_date',
    ];

    protected $casts = [
        'remote_uid'      => 'integer',
        'remote_upd_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Beziehungen
    |--------------------------------------------------------------------------
    */

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function person()
    {
        // deine Person-Tabelle heißt ziemlich sicher "person"
        // und das Model App\Models\Person
        return $this->belongsTo(Person::class, 'person_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    public function markDirty(): void
    {
        $this->sync_state = self::SYNC_STATE_DIRTY;
    }

    public function markSynced(int $remoteUid, ?\Carbon\Carbon $remoteUpdDate = null): void
    {
        $this->remote_uid      = $remoteUid;
        $this->remote_upd_date = $remoteUpdDate;
        $this->sync_state      = self::SYNC_STATE_SYNCED;
    }

    public function markRemote(int $remoteUid, ?\Carbon\Carbon $remoteUpdDate = null): void
    {
        $this->remote_uid      = $remoteUid;
        $this->remote_upd_date = $remoteUpdDate;
        $this->sync_state      = self::SYNC_STATE_REMOTE;
    }

    public function isDirtyForSync(): bool
    {
        return $this->sync_state === self::SYNC_STATE_DIRTY;
    }
}
