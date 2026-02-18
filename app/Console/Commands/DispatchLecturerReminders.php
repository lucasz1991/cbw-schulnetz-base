<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Mail;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchLecturerReminders extends Command
{
    protected $signature = 'courses:dispatch-lecturer-reminders {--dry-run : Nur anzeigen, nichts senden}';

    protected $description = 'Versendet Dozenten-Erinnerungen fuer fehlende Dokumentation, Roter Faden, Anwesenheiten und Ergebnisse.';

    private const KEY_DOC_MISSING = 'lecturer_module_documentation_missing';
    private const KEY_ROTER_FADEN_MISSING = 'rote_faden_missing';
    private const KEY_ATTENDANCE_MISSING = 'attendance_proof_missing';
    private const KEY_RESULTS_MISSING = 'exam_results_missing_by_lecturer';

    public function handle(): int
    {
        $today = Carbon::today('Europe/Berlin');
        $dryRun = (bool) $this->option('dry-run');

        $enabled = [
            self::KEY_DOC_MISSING => $this->isReminderEnabled(self::KEY_DOC_MISSING),
            self::KEY_ROTER_FADEN_MISSING => $this->isReminderEnabled(self::KEY_ROTER_FADEN_MISSING),
            self::KEY_ATTENDANCE_MISSING => $this->isReminderEnabled(self::KEY_ATTENDANCE_MISSING),
            self::KEY_RESULTS_MISSING => $this->isReminderEnabled(self::KEY_RESULTS_MISSING),
        ];

        if (! in_array(true, $enabled, true)) {
            $this->info('Alle Dozenten-Reminder sind deaktiviert.');
            return Command::SUCCESS;
        }

        $sent = 0;

        if ($enabled[self::KEY_DOC_MISSING]) {
            $sent += $this->dispatchMissingDocumentation($today, $dryRun);
        }

        if ($enabled[self::KEY_ROTER_FADEN_MISSING]) {
            $sent += $this->dispatchMissingRoterFaden($today, $dryRun);
        }

        if ($enabled[self::KEY_ATTENDANCE_MISSING]) {
            $sent += $this->dispatchMissingAttendance($today, $dryRun);
        }

        if ($enabled[self::KEY_RESULTS_MISSING]) {
            $sent += $this->dispatchMissingResults($today, $dryRun);
        }

        $this->info("Fertig. Versandt: {$sent} Reminder.");
        return Command::SUCCESS;
    }

    private function dispatchMissingDocumentation(Carbon $today, bool $dryRun): int
    {
        if (! $today->isFriday()) {
            return 0;
        }

        $sent = 0;

        Course::query()
            ->with(['tutor:id,user_id,email_priv,email_cbw', 'days:id,course_id,note_status'])
            ->whereNotNull('primary_tutor_person_id')
            ->whereDate('planned_start_date', '<=', $today->toDateString())
            ->whereDate('planned_end_date', '>=', $today->toDateString())
            ->chunkById(100, function ($courses) use ($today, $dryRun, &$sent) {
                foreach ($courses as $course) {
                    $missingDays = $course->days->filter(fn ($day) => (int) $day->note_status !== 2)->count();
                    if ($missingDays <= 0) {
                        continue;
                    }

                    $sent += $this->sendReminder(
                        $course,
                        self::KEY_DOC_MISSING,
                        'Erinnerung: Fehlende Dozenten-Dokumentation',
                        [
                            "Im Baustein \"{$course->title}\" fehlen noch {$missingDays} Tageseinträge in der Dozenten-Dokumentation.",
                            'Bitte vervollständigen Sie die Dokumentation zeitnah.',
                        ],
                        $today,
                        $dryRun,
                        everyDays: null,
                        maxCount: 2,
                        once: false
                    );
                }
            });

        return $sent;
    }

    private function dispatchMissingRoterFaden(Carbon $today, bool $dryRun): int
    {
        if (! $today->isTuesday()) {
            return 0;
        }

        $sent = 0;

        Course::query()
            ->with(['tutor:id,user_id,email_priv,email_cbw', 'days:id,course_id,date', 'files:id,fileable_id,fileable_type,type'])
            ->whereNotNull('primary_tutor_person_id')
            ->whereDate('planned_start_date', '<=', $today->toDateString())
            ->chunkById(100, function ($courses) use ($today, $dryRun, &$sent) {
                foreach ($courses as $course) {
                    if ($course->files->contains(fn ($file) => $file->type === 'roter_faden')) {
                        continue;
                    }

                    $firstDay = $course->days
                        ->pluck('date')
                        ->filter()
                        ->sort()
                        ->first();

                    $startDate = $firstDay ? Carbon::parse($firstDay) : Carbon::parse($course->planned_start_date);
                    $weekStart = $startDate->copy()->startOfWeek(Carbon::MONDAY);
                    $weekEnd = $startDate->copy()->endOfWeek(Carbon::SUNDAY);

                    if (! $today->betweenIncluded($weekStart, $weekEnd)) {
                        continue;
                    }

                    $sent += $this->sendReminder(
                        $course,
                        self::KEY_ROTER_FADEN_MISSING,
                        'Erinnerung: Roter Faden fehlt',
                        [
                            "Zum Baustein \"{$course->title}\" ist noch kein Roter Faden hinterlegt.",
                            'Bitte hinterlegen Sie den Roten Faden umgehend.',
                        ],
                        $today,
                        $dryRun,
                        everyDays: null,
                        maxCount: null,
                        once: true
                    );
                }
            });

        return $sent;
    }

    private function dispatchMissingAttendance(Carbon $today, bool $dryRun): int
    {
        $sent = 0;

        Course::query()
            ->with(['tutor:id,user_id,email_priv,email_cbw', 'days:id,course_id,date,attendance_data'])
            ->whereNotNull('primary_tutor_person_id')
            ->whereDate('planned_start_date', '<=', $today->toDateString())
            ->whereDate('planned_end_date', '>=', $today->toDateString())
            ->chunkById(100, function ($courses) use ($today, $dryRun, &$sent) {
                foreach ($courses as $course) {
                    $hasMissingAttendance = $course->days
                        ->filter(fn ($day) => $day->date && Carbon::parse($day->date)->lte($today))
                        ->contains(fn ($day) => ! $day->isAttendanceCompletelyRecorded());

                    if (! $hasMissingAttendance) {
                        continue;
                    }

                    $sent += $this->sendReminder(
                        $course,
                        self::KEY_ATTENDANCE_MISSING,
                        'Dringende Erinnerung: Anwesenheitsnachweis unvollständig',
                        [
                            "Im Baustein \"{$course->title}\" fehlen Anwesenheitseinträge (anwesend/fehlend).",
                            'Bitte vervollständigen Sie die Teilnehmer-Anwesenheiten schnellstmöglich.',
                        ],
                        $today,
                        $dryRun,
                        everyDays: 2,
                        maxCount: null,
                        once: false
                    );
                }
            });

        return $sent;
    }

    private function dispatchMissingResults(Carbon $today, bool $dryRun): int
    {
        if (! $today->isMonday()) {
            return 0;
        }

        $sent = 0;

        Course::query()
            ->with(['tutor:id,user_id,email_priv,email_cbw'])
            ->whereNotNull('primary_tutor_person_id')
            ->whereDate('planned_end_date', '<', $today->toDateString())
            ->chunkById(100, function ($courses) use ($today, $dryRun, &$sent) {
                foreach ($courses as $course) {
                    $endDate = Carbon::parse($course->planned_end_date);
                    $mondayAfterEnd = $endDate->copy()->next(Carbon::MONDAY);

                    if (! $today->isSameDay($mondayAfterEnd)) {
                        continue;
                    }

                    if ($course->hasResultsForAllParticipantsOrExternalExam()) {
                        continue;
                    }

                    $sent += $this->sendReminder(
                        $course,
                        self::KEY_RESULTS_MISSING,
                        'Erinnerung: Prüfungsergebnisse fehlen',
                        [
                            "Für den Baustein \"{$course->title}\" wurden noch nicht alle Prüfungsergebnisse eingetragen.",
                            'Bitte erfassen Sie die ausstehenden Ergebnisse.',
                        ],
                        $today,
                        $dryRun,
                        everyDays: null,
                        maxCount: null,
                        once: true
                    );
                }
            });

        return $sent;
    }

    /**
     * @param array<int, string> $lines
     */
    private function sendReminder(
        Course $course,
        string $type,
        string $subject,
        array $lines,
        Carbon $today,
        bool $dryRun,
        ?int $everyDays,
        ?int $maxCount,
        bool $once
    ): int {
        $state = $this->getReminderState($course, $type);
        $count = (int) ($state['count'] ?? 0);
        $lastSentAt = ! empty($state['last_sent_at']) ? Carbon::parse($state['last_sent_at']) : null;

        if ($once && ! empty($state['sent_once_at'])) {
            return 0;
        }

        if ($maxCount !== null && $count >= $maxCount) {
            return 0;
        }

        if ($everyDays !== null && $lastSentAt && $today->diffInDays($lastSentAt) < $everyDays) {
            return 0;
        }

        $recipient = $this->resolveRecipient($course);
        if (empty($recipient['emails']) && empty($recipient['user_id'])) {
            return 0;
        }

        if ($dryRun) {
            $this->line("[dry-run] {$subject} | course_id={$course->id}");
            return 1;
        }

        $courseUrl = $this->buildTutorCourseUrl($course->id);
        $recipientRows = [];

        if (! empty($recipient['user_id'])) {
            $recipientRows[] = [
                'user_id' => (int) $recipient['user_id'],
                'email' => null,
                'status' => false,
            ];
        }

        foreach ($recipient['emails'] as $email) {
            $recipientRows[] = [
                'user_id' => null,
                'email' => $email,
                'status' => false,
            ];
        }

        $recipientRows = collect($recipientRows)
            ->unique(fn ($r) => ($r['user_id'] ? 'u:' . $r['user_id'] : 'e:' . strtolower((string) $r['email'])))
            ->values()
            ->all();

        Mail::create([
            'type' => 'mail',
            'status' => false,
            'content' => [
                'subject' => $subject,
                'header' => 'Hallo,',
                'body' => implode("\n", $lines),
                'link' => $courseUrl,
            ],
            'recipients' => $recipientRows,
        ]);

        $state['count'] = $count + 1;
        $state['last_sent_at'] = now()->toIso8601String();
        if ($once) {
            $state['sent_once_at'] = now()->toIso8601String();
        }
        $course->setSetting("reminder_state_{$type}", $state);
        $course->save();

        return 1;
    }

    /**
     * @return array{user_id:int|null,emails:array<int,string>}
     */
    private function resolveRecipient(Course $course): array
    {
        $person = $course->tutor;
        if (! $person) {
            return ['user_id' => null, 'emails' => []];
        }

        $emails = collect([
            $person->email_cbw,
            $person->email_priv,
        ])->filter(function ($mail) {
            return is_string($mail) && filter_var($mail, FILTER_VALIDATE_EMAIL);
        })->unique()->values()->all();

        return [
            'user_id' => $person->user_id ? (int) $person->user_id : null,
            'emails' => $emails,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getReminderState(Course $course, string $type): array
    {
        $state = $course->getSetting("reminder_state_{$type}", []);
        return is_array($state) ? $state : [];
    }

    private function isReminderEnabled(string $key): bool
    {
        $rawValue = Setting::getValueUncached('mails', $key);
        $decoded = json_decode((string) $rawValue, true);
        $value = $decoded ?? $rawValue;

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function buildTutorCourseUrl(int $courseId): string
    {
        $baseUrl = rtrim((string) (Setting::getValue('api', 'base_api_url') ?: config('app.url')), '/');
        return "{$baseUrl}/tutor/tutor-course/{$courseId}";
    }
}
