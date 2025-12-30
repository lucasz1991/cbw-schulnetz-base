<?php

namespace App\Jobs\ReportBook;

use App\Models\ReportBook;
use App\Models\AdminTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

            // Nur wenn vollständig eingereicht
            $allSubmitted = $book->entries->count() > 0
                && $book->entries->every(fn ($e) => (int) $e->status === 1);

            if (! $allSubmitted) {
                continue;
            }

            // Ein Task pro ReportBook (stabil über context_* + task_type)
            $task = AdminTask::query()
                ->where('task_type', 'reportbook_review')
                ->where('context_type', ReportBook::class)
                ->where('context_id', $book->id)
                ->first();

            // Fallback für Altlasten (falls früher nur description genutzt wurde)
            if (! $task) {
                $task = AdminTask::query()
                    ->where('task_type', 'reportbook_review')
                    ->where('description', 'LIKE', "%Berichtsheft {$book->id}%")
                    ->first();
            }

            if ($task) {
                // Schon vorhanden -> niemals neu erstellen

                // Wenn gerade in Bearbeitung: nichts ändern
                if ((int) $task->status === (int) AdminTask::STATUS_IN_PROGRESS) {
                    Log::info("CheckReportBooks: Task {$task->id} für Berichtsheft {$book->id} ist in Bearbeitung – unverändert.");
                    continue;
                }

                // Sonst: reaktivieren (OPEN + Zuordnung löschen)
                $task->status = AdminTask::STATUS_OPEN;
                $task->assigned_to = null;
                $task->completed_at = null; // falls er mal abgeschlossen war
                $task->save();

                Log::info("CheckReportBooks: Task {$task->id} für Berichtsheft {$book->id} reaktiviert (OPEN, assigned_to null).");
                continue;
            }

            // Noch kein Task -> neu erstellen
            AdminTask::create([
                'created_by'   => $book->user_id,
                'context_type' => ReportBook::class,
                'context_id'   => $book->id,
                'task_type'    => 'reportbook_review',
                'description'  => "Baustein Berichtsheft {$book->id} vollständig eingereicht – Prüfung & Freigabe erforderlich.",
                'status'       => AdminTask::STATUS_OPEN,
                'assigned_to'  => null,
                'completed_at' => null,
            ]);

            Log::info("CheckReportBooks: AdminTask für Berichtsheft {$book->id} erstellt.");
        }
    }
}
