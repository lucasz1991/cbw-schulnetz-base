<?php

namespace App\Services\ReportBook;

use App\Models\ReportBook;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Resolves historical approval evidence without creating signatures or public copies. */
class ReportBookPdfContext
{
    public function prepare(ReportBook $book): void
    {
        $book->loadMissing('files');
        foreach (['participant', 'trainer'] as $role) {
            $file = ReportBookSignatureEvidence::currentFile($book, $role);
            $book->{$role.'SignatureFile'} = $file;
            $book->{$role.'SignatureUrl'} = $file ? $this->imageData($file->disk ?: 'private', $file->path) : null;
        }

        $entries = $book->entries;
        if (Schema::hasTable('report_book_policy_audit')) {
            $manualEvidence = DB::table('report_book_policy_audit')->where('book_id', $book->id)
                ->whereIn('entry_id', $entries->modelKeys())->where('event', 'manual_signatures_preserved')->get()->keyBy('entry_id');
            foreach ($entries as $entry) {
                if ($audit = $manualEvidence->get($entry->id)) {
                    $this->signatureSnapshot($entry, data_get(json_decode($audit->payload, true), 'signature_files', []));
                }
            }
        }
        $auditIds = $entries->pluck('automated_approval_audit_id')->filter()->unique()->values();
        if ($auditIds->isEmpty()) {
            return;
        }

        abort_unless(Schema::hasTable('report_book_policy_audit'), 409, 'Der Freigabenachweis ist nicht verfügbar.');
        $audits = DB::table('report_book_policy_audit')->whereIn('id', $auditIds)->get()->keyBy('id');

        foreach ($entries as $entry) {
            $id = $entry->automated_approval_audit_id;
            if (! $id) {
                continue;
            }
            $audit = $audits->get($id);
            abort_unless($audit && (int) $audit->book_id === (int) $book->id
                && (int) $audit->entry_id === (int) $entry->id
                && $audit->event === 'entry_approved' && (int) $entry->status === 2,
                409, 'Der Freigabenachweis passt nicht zu diesem Eintrag.');

            $payload = json_decode((string) $audit->payload, true);
            // A missing historical signature is intentionally empty, never replaced by a later one.
            $this->signatureSnapshot($entry, data_get($payload, 'context.signature_files', []));
            $path = data_get($payload, 'stamp.path');
            $hash = data_get($payload, 'stamp.sha256');
            $revisionId = data_get($payload, 'stamp.revision_id');
            $approvedAt = data_get($payload, 'approved_at');
            abort_unless(is_string($path) && is_string($hash) && preg_match('/^[a-f0-9]{64}$/i', $hash)
                && $revisionId && (int) $audit->revision_id === (int) $revisionId
                && is_string($approvedAt) && $approvedAt !== '',
                409, 'Der Freigabenachweis ist unvollständig.');

            $image = $this->imageData('private', $path, $hash);
            abort_unless($image, 409, 'Die historische Stempeldatei fehlt oder wurde verändert.');
            try {
                $date = CarbonImmutable::parse($approvedAt, 'UTC')->setTimezone('Europe/Berlin')->format('d.m.Y');
            } catch (\Throwable $e) {
                abort(409, 'Das Datum des Freigabenachweises ist ungültig.');
            }

            $entry->setAttribute('system_release', [
                'image' => $image, 'date' => $date, 'revision_id' => $revisionId,
                'group_key' => $revisionId.':'.$hash.':'.$date,
            ]);
        }
    }

    private function signatureSnapshot($entry, array $files): void
    {
        $snapshot = ['group_key' => 'snapshot'];
        foreach (['participant', 'trainer'] as $role) {
            $file = collect($files)->filter(fn ($item) => in_array($item['type'] ?? null,
                $role === 'participant' ? ['sign_reportbook_participant', 'reportbook_participant'] : ['sign_reportbook_trainer'], true))->sortByDesc('id')->first();
            $image = $file && ! ($file['missing_at_snapshot'] ?? false)
                ? $this->imageData($file['disk'] ?? 'private', $file['path'] ?? null, $file['sha256'] ?? null) : null;
            abort_if($file && ! empty($file['sha256']) && ! $image, 409, 'Eine historische Unterschrift fehlt oder wurde verändert.');
            $snapshot[$role] = ['file_id' => $file['id'] ?? null, 'image' => $image,
                'date' => ! empty($file['created_at']) ? CarbonImmutable::parse($file['created_at'])->format('d.m.Y') : ''];
            $snapshot['group_key'] .= ':'.($file['id'] ?? 'none');
        }
        $entry->setAttribute('signature_evidence', $snapshot);
    }

    private function imageData(string $disk, ?string $path, ?string $expectedHash = null): ?string
    {
        if (! in_array($disk, ['private', 'public'], true) || ! $path || str_starts_with($path, '/')
            || str_contains($path, '\\') || preg_match('~(^|/)\.\.(/|$)~', $path)) {
            return null;
        }

        try {
            if (! Storage::disk($disk)->exists($path)) {
                return null;
            }
            $bytes = Storage::disk($disk)->get($path);
            if ($expectedHash && ! hash_equals(strtolower($expectedHash), hash('sha256', $bytes))) {
                return null;
            }
            $info = @getimagesizefromstring($bytes);
            if (! $info || ! in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) {
                return null;
            }

            return 'data:'.$info['mime'].';base64,'.base64_encode($bytes);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
