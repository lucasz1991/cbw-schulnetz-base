<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    protected $fillable = [
        'filepool_id',
        'user_id',
        'name',
        'path',
        'mime_type',
        'size',
        'expires_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function getIconOrThumbnailAttribute(): string
    {
        $mime = $this->mime_type ?? '';
        $path = $this->path ?? '';

        return match (true) {
            str_starts_with($mime, 'image/') => Storage::url($path),
            str_starts_with($mime, 'video/') => asset('site-images/fileicons/file-video.png'),
            str_starts_with($mime, 'audio/') => asset('site-images/fileicons/file-audio.png'),
            str_contains($mime, 'pdf')       => asset('site-images/fileicons/file-pdf.png'),
            str_contains($mime, 'zip')       => asset('site-images/fileicons/file-zip.png'),
            str_contains($mime, 'excel')     => asset('site-images/fileicons/file-excel.png'),
            str_contains($mime, 'word')      => asset('site-images/fileicons/file-word.png'),
            str_contains($mime, 'text')      => asset('site-images/fileicons/file-text.png'),
            default                          => asset('site-images/fileicons/file-default.png'),
        };
    }


    /**
     * Morphable Beziehung – z. B. zu User, Course, Task, etc.
     */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
