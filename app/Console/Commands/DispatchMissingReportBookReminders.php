<?php

namespace App\Console\Commands;

use App\Jobs\ReportBook\SendMissingReportBookReminderJob;
use App\Models\ReportBook;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DispatchMissingReportBookReminders extends Command
{
    protected $signature = 'reportbooks:dispatch-missing-reminders
                            {--from-days=28 : Aeltere Grenze in Tagen (inklusive)}
                            {--to-days=14 : Juengere Grenze in Tagen (inklusive)}
                            {--chunk=200 : Anzahl Reminder pro Job}
                            {--dry-run : Nur anzeigen, nichts dispatchen}';

    protected $description = 'Ermittelt offene Berichtshefte pro ReportBook und dispatcht genau eine interne Erinnerung je ReportBook.';

    public function handle(): int
    {
        if (! $this->isReminderEnabled()) {
            $this->info('Reminder Missing Report Book ist deaktiviert (settings: mails.reminder_missing_report_book = false).');
            return Command::SUCCESS;
        }

        $fromDays = max(1, (int) $this->option('from-days'));
        $toDays = max(1, (int) $this->option('to-days'));
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if ($fromDays < $toDays) {
            [$fromDays, $toDays] = [$toDays, $fromDays];
        }

        $windowStart = Carbon::today()->subDays($fromDays)->toDateString();
        $windowEnd = Carbon::today()->subDays($toDays)->toDateString();

        $this->info("Suche offene Berichtshefte fuer Kurse mit Enddatum zwischen {$windowStart} und {$windowEnd}...");

        $rows = DB::table('report_books as rb')
            ->join('courses as c', function ($join) {
                $join->on('c.id', '=', 'rb.course_id')
                    ->whereNull('c.deleted_at');
            })
            ->join('users as u', 'u.id', '=', 'rb.user_id')
            ->leftJoin('course_days as cd', function ($join) {
                $join->on('cd.course_id', '=', 'rb.course_id')
                    ->whereNull('cd.deleted_at');
            })
            ->leftJoin('report_book_entries as rbe', function ($join) {
                $join->on('rbe.report_book_id', '=', 'rb.id')
                    ->on('rbe.course_day_id', '=', 'cd.id');
            })
            ->whereDate('c.planned_end_date', '>=', $windowStart)
            ->whereDate('c.planned_end_date', '<=', $windowEnd)
            ->groupBy('rb.id', 'rb.user_id', 'rb.course_id', 'c.title', 'c.planned_end_date')
            ->select([
                'rb.id as report_book_id',
                'rb.user_id as to_user',
                'rb.course_id as course_id',
                'c.title as course_title',
                'c.planned_end_date as course_end_date',
                DB::raw('COUNT(DISTINCT cd.id) as total_days'),
                DB::raw('SUM(CASE WHEN rbe.id IS NULL OR rbe.status < 2 THEN 1 ELSE 0 END) as open_days'),
            ])
            ->havingRaw('COUNT(DISTINCT cd.id) > 0')
            ->havingRaw('SUM(CASE WHEN rbe.id IS NULL OR rbe.status < 2 THEN 1 ELSE 0 END) > 0')
            ->orderBy('rb.id')
            ->get();

        // Bereits erinnerte ReportBooks (über settings) ausfiltern:
        // settings.missing_reportbook_reminder_sent_at gesetzt => nicht erneut senden
        $reportBookIds = $rows->pluck('report_book_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (!empty($reportBookIds)) {
            $booksById = ReportBook::query()
                ->whereIn('id', $reportBookIds)
                ->get(['id', 'settings'])
                ->keyBy('id');

            $rows = $rows->reject(function ($row) use ($booksById) {
                $reportBook = $booksById->get((int) $row->report_book_id);
                $sentAt = data_get($reportBook?->settings, 'missing_reportbook_reminder_sent_at');
                return !empty($sentAt);
            })->values();
        }

        $total = $rows->count();

        if ($total === 0) {
            $this->info('Keine offenen Berichtshefte im konfigurierten Enddatum-Fenster gefunden.');
            return Command::SUCCESS;
        }

        $this->info("Gefunden: {$total} ReportBook(s) mit offenen Tagen.");

        $chunks = $rows->chunk($chunkSize);

        foreach ($chunks as $index => $chunk) {
            $chunkNo = $index + 1;
            $count = $chunk->count();
            $this->line("Chunk {$chunkNo}: {$count} Reminder-Message(s).");

            if ($dryRun) {
                continue;
            }

            dispatch(new SendMissingReportBookReminderJob($chunk->map(fn ($row) => (array) $row)->values()->all()));
        }

        if ($dryRun) {
            $this->info('Dry-Run beendet. Es wurden keine Jobs dispatcht.');
            return Command::SUCCESS;
        }

        $this->info('Alle Reminder-Jobs wurden erfolgreich dispatcht.');

        return Command::SUCCESS;
    }

    private function isReminderEnabled(): bool
    {
        $rawValue = Setting::getValueUncached('mails', 'reminder_missing_report_book');
        $decoded = json_decode((string) $rawValue, true);
        $value = $decoded ?? $rawValue;

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
