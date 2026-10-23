<?php

namespace App\Services\ReportBooks;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StampStorage
{
    public const PREFIX = 'reportbook-stamps/';
    public const MAX_BYTES = 4194304;

    public static function verify(string $path, string $hash, string $mime): bool
    {
        if (! preg_match('#\Areportbook-stamps/[a-f0-9]{64}\.(png|jpg)\z#D', $path)
            || ! preg_match('/\A[a-f0-9]{64}\z/D', $hash)
            || ! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return false;
        }

        try {
            $disk = Storage::disk('private');
            if (! $disk->exists($path) || $disk->size($path) > self::MAX_BYTES) {
                return false;
            }
            $bytes = $disk->get($path);
            $image = @getimagesizefromstring($bytes);

            return $bytes !== '' && hash_equals($hash, hash('sha256', $bytes))
                && (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) === $mime
                && ($image['mime'] ?? null) === $mime
                && $path === self::PREFIX.$hash.($mime === 'image/png' ? '.png' : '.jpg');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function store(UploadedFile $file): array
    {
        validator(['file' => $file], ['file' => 'required|file|max:4096|mimetypes:image/png,image/jpeg'])->validate();
        $bytes = file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $image = @getimagesizefromstring($bytes);
        if (! in_array($mime, ['image/png', 'image/jpeg'], true) || ($image['mime'] ?? null) !== $mime) {
            throw ValidationException::withMessages(['stampUpload' => 'Bitte ein gültiges PNG- oder JPEG-Bild hochladen.']);
        }
        $hash = hash('sha256', $bytes);
        $path = self::PREFIX.$hash.($mime === 'image/png' ? '.png' : '.jpg');
        $disk = Storage::disk('private');
        if (! $disk->exists($path)) {
            $disk->put($path, $bytes, ['visibility' => 'private']);
        }
        if (! self::verify($path, $hash, $mime)) {
            throw ValidationException::withMessages(['stampUpload' => 'Die Stempeldatei konnte nicht unverändert gespeichert werden.']);
        }

        return ['stamp_path' => $path, 'stamp_sha256' => $hash, 'stamp_mime' => $mime, 'size' => strlen($bytes)];
    }

    public static function preview(string $path, string $hash, string $mime): ?string
    {
        return self::verify($path, $hash, $mime)
            ? 'data:'.$mime.';base64,'.base64_encode(Storage::disk('private')->get($path))
            : null;
    }
}
