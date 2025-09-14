<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Jobs\DeleteTempFile;

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
        $path = $this->getEphemeralPublicUrl(3) ?? '';

        return match (true) {
            str_starts_with($mime, 'image/') => '/'.$path,
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
     * Path des Files im Storage zu temporären datei zum anzeigen im Browser
     */

    public function getEphemeralPublicUrl(int $minutes = 10): string
    {
        $sourceDisk = $this->disk ?? config('filesystems.default');
        $publicDisk = 'public';

        // Ziel: /temp/<uuid>-<basename>
        $tmpName = Str::uuid()->toString() . '-' . basename($this->path);
        $tmpPath = 'temp/' . $tmpName;

        // Stream-basiert kopieren (funktioniert zwischen Disks)
        $read = Storage::disk('public')->readStream($this->path);
        if (! $read) {
            throw new \RuntimeException('Quelle nicht lesbar: ' . $this->path);
        }
        Storage::disk('public')->writeStream($tmpPath, $read);
        if (is_resource($read)) { fclose($read); }

        // Auto-Delete Job planen
        DeleteTempFile::dispatch($publicDisk, $tmpPath)
            ->delay(now()->addMinutes($minutes));

        // Öffentliche URL (direkt nutzbar in <img>, <iframe>, …)
        return $tmpPath;
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
