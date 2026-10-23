<?php

namespace App\Http\Controllers\Api;

use App\Services\ReportBooks\StampStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportBookStampController extends AdminStorageController
{
    public function storeStamp(Request $request): JsonResponse
    {
        abort_unless($this->validateApiKey($request), 403);
        $data = $request->validate(['file' => 'required|file|max:4096|mimetypes:image/png,image/jpeg']);

        return response()->json(['success' => true] + StampStorage::store($data['file']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function verifyStamp(Request $request): JsonResponse
    {
        abort_unless($this->validateApiKey($request), 403);
        $data = $request->validate([
            'path' => 'required|string|max:150', 'hash' => 'required|string|size:64',
            'mime' => 'required|in:image/png,image/jpeg', 'preview' => 'sometimes|boolean',
        ]);
        $verified = StampStorage::verify($data['path'], $data['hash'], $data['mime']);

        return response()->json([
            'verified' => $verified,
            'preview' => $verified && ($data['preview'] ?? false)
                ? StampStorage::preview($data['path'], $data['hash'], $data['mime']) : null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
