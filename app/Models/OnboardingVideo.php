<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OnboardingVideo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'category',
        'is_active',
        'is_required',
        'sort_order',
        'duration_seconds',
        'valid_from',
        'valid_until',
        'settings',
        'version',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
        'duration_seconds' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'settings' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Einheitlich über euer File-Model.
     * Erwartet: files.fileable_type + files.fileable_id
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    /**
     * Standard: genau 1 Videodatei pro OnboardingVideo.
     * Setze beim File-Datensatz z.B. type='onboarding_video'
     */
    public function videoFile()
    {
        return $this->morphOne(File::class, 'fileable')
            ->where('type', 'onboarding_video');
    }

    public function videoFileThumbnail()
    {
        return $this->morphOne(File::class, 'fileable')
            ->where('type', 'onboarding_video_thumbnail');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyValid($query)
    {
        return $query
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isValidNow(): bool
    {
        $now = now();

        if ($this->valid_from && $this->valid_from->gt($now)) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->lt($now)) {
            return false;
        }

        return true;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? $default;
    }
}
