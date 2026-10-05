<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Mail;
use App\Models\Setting;
use App\Notifications\MailNotification;
use App\Services\CourseRatingsPdfExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use ZipArchive;

class DispatchCourseRatingsMail extends Command
{
    protected $signature = 'course-ratings:dispatch-mail';

    protected $description = 'Versendet die Kursbewertungen der am Freitag der Vorwoche abgeschlossenen Bausteine als ZIP.';

    public function handle(): int
    {
        if (! Setting::getValueUncached('mails', 'course_ratings_mail_enabled')) {
            return self::SUCCESS;
        }

        $recipients = array_values(array_unique(array_map('trim', explode(',',
            (string) Setting::getValueUncached('mails', 'course_ratings_mail_recipients')
        ))));
        if (! $recipients || collect($recipients)->contains(fn ($email) => ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $this->error('Bitte gültige Kursbewertungen-E-Mail-Adressen in den Einstellungen hinterlegen.');

            return self::FAILURE;
        }

        $lock = Cache::lock('course-ratings:dispatch-mail', 3600);
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            // Die Admin-Einstellung startet ab Aktivierung, ohne historische Massenmails.
            $startDate = Setting::getValueUncached('mails', 'course_ratings_mail_start_date');
            if (! $startDate) {
                Setting::setValue('mails', 'course_ratings_mail_start_date', now('Europe/Berlin')->toDateString());

                return self::SUCCESS;
            }

            $date = now('Europe/Berlin')->startOfWeek(\Carbon\Carbon::MONDAY)->subDays(3)->toDateString();
            if ($date < $startDate) {
                return self::SUCCESS;
            }

            try {
                $mail = Mail::query()->where('content->course_ratings_date', $date)
                    ->where('content->course_ratings_export', true)->first();
                if ($mail?->status) {
                    return self::SUCCESS;
                }
                if (! $mail && ! Course::query()->whereDate('planned_end_date', $date)->whereHas('ratings', function ($query) {
                    $query->where('skip_course_rating', false)->orWhereNull('skip_course_rating');
                })->exists()) {
                    return self::SUCCESS;
                }

                $mail ??= $this->createArchiveMail($date, $recipients);
                if (! $mail) {
                    return self::SUCCESS;
                }
                $content = $mail->content;
                $content['link'] = URL::temporarySignedRoute('course-ratings.download', now()->addDays(30), ['mail' => $mail->id]);
                $mail->update(['content' => $content]);
                $this->sendArchiveMail($mail);

                return $mail->fresh()->status ? self::SUCCESS : self::FAILURE;
            } catch (\Throwable $e) {
                Log::error('Kursbewertungen-Sammelversand fehlgeschlagen.', ['date' => $date, 'error' => $e->getMessage()]);
                $this->error("Kursbewertungen für {$date} konnten nicht vollständig versendet werden.");

                return self::FAILURE;
            }
        } finally {
            $lock->release();
        }
    }

    private function sendArchiveMail(Mail $mail): void
    {
        $recipients = $mail->recipients;
        foreach ($recipients as &$recipient) {
            if ($recipient['status'] ?? false) {
                continue;
            }
            try {
                try {
                    Notification::route('mail', $recipient['email'])->notifyNow(new MailNotification($mail));
                } catch (\Throwable $e) {
                    Log::warning('Kursbewertungen-Anhang konnte nicht versendet werden; versuche Downloadlink.', [
                        'mail_id' => $mail->id,
                    ]);
                    // Nur diese sofortige Benachrichtigung ohne Anhang rendern; gespeicherte ZIP erhalten.
                    $linkOnlyMail = clone $mail;
                    $linkOnlyMail->setRelation('files', new \Illuminate\Database\Eloquent\Collection);
                    Notification::route('mail', $recipient['email'])->notifyNow(new MailNotification($linkOnlyMail));
                }
                $recipient['status'] = true;
            } catch (\Throwable $e) {
                $recipient['status'] = false;
                Log::error('Kursbewertungen-Mail konnte nicht versendet werden.', [
                    'mail_id' => $mail->id, 'error' => $e->getMessage(),
                ]);
            }
        }
        unset($recipient);
        $mail->update([
            'recipients' => $recipients,
            'status' => ! empty($recipients) && collect($recipients)->every(fn ($recipient) => (bool) ($recipient['status'] ?? false)),
        ]);
    }

    private function createArchiveMail(string $date, array $recipients): ?Mail
    {
        $disk = Storage::disk('private');
        if (! $disk->makeDirectory('course-ratings')) {
            throw new \RuntimeException('ZIP-Verzeichnis konnte nicht angelegt werden.');
        }
        $path = 'course-ratings/'.Str::uuid().'.zip';
        $zip = new ZipArchive;
        $zipOpen = false;
        $temporaryPdfs = [];
        $courseIds = [];
        try {
            if ($zip->open($disk->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Kursbewertungen-ZIP konnte nicht angelegt werden.');
            }
            $zipOpen = true;
            $courses = Course::query()->whereDate('planned_end_date', $date)->whereHas('ratings', function ($query) {
                $query->where('skip_course_rating', false)->orWhereNull('skip_course_rating');
            })->orderBy('id')->get();
            $exporter = app(CourseRatingsPdfExporter::class);
            foreach ($courses as $course) {
                $pdf = $exporter->generate($course);
                if (! $pdf) {
                    continue;
                }
                $temporaryPdfs[] = $pdf;
                $label = Str::slug($course->courseShortName ?: 'Baustein');
                $termin = Str::slug((string) ($course->termin_id ?: $course->klassen_id));
                if (! $zip->addFile($pdf, "{$course->id}_{$termin}_{$label}.pdf")) {
                    throw new \RuntimeException('Kursbewertungs-PDF konnte nicht in das ZIP aufgenommen werden.');
                }
                $courseIds[] = $course->id;
            }
            $closed = $zip->close();
            $zipOpen = false;
            if (! $courseIds) {
                $disk->delete($path);

                return null;
            }
            if (! $closed) {
                throw new \RuntimeException('Kursbewertungen-ZIP konnte nicht gespeichert werden.');
            }

            // Der created-Hook darf erst nach der Anhang-Zuordnung senden.
            return Mail::withoutEvents(fn () => DB::transaction(function () use ($date, $courseIds, $recipients, $path, $disk) {
                $label = \Carbon\Carbon::parse($date)->format('d.m.Y');
                $mail = Mail::create([
                    'type' => 'mail', 'status' => false,
                    'content' => [
                        'course_ratings_export' => true, 'course_ratings_date' => $date, 'course_ids' => $courseIds,
                        'subject' => "Kursbewertungen – Bausteinabschluss {$label}",
                        'header' => 'Kursbewertungen',
                        'body' => 'Im ZIP-Anhang finden Sie die Kursbewertungen der am '.$label.' abgeschlossenen Bausteine. Falls der Anhang nicht zugestellt wurde, können Sie dieselbe ZIP-Datei auch über den folgenden Link herunterladen. Der Downloadlink ist 30 Tage gültig.',
                    ],
                    'recipients' => array_map(fn ($email) => ['email' => $email, 'status' => false], $recipients),
                ]);
                $mail->files()->create([
                    'name' => "Kursbewertungen_{$date}.zip", 'path' => $path, 'disk' => 'private',
                    'mime_type' => 'application/zip', 'type' => 'zip', 'size' => $disk->size($path),
                ]);

                return $mail;
            }));
        } catch (\Throwable $e) {
            if ($zipOpen) {
                $zip->close();
            }
            $disk->delete($path);
            throw $e;
        } finally {
            foreach ($temporaryPdfs as $pdf) {
                if (is_file($pdf)) {
                    unlink($pdf);
                }
            }
        }
    }
}
