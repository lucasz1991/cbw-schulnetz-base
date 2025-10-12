<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Jobs\DeleteTempFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;



class File extends Model
{
    protected $fillable = [
        'filepool_id',
        'user_id',
        'name',
        'path',
        'mime_type',
        'type',
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

        if (str_starts_with($mime, 'image/')) {
            // Hier kommt jetzt bereits eine fertige URL raus
            return $this->getEphemeralPublicUrl(10);
        }

        return match (true) {
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
        $publicDisk = 'public';
        $sourceDisk = 'private'; // falls du pro Datei 'disk' speicherst; sonst 'private'
        $cacheKey   = "file:{$this->getKey()}:temp_url";

        // 1) Cache lesen
        $cached = Cache::get($cacheKey); // ['path' => 'temp/..', 'expires_at' => '...']

        if ($cached) {
            $expiresAt = Carbon::parse($cached['expires_at']);
            // Re-Use, wenn noch gültig UND Datei existiert
            if (now()->lt($expiresAt) && Storage::disk($publicDisk)->exists($cached['path'])) {
                return Storage::disk($publicDisk)->url($cached['path']);
            }
        }

        // 2) Lock gegen parallele Erzeugung (optional, aber sinnvoll)
        $lock = Cache::lock("lock:{$cacheKey}", 10); // 10s Lock
        try {
            if ($lock->get()) {
                // Prüfe erneut nach Lock (race condition vermeiden)
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    $expiresAt = Carbon::parse($cached['expires_at']);
                    if (now()->lt($expiresAt) && Storage::disk($publicDisk)->exists($cached['path'])) {
                        return Storage::disk($publicDisk)->url($cached['path']);
                    }
                }

                // 3) Neu erzeugen
                $tmpName = Str::uuid()->toString() . '-' . basename($this->path);
                $tmpPath = 'temp/' . $tmpName;

                // Quelle lesen (private → public kopieren), mit Fallback auf public
                $read = Storage::disk($sourceDisk)->readStream($this->path);
                if (! $read) {
                    throw new \RuntimeException("Quelle nicht lesbar: {$this->path}");
                }
                Storage::disk($publicDisk)->writeStream($tmpPath, $read);
                if(Storage::disk($publicDisk)->exists($tmpPath) === false) {
                    Log::error("Fehler beim Schreiben der temporären Datei: {$tmpPath}");
                    throw new \RuntimeException("Ziel nicht schreibbar: {$tmpPath}");
                }else {
                    Log::info("Temporäre Datei erstellt: {$tmpPath}");
                }
                if (is_resource($read)) { fclose($read); }

                // 4) Auto-Delete planen NACH Ablauf
                DeleteTempFile::dispatch($publicDisk, $tmpPath)
                    ->delay(now()->addMinutes($minutes));

                // 5) In Cache legen (TTL = Minuten) – wir speichern Pfad + Ablauf
                $payload = [
                    'path'       => $tmpPath,
                    'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
                ];
                Cache::put($cacheKey, $payload, now()->addMinutes($minutes));

                return Storage::disk($publicDisk)->url($tmpPath);
            }
        } finally {
            optional($lock)->release();
        }

        // 6) Falls Lock nicht bekommen → kurzer Retry auf Cache
        $cached = Cache::get($cacheKey);
        if ($cached && Storage::disk($publicDisk)->exists($cached['path'])) {
            return Storage::disk($publicDisk)->url($cached['path']);
        }

        // Fallback: einmal hart neu erzeugen (sehr selten nötig)
        $tmpName = Str::uuid()->toString() . '-' . basename($this->path);
        $tmpPath = 'temp/' . $tmpName;

        $read = Storage::disk($sourceDisk)->readStream($this->path);
        if (! $read) {
            throw new \RuntimeException("Quelle nicht lesbar: {$this->path}");
        }
        Storage::disk($publicDisk)->writeStream($tmpPath, $read);
        if(Storage::disk($publicDisk)->exists($tmpPath) === false) {
            Log::error("Fehler beim Schreiben der temporären Datei: {$tmpPath}");
            throw new \RuntimeException("Ziel nicht schreibbar: {$tmpPath}");
        }else {
            Log::info("Temporäre Datei erstellt: {$tmpPath}");
        }
        if (is_resource($read)) { fclose($read); }

        DeleteTempFile::dispatch($publicDisk, $tmpPath)
            ->delay(now()->addMinutes($minutes));

        Cache::put($cacheKey, [
            'path'       => $tmpPath,
            'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
        ], now()->addMinutes($minutes));

        return Storage::disk($publicDisk)->url($tmpPath);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Gibt true zurück, wenn die Datei dem aktuell angemeldeten User gehört.
     */
    public function getIsOwnedByAuthUserAttribute(): bool
    {
        $authUser = Auth::user();

        if (!$authUser) {
            return false;
        }

        return (int) $this->user_id === (int) $authUser->id;
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
