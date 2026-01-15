<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingVideoView extends Model
{
    use HasFactory;

    protected $fillable = [
        'onboarding_video_id',
        'user_id',
        'progress_seconds',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'progress_seconds' => 'integer',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function video(): BelongsTo
    {
        return $this->belongsTo(OnboardingVideo::class, 'onboarding_video_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function markCompleted(): void
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    public function updateProgress(int $seconds): void
    {
        if ($seconds > $this->progress_seconds) {
            $this->update([
                'progress_seconds' => $seconds,
            ]);
        }
    }

    public function completionPercentage(?int $durationSeconds): ?int
    {
        if (!$durationSeconds || $durationSeconds <= 0) {
            return null;
        }

        return min(100, (int) round(($this->progress_seconds / $durationSeconds) * 100));
    }
}
