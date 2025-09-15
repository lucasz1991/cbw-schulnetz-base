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
            return response()->json(['error' => 'Ungültiger API-Key.'], 403);
        }
        $request->validate([
            'file' => 'required|file|max:8192',
        ]);
        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'svg', 'webp', 'pdf', 'docx', 'xlsx', 'txt', 'zip', 'rar'];
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json(['error' => 'Dateityp nicht erlaubt.'], 422);
        }
        $folder = $request->input('folder', 'uploads/files');
        $path = $request->file('file')->store($folder, 'private');
            Log::info('Media gespeichert', ['path' => $path]);
        return response()->json([
            'url' => Storage::url($path),
            'path' => $path,
            'name' => basename($path),
            'type' => $extension,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return response()->json(['error' => 'Ungültiger API-Key.'], 403);
        }
        $request->validate([
            'path' => 'required|string',
        ]);
        if (Storage::disk('private')->exists($request->path)) {
            Storage::disk('private')->delete($request->path);
            return response()->json(['message' => 'Datei gelöscht.']);
        }
        return response()->json(['error' => 'Datei nicht gefunden.'], 404);
    }
}
