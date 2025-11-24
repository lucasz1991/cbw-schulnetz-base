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

    /**
     * Optional: Liste von ReportBook IDs
     */
    public ?array $ids = null;

    /**
     * Konstruktor – optional IDs übergeben
     */
    public function __construct(?array $ids = null)
    {
        $this->ids = $ids;
    }

    /**
     * Main Logic
     */
    public function handle(): void
    {
        // --- ReportBooks abrufen -------------------------------------------
        $query = ReportBook::query()->with('entries');

        if (!empty($this->ids)) {
            $query->whereIn('id', $this->ids);
        }

        $books = $query->get();

        if ($books->isEmpty()) {
            Log::info('CheckReportBooks: Keine passenden ReportBooks gefunden.');
            return;
        }

        // --- Prüfen ---------------------------------------------------------
        foreach ($books as $book) {

            $allSubmitted = $book->entries->count() > 0 &&
                            $book->entries->every(fn ($e) => $e->status === 1);

            if (!$allSubmitted) {
                continue;
            }

            $existing = AdminTask::where('task_type', 'reportbook_review')
                ->where('description', 'LIKE', "%ReportBook {$book->id}%")
                ->first();

            if ($existing) {
                continue;
            }

            // --- AdminTask erstellen ----------------------------------------
            AdminTask::create([
                'created_by'   => $book->user_id,
                'context_type' => ReportBook::class,
                'context_id'   => $book->id,
                'task_type'    => 'reportbook_review',
                'description'  => "Baustein Berichtsheft {$book->id} vollständig eingereicht – Prüfung & Freigabe erforderlich.",
                'status'       => AdminTask::STATUS_OPEN,
            ]);

            Log::info("CheckReportBooks: AdminTask für Berichtsheft {$book->id} erstellt.");
        }
    }
}
