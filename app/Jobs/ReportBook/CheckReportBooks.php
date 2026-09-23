<?php

namespace App\Jobs\ReportBook;

use App\Models\ReportBook;
use App\Models\AdminTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckReportBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?array $ids = null;

    public function __construct(?array $ids = null)
    {
        $this->ids = $ids;
    }

    public function handle(): void
    {
        $query = ReportBook::query()->with('entries');

        if (!empty($this->ids)) {
            $query->whereIn('id', $this->ids);
        }

        $books = $query->get();

        if ($books->isEmpty()) {
            Log::info('CheckReportBooks: Keine passenden ReportBooks gefunden.');
            return;
        }

        foreach ($books as $book) {
            if (\App\Support\ParticipantReportBookAccess::isCoachingCourse((int) $book->course_id)
                || ! $this->isReadyForReview($book)) {
                continue;
            }

            DB::transaction(function () use ($book): void {
                // Der gemeinsame Parent-Lock serialisiert auch den Fall, dass noch
                // keine Task-Zeile existiert und zwei Queue-Jobs parallel starten.
                $currentBook = ReportBook::query()
                    ->whereKey($book->id)
                    ->lockForUpdate()
                    ->first();

                if (! $currentBook) {
                    return;
                }

                $task = AdminTask::query()
                    ->where('task_type', AdminTask::TYPE_REPORTBOOK_REVIEW)
                    ->where('context_type', ReportBook::class)
                    ->where('context_id', $currentBook->id)
                    ->lockForUpdate()
                    ->first();

                // Fallback für Altlasten (falls früher nur description genutzt wurde).
                // Das Leerzeichen hinter der ID bildet eine feste Grenze, damit
                // z. B. Berichtsheft 12 nicht den Eintrag für 123 trifft.
                $legacyTask = false;
                if (! $task) {
                    $task = AdminTask::query()
                        ->where('task_type', AdminTask::TYPE_REPORTBOOK_REVIEW)
                        ->where(function ($query): void {
                            $query->whereNull('context_type')
                                ->orWhereNull('context_id');
                        })
                        ->where(function ($query) use ($currentBook): void {
                            $query->where(
                                'description',
                                'LIKE',
                                "Baustein Berichtsheft {$currentBook->id} %"
                            )->orWhere(
                                'description',
                                'LIKE',
                                "ReportBook {$currentBook->id} %"
                            );
                        })
                        ->lockForUpdate()
                        ->first();

                    $legacyTask = $task !== null;
                }

                // Ein gleichzeitig abgeschlossener Review kann die Entry-Status
                // geändert haben, während dieser Job auf die Task-Zeile wartete.
                $currentBook->load('entries');

                if (! $this->isReadyForReview($currentBook)) {
                    return;
                }

                if ($task) {
                    if ($legacyTask) {
                        $task->context_type = ReportBook::class;
                        $task->context_id = $currentBook->id;
                    }

                    // Wenn gerade in Bearbeitung: nichts ändern
                    if ((int) $task->status === (int) AdminTask::STATUS_IN_PROGRESS) {
                        if ($task->isDirty()) {
                            $task->save();
                        }

                        Log::info("CheckReportBooks: Task {$task->id} für Berichtsheft {$currentBook->id} ist in Bearbeitung – unverändert.");
                        return;
                    }

                    // Sonst: reaktivieren (OPEN + Zuordnung löschen)
                    $task->status = AdminTask::STATUS_OPEN;
                    $task->assigned_to = null;
                    $task->completed_at = null; // falls er mal abgeschlossen war
                    $task->save();

                    Log::info("CheckReportBooks: Task {$task->id} für Berichtsheft {$currentBook->id} reaktiviert (OPEN, assigned_to null).");
                    return;
                }

                // Noch kein Task -> neu erstellen
                AdminTask::create([
                    'created_by'   => $currentBook->user_id,
                    'context_type' => ReportBook::class,
                    'context_id'   => $currentBook->id,
                    'task_type'    => AdminTask::TYPE_REPORTBOOK_REVIEW,
                    'description'  => "Baustein Berichtsheft {$currentBook->id} vollständig eingereicht – Prüfung & Freigabe erforderlich.",
                    'status'       => AdminTask::STATUS_OPEN,
                    'assigned_to'  => null,
                    'completed_at' => null,
                ]);

                Log::info("CheckReportBooks: AdminTask für Berichtsheft {$currentBook->id} erstellt.");
            }, 3);
        }
    }

    private function isReadyForReview(ReportBook $book): bool
    {
        $allSubmitted = $book->entries->count() > 0
            && $book->entries->every(fn ($entry) => (int) $entry->status >= 1);

        if (! $allSubmitted) {
            return false;
        }

        if (! $book->entries->contains(fn ($entry) => (int) $entry->status === 1)) {
            return false;
        }

        $expectedDays = $book->days()
            ->pluck('date')
            ->map(fn ($date) => \Illuminate\Support\Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->all();

        $existingDays = $book->entries
            ->map(function ($entry) {
                $date = $entry->date ?? $entry->day ?? $entry->entry_date ?? null;

                return $date
                    ? \Illuminate\Support\Carbon::parse($date)->toDateString()
                    : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return empty(array_diff($expectedDays, $existingDays));
    }
}
