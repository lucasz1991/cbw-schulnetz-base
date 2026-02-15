<?php

namespace App\Jobs\ReportBook;

use App\Models\Message;
use App\Models\ReportBook;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendMissingReportBookReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $reminders;

    /**
     * @param array<int, array<string, mixed>> $reminders
     */
    public function __construct(array $reminders)
    {
        $this->reminders = $reminders;
    }

    public function handle(): void
    {
        if (empty($this->reminders)) {
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->reminders as $item) {
            $toUserId = (int) ($item['to_user'] ?? 0);
            $reportBookId = (int) ($item['report_book_id'] ?? 0);

            if ($toUserId <= 0 || $reportBookId <= 0) {
                $skipped++;
                continue;
            }

            $reportBook = ReportBook::find($reportBookId);
            if (! $reportBook) {
                $skipped++;
                continue;
            }

            // Doppelschutz: wenn bereits per settings markiert, nicht erneut senden
            if ($reportBook->getSetting('missing_reportbook_reminder_sent_at')) {
                $skipped++;
                continue;
            }

            $courseTitle = trim((string) ($item['course_title'] ?? ''));
            $courseTitle = $courseTitle !== '' ? $courseTitle : 'Baustein';

            $courseEndDate = (string) ($item['course_end_date'] ?? '');
            $formattedCourseEndDate = $courseEndDate !== ''
                ? Carbon::parse($courseEndDate)->format('d.m.Y')
                : 'unbekannt';

            $openDays = max(0, (int) ($item['open_days'] ?? 0));
            $totalDays = max(0, (int) ($item['total_days'] ?? 0));
            $courseId = (int) ($item['course_id'] ?? 0);

            $subject = "Erinnerung Berichtsheft: {$courseTitle}";

            $alreadySent = Message::query()
                ->where('to_user', $toUserId)
                ->where('subject', $subject)
                ->exists();

            if ($alreadySent) {
                $skipped++;
                continue;
            }

            $baseUrl = rtrim((string) (Setting::getValue('api', 'base_api_url') ?: config('app.url')), '/');
            $reportBookUrl = $courseId > 0
                ? "{$baseUrl}/user/reportbook?course={$courseId}"
                : "{$baseUrl}/user/reportbook";

            $messageBody = "Der Baustein \"{$courseTitle}\" endete am {$formattedCourseEndDate}.<br>"
                . "Dein Berichtsheft ist noch nicht vollstaendig (offene Tage: {$openDays} von {$totalDays}).<br><br>"
                . "<a href='{$reportBookUrl}' target='_blank'>Berichtsheft ansehen</a>";

            $message = Message::create([
                'subject' => $subject,
                'message' => $messageBody,
                'from_user' => 1,
                'to_user' => $toUserId,
                'status' => '1',
            ]);

            // Versand im ReportBook markieren (einmalige Erinnerung)
            $reportBook->setSetting('missing_reportbook_reminder_sent_at', now()->toIso8601String());
            $reportBook->setSetting('missing_reportbook_reminder_message_id', $message->id);
            $reportBook->save();

            $created++;
        }
    }
}
