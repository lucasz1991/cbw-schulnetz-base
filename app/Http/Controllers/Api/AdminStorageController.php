<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class AdminStorageController extends Controller
{
    protected function validateApiKey(Request $request): bool
    {
        $incomingKey = $request->header('X-API-KEY');
        $storedKey = Setting::where('key', 'base_api_key')->value('value');
        return $incomingKey === $storedKey;
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return response()->json(['success' => false, 'error' => 'Ungültiger API-Key.'], 403);
        }

        $validated = $request->validate([
            'file' => 'required|max:40960',
            'folder' => 'nullable|string',
            'visibility' => 'nullable|in:public,private',
        ]);

        $visibility = $validated['visibility'] ?? 'private';
        $disk = $visibility === 'public' ? 'public' : 'private';

        // Ordner sanft normalisieren (Unterordner erlauben)
        $folderInput = trim($validated['folder'] ?? 'uploads/files', '/');
        $folder = preg_replace('#[^A-Za-z0-9/_\-.]#', '', $folderInput) ?: 'uploads/files';

        $file = $validated['file'];
        $origBase = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBase = Str::slug($origBase) ?: 'file';
        $ext = strtolower($file->getClientOriginalExtension());
        $filename = Str::random(12) . '-' . $safeBase . '.' . $ext;

        $path = $file->storeAs($folder, $filename, $disk);

        $size = Storage::disk($disk)->size($path);
        $mime = $file->getMimeType();
        $url  = $disk === 'public' ? Storage::disk($disk)->url($path) : null;

        Log::info('Media gespeichert', ['disk' => $disk, 'path' => $path, 'mime' => $mime, 'size' => $size]);

        return response()->json([
            'success'    => true,
            'url'        => $url,                  // nur bei public
            'path'       => $path,
            'name'       => basename($path),
            'original'   => $file->getClientOriginalName(),
            'mime'       => $mime,
            'type'       => $ext,
            'size'       => $size,
            'visibility' => $visibility,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return response()->json(['success' => false, 'error' => 'Ungültiger API-Key.'], 403);
        }

        $validated = $request->validate([
            'path'       => 'required|string',
            'visibility' => 'nullable|in:public,private',
        ]);

        // Pfad sanitisieren
        $rawPath = ltrim($validated['path'], '/');
        $path = preg_replace('#[^A-Za-z0-9/_\-.]#', '', $rawPath);

        // Disk bestimmen oder beide prüfen
        $disks = isset($validated['visibility'])
            ? [ $validated['visibility'] === 'public' ? 'public' : 'private' ]
            : ['private','public'];

        foreach ($disks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
                return response()->json(['success' => true, 'message' => 'Datei gelöscht.', 'disk' => $disk, 'path' => $path]);
            }
        }

        return response()->json(['success' => false, 'error' => 'Datei nicht gefunden.'], 404);
    }

}
