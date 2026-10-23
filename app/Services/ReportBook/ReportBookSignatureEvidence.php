<?php

namespace App\Services\ReportBook;

use App\Models\File;
use App\Models\ReportBook;
use App\Services\ReportBooks\LocationPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Existing signature files remain evidence; only their current-use validity changes. */
class ReportBookSignatureEvidence
{
    public const INVALIDATED_IDS = 'reportbook_invalidated_signature_ids';

    public static function currentFile(ReportBook $book, string $role): ?File
    {
        if (DB::transactionLevel() > 0 && $book->exists) {
            $book->settings = ReportBook::query()->whereKey($book->id)->lockForUpdate()->value('settings');
        }
        return $book->files()->where('type', 'sign_reportbook_'.$role)
            ->whereNotIn('id', (array) data_get($book->settings, self::INVALIDATED_IDS, []))
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())
            ->latest('id')->first();
    }

    /** Caller holds policy, book and entry locks. Returns false for the legacy path. */
    public static function preserveAndInvalidate(ReportBook $book): bool
    {
        if (! LocationPolicy::schemaReady()) return false;
        $book->settings = ReportBook::query()->whereKey($book->id)->lockForUpdate()->value('settings');
        $entries = $book->entries()->where('status', 2)->orderBy('id')->lockForUpdate()->get();
        if ($entries->isEmpty()) return false;
        $files = $book->files()->whereIn('type', ['sign_reportbook_participant', 'sign_reportbook_trainer'])
            ->orderBy('id')->lockForUpdate()->get();
        $invalidIds = (array) data_get($book->settings, self::INVALIDATED_IDS, []);
        $current = $files->whereNotIn('id', $invalidIds)->sortByDesc('id')->unique('type');
        $snapshots = $current->map(function (File $file) {
            $data = $file->only(['id', 'type', 'disk', 'path', 'created_at']);
            $data['created_at'] = $file->getRawOriginal('created_at');
            $data['disk'] = $file->disk ?: 'private';
            $data['sha256'] = null;
            $data['missing_at_snapshot'] = true;
            if ($file->path && in_array($data['disk'], ['private', 'public'], true) && Storage::disk($data['disk'])->exists($file->path)) {
                $data['sha256'] = hash('sha256', Storage::disk($data['disk'])->get($file->path));
                $data['missing_at_snapshot'] = false;
            }
            return $data;
        })->values()->all();
        foreach ($entries->whereNull('automated_approval_audit_id') as $entry) {
            DB::table('report_book_policy_audit')->insertOrIgnore([
                'event_key' => 'entry:'.$entry->id.':signatures-preserved', 'event' => 'manual_signatures_preserved',
                'book_id' => $book->id, 'entry_id' => $entry->id, 'actor_id' => $book->user_id,
                'payload' => json_encode(['signature_files' => $snapshots, 'preserved_at' => LocationPolicy::utcNow()->toIso8601String()], JSON_THROW_ON_ERROR),
                'created_at' => LocationPolicy::utcNow()->format('Y-m-d H:i:s'),
            ]);
        }
        $settings = $book->settings ?? [];
        $settings[self::INVALIDATED_IDS] = array_values(array_unique(array_merge($invalidIds, $files->modelKeys())));
        $book->settings = $settings;
        $book->saveQuietly();
        return true;
    }

    public static function fileIsPreserved(File $file): bool
    {
        if ($file->fileable_type !== ReportBook::class || ! str_starts_with((string) $file->type, 'sign_reportbook_')
            || ! LocationPolicy::schemaReady()) return false;
        // Generic media deletion cannot synchronize with an imminent approval.
        // Only the guarded participant invalidation path may remove obsolete files.
        return true;
    }

    public static function pathIsPreserved(string $path): bool
    {
        if ($path === '' || ! LocationPolicy::schemaReady()) return false;
        if (str_starts_with($path, 'signatures/report-book/')) return true;
        $files = File::query()->where('path', $path)->where('fileable_type', ReportBook::class)
            ->whereIn('type', ['sign_reportbook_participant', 'sign_reportbook_trainer'])->get();
        if ($files->isNotEmpty()) return true;
        // Audit evidence outlives source rows. Exact JSON comparison avoids LIKE/path ambiguity.
        foreach (DB::table('report_book_policy_audit')->whereIn('event', ['manual_signatures_preserved', 'entry_approved'])
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->cursor() as $audit) {
            $payload = json_decode($audit->payload, true);
            foreach ((array) data_get($payload, $audit->event === 'entry_approved' ? 'context.signature_files' : 'signature_files', []) as $snapshot) {
                if (($snapshot['path'] ?? null) === $path) return true;
            }
        }
        return false;
    }
}
