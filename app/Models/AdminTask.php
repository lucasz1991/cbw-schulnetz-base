<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminTask extends Model
{
    use HasFactory;

    public const TYPE_REPORTBOOK_REVIEW = 'reportbook_review';

    // Status-Konstanten
    public const STATUS_OPEN        = 0;
    public const STATUS_IN_PROGRESS = 1;
    public const STATUS_COMPLETED   = 2;

    // Priority-Konstanten
    public const PRIORITY_HIGH   = 1;
    public const PRIORITY_NORMAL = 2;
    public const PRIORITY_LOW    = 3;

    protected $fillable = [
        'created_by',
        'context_type',
        'context_id',
        'task_type',
        'description',
        'status',
        'priority',
        'due_at',
        'assigned_to',
        'completed_at',
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];

    /*
     |--------------------------------------------------------------------------
     | Beziehungen
     |--------------------------------------------------------------------------
     */

    /** Ersteller der Aufgabe */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Zugewiesener Admin */
    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Generischer Kontext (User, Course, UserRequest, ...) */
    public function context()
    {
        return $this->morphTo();
    }

    /*
     |--------------------------------------------------------------------------
     | Accessors
     |--------------------------------------------------------------------------
     */

    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN        => 'Offen',
            self::STATUS_IN_PROGRESS => 'In Bearbeitung',
            self::STATUS_COMPLETED   => 'Erledigt',
            default                  => 'Unbekannt',
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN        => '⚠️',
            self::STATUS_IN_PROGRESS => '⏳',
            self::STATUS_COMPLETED   => '✅',
            default                  => '❓',
        };
    }

    public function getPriorityTextAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_HIGH   => 'Hoch',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_LOW    => 'Niedrig',
            default               => 'Unbekannt',
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at
            && $this->status !== self::STATUS_COMPLETED
            && now()->gt($this->due_at);
    }

    /*
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
     */

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForAdmin($query, int $adminId)
    {
        return $query->where('assigned_to', $adminId);
    }

    public function scopeWithStatus($query, ?int $status)
    {
        if ($status === null) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeWithPriority($query, ?int $priority)
    {
        if ($priority === null) {
            return $query;
        }

        return $query->where('priority', $priority);
    }

    /*
     |--------------------------------------------------------------------------
     | Business-Methoden (ohne Service-Klasse)
     |--------------------------------------------------------------------------
     */

    /** Eine offene, unzugewiesene Aufgabe übernehmen. */
    public function assignTo(int $userId): bool
    {
        if ((int) $this->status === self::STATUS_COMPLETED) {
            return false;
        }

        if ($this->assigned_to !== null && (int) $this->assigned_to !== $userId) {
            return false;
        }

        $this->assigned_to = $userId;
        $this->status      = self::STATUS_IN_PROGRESS;
        $this->completed_at = null;

        return $this->save();
    }

    /** Eine aktive Berichtsheft-Prüfung an einen anderen berechtigten Bearbeiter übertragen. */
    public function takeOverBy(int $userId): bool
    {
        if (
            $this->task_type !== self::TYPE_REPORTBOOK_REVIEW
            || (int) $this->status !== self::STATUS_IN_PROGRESS
            || $this->assigned_to === null
            || (int) $this->assigned_to === $userId
        ) {
            return false;
        }

        $this->assigned_to = $userId;
        $this->completed_at = null;

        return $this->save();
    }

    /** Aufgabe als erledigt markieren */
    public function complete(): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }
        $this->status       = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }
}
