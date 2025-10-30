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
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $mime = (string) ($this->mime_type ?? '');

        // → Bilder kriegen ein echtes Thumbnail / Temp-URL
        if (str_starts_with($mime, 'image/')) {
            return $this->getEphemeralPublicUrl(10);
        }

        // Extension aus Pfad/MIME ermitteln
        $ext = strtolower((string) ($this->guessExtension($mime, $this->path) ?? ''));

        // 1) Exakte Zuordnung nach Dateiendung (aus deinem Ordner)
        $byExt = [
            // Office & Dokumente
            'pdf'  => 'file-pdf.png',
            'doc'  => 'file-word.png',
            'docx' => 'file-word.png',
            'ppt'  => 'file-powerpoint.png',
            'pptx' => 'file-powerpoint.png',
            'xls'  => 'file-exel.png',     // Schreibweise wie im Screenshot
            'xlsx' => 'file-exel.png',
            'csv'  => 'csv-icon.svg',
            'txt'  => 'txt-icon.svg',
            'xml'  => 'xml-icon.svg',
            'htm'  => 'html-icon.svg',
            'html' => 'html-icon.svg',

            // Code & Scripts
            'php'  => 'php-icon.svg',

            // Grafik / Design
            'ai'   => 'ai-icon.svg',
            'eps'  => 'eps-icon.svg',
            'cdr'  => 'cdr-icon.svg',
            'gif'  => 'gif-icon.svg',
            'raw'  => 'raw-icon.svg',

            // Audio
            'mp3'  => 'mp3-icon.svg',
            'wav'  => 'wav-icon.svg',

            // Video
            'mp4'  => 'mp4-icon.svg',
            'avi'  => 'avi-icon.svg',
            'mov'  => 'mov-icon.svg',
            'mpg'  => 'mpg-icon.svg',
            'mpeg' => 'mpg-icon.svg',

            // Archive
            'zip'  => 'zip-icon.svg',
            'rar'  => 'rar-icon.svg',
            '7z'   => 'zip-icon.svg',
        ];

        if ($ext && isset($byExt[$ext])) {
            return asset('site-images/fileicons/' . $byExt[$ext]);
        }

        // 2) MIME-Fallbacks (falls keine Extension erkannt)
        if (str_starts_with($mime, 'video/')) {
            return asset('site-images/fileicons/file-video.png');
        }
        if (str_starts_with($mime, 'audio/')) {
            return asset('site-images/fileicons/file-audio.png');
        }
        if (str_contains($mime, 'pdf')) {
            return asset('site-images/fileicons/file-pdf.png');
        }
        if (str_contains($mime, 'zip') || str_contains($mime, 'compressed')) {
            return asset('site-images/fileicons/file-zip.png');
        }
        if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) {
            return asset('site-images/fileicons/file-exel.png');
        }
        if (str_contains($mime, 'word')) {
            return asset('site-images/fileicons/file-word.png');
        }
        if (str_contains($mime, 'powerpoint') || str_contains($mime, 'presentation')) {
            return asset('site-images/fileicons/file-powerpoint.png');
        }
        if (str_starts_with($mime, 'text/')) {
            if (str_contains($mime, 'html')) {
                return asset('site-images/fileicons/html-icon.svg');
            }
            return asset('site-images/fileicons/txt-icon.svg');
        }
        if (str_contains($mime, 'php')) {
            return asset('site-images/fileicons/php-icon.svg');
        }

        // 3) Letzter Fallback → generisches Doku-Icon
        return asset('site-images/fileicons/doc-icon.svg');
    }



    public function getSizeFormattedAttribute(): string
    {
        $bytes = (int) $this->size;

        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        } else {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
    }

    public function getEphemeralPublicUrl(int $minutes = 10): string
    {
        $publicDisk = 'public';
        $sourceDisk = 'private';
        $cacheKey   = "file:{$this->getKey()}:temp_url";

        $cached = Cache::get($cacheKey);

        if ($cached) {
            $expiresAt = Carbon::parse($cached['expires_at']);
            if (now()->lt($expiresAt) && Storage::disk($publicDisk)->exists($cached['path'])) {
                return Storage::disk($publicDisk)->url($cached['path']);
            }
        }

        $lock = Cache::lock("lock:{$cacheKey}", 10); 
        try {
            if ($lock->get()) {
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    $expiresAt = Carbon::parse($cached['expires_at']);
                    if (now()->lt($expiresAt) && Storage::disk($publicDisk)->exists($cached['path'])) {
                        return Storage::disk($publicDisk)->url($cached['path']);
                    }
                }

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

        $cached = Cache::get($cacheKey);
        if ($cached && Storage::disk($publicDisk)->exists($cached['path'])) {
            return Storage::disk($publicDisk)->url($cached['path']);
        }

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

    public function getIsOwnedByAuthUserAttribute(): bool
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return false;
        }
        return (int) $this->user_id === (int) $authUser->id;
    }

        public function getNameWithExtensionAttribute(): string
    {
        $safeName = $this->sanitizeName($this->name ?? 'datei');
        $ext = pathinfo($safeName, PATHINFO_EXTENSION);
        if ($ext !== '') {
            return $safeName;
        }
        $guessed = $this->guessExtension($this->mime_type, $this->path);
        return $guessed ? ($safeName . '.' . $guessed) : $safeName;
    }

    protected function sanitizeName(string $name): string
    {
        $name = trim($name);
        $name = str_replace(['\\', '/', "\0"], '-',$name);
        return $name === '' ? 'datei' : $name;
    }

    protected function guessExtension(?string $mime, ?string $storagePath): ?string
    {
        if ($mime) {
            try {
                $candidates = MimeTypes::getDefault()->getExtensions($mime);
                if (!empty($candidates)) {
                    return strtolower($candidates[0]);
                }
            } catch (\Throwable $e) {}
        }
        if ($storagePath) {
            $ext = pathinfo($storagePath, PATHINFO_EXTENSION);
            if ($ext !== '') {
                return strtolower($ext);
            }
        }
        return null;
    }

    public function download(string $disk = 'private', bool $denyExpired = true): StreamedResponse
    {
        if ($denyExpired && $this->isExpired()) {
            abort(403, 'Diese Datei ist abgelaufen und kann nicht mehr heruntergeladen werden.');
        }
        $filename = $this->name_with_extension ?? $this->name ?? 'datei';
        $mime = $this->mime_type
            ?: (Storage::disk($disk)->exists($this->path) ? (Storage::disk($disk)->mimeType($this->path) ?: null) : null)
            ?: 'application/octet-stream';
        return Storage::disk($disk)->download($this->path, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    public function getMimeTypeForHumans(): string
    {
        $mime = strtolower((string) $this->mime_type);
        $ext  = $this->guessExtension($mime, $this->path);
        $extU = $ext ? strtoupper($ext) : null;

        // 1) Spezifische Zuordnungen
        $map = [
            // Bilder
            'image/jpeg'                    => 'JPEG-Bild',
            'image/png'                     => 'PNG-Bild',
            'image/gif'                     => 'GIF-Bild',
            'image/webp'                    => 'WebP-Bild',
            'image/svg+xml'                 => 'SVG-Vektorgrafik',
            'image/heic'                    => 'HEIC-Bild',
            'image/heif'                    => 'HEIF-Bild',
            'image/tiff'                    => 'TIFF-Bild',
            'image/bmp'                     => 'BMP-Bild',
            'image/x-icon'                  => 'ICO-Icon',

            // Audio
            'audio/mpeg'                    => 'MP3-Audiodatei',
            'audio/mp4'                     => 'M4A-Audiodatei',
            'audio/aac'                     => 'AAC-Audiodatei',
            'audio/wav'                     => 'WAV-Audiodatei',
            'audio/x-wav'                   => 'WAV-Audiodatei',
            'audio/ogg'                     => 'OGG-Audiodatei',
            'audio/opus'                    => 'OPUS-Audiodatei',
            'audio/flac'                    => 'FLAC-Audiodatei',
            'audio/midi'                    => 'MIDI-Datei',
            'audio/x-midi'                  => 'MIDI-Datei',

            // Video
            'video/mp4'                     => 'MP4-Video',
            'video/quicktime'               => 'MOV-Video',
            'video/x-msvideo'               => 'AVI-Video',
            'video/x-matroska'              => 'MKV-Video',
            'video/webm'                    => 'WebM-Video',
            'video/mpeg'                    => 'MPEG-Video',
            'video/3gpp'                    => '3GPP-Video',

            // PDF
            'application/pdf'               => 'PDF-Dokument',

            // Office (Microsoft)
            'application/msword'                                                => 'Microsoft Word-Dokument (DOC)',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Microsoft Word-Dokument (DOCX)',
            'application/vnd.ms-excel'                                          => 'Microsoft Excel-Arbeitsmappe (XLS)',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Microsoft Excel-Arbeitsmappe (XLSX)',
            'text/csv'                                                          => 'CSV-Tabelle',
            'application/vnd.ms-powerpoint'                                     => 'Microsoft PowerPoint-Präsentation (PPT)',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'Microsoft PowerPoint-Präsentation (PPTX)',

            // Office (OpenDocument)
            'application/vnd.oasis.opendocument.text'         => 'OpenDocument Text (ODT)',
            'application/vnd.oasis.opendocument.spreadsheet'  => 'OpenDocument Tabelle (ODS)',
            'application/vnd.oasis.opendocument.presentation' => 'OpenDocument Präsentation (ODP)',

            // Text & Web
            'text/plain'                   => 'Textdatei',
            'text/html'                    => 'HTML-Dokument',
            'text/css'                     => 'CSS-Stylesheet',
            'application/javascript'       => 'JavaScript-Datei',
            'text/javascript'              => 'JavaScript-Datei',
            'application/json'             => 'JSON-Datei',
            'application/xml'              => 'XML-Datei',
            'text/xml'                     => 'XML-Datei',
            'application/x-yaml'           => 'YAML-Datei',
            'text/yaml'                    => 'YAML-Datei',
            'application/sql'              => 'SQL-Skript',
            'text/x-sql'                   => 'SQL-Skript',

            // Code/Quelltexte (häufige)
            'text/x-php'                   => 'PHP-Datei',
            'application/x-httpd-php'      => 'PHP-Datei',
            'application/x-php'            => 'PHP-Datei',
            'text/x-python'                => 'Python-Datei',
            'text/x-java-source'           => 'Java-Quelldatei',
            'text/x-c'                     => 'C-Quelldatei',
            'text/x-c++'                   => 'C++-Quelldatei',
            'application/x-typescript'     => 'TypeScript-Datei',
            'text/markdown'                => 'Markdown-Dokument',

            // Design/Publishing
            'application/postscript'       => 'PostScript (AI/EPS)',
            'application/illustrator'      => 'Adobe Illustrator-Datei',
            'image/vnd.adobe.photoshop'    => 'Photoshop-Datei (PSD)',
            'application/vnd.corel-draw'   => 'CorelDRAW-Datei (CDR)',

            // Archive & Kompression
            'application/zip'              => 'ZIP-Archiv',
            'application/x-7z-compressed'  => '7z-Archiv',
            'application/x-rar-compressed' => 'RAR-Archiv',
            'application/x-tar'            => 'TAR-Archiv',
            'application/gzip'             => 'GZIP-Archiv',
            'application/x-bzip2'          => 'BZIP2-Archiv',
            'application/x-xz'             => 'XZ-Archiv',

            // Sonstiges verbreitet
            'application/octet-stream'     => 'Binärdatei',
        ];

        if (isset($map[$mime])) {
            // Optional: Extension im Label anzeigen
            if ($extU && !str_contains($map[$mime], "($extU)")) {
                return $map[$mime] . " ($extU)";
            }
            return $map[$mime];
        }

        // 2) Familien-Fallbacks (image/*, audio/*, …)
        $family = strtok($mime, '/'); // z.B. "image"
        $familyLabels = [
            'image'       => 'Bilddatei',
            'audio'       => 'Audiodatei',
            'video'       => 'Videodatei',
            'text'        => 'Textdatei',
            'font'        => 'Schriftart',
            'multipart'   => 'Mehrteilige Datei',
            'message'     => 'Nachrichtenformat',
            'model'       => '3D/Modelldatei',
            'application' => 'Anwendungsdatei',
        ];
        if ($family && isset($familyLabels[$family])) {
            return $extU ? "{$familyLabels[$family]} ($extU)" : $familyLabels[$family];
        }

        // 3) Letzter Fallback – möglichst hilfreich mit Extension/MIME
        if ($extU) {
            return "Datei ($extU)";
        }
        return $mime !== '' ? "Unbekannter Dateityp ({$mime})" : 'Unbekannter Dateityp';
    }


    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
