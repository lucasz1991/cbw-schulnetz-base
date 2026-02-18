<?php

namespace App\Jobs;

use App\Models\Mail;
use App\Notifications\MailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

class ProcessMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $mail;

    /**
     * Create a new job instance.
     */
    public function __construct(Mail $mail)
    {
        $this->mail = $mail;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $recipients = is_array($this->mail->recipients) ? $this->mail->recipients : [];
        $sendMailTo = $this->mail->type == 'message' ? false : true;
        $files = $this->mail->files ?? [];

        foreach ($recipients as &$recipient) {
            try {
                $userId = (int) ($recipient['user_id'] ?? 0);
                $email = (string) ($recipient['email'] ?? '');
                $recipient['status'] = false;

                // User-Empfaenger: immer interne Message, optional E-Mail
                if ($userId > 0) {
                    $user = User::find($userId);
                    if (! $user) {
                        Log::warning("Benutzer mit ID {$userId} nicht gefunden.");
                        continue;
                    }

                    $user->receiveMessage(
                        $this->mail->content['subject'] ?? 'Nachricht',
                        $this->mail->content['body'] ?? '',
                        $this->mail->from_user_id ?? 1,
                        $files
                    );

                    if ($sendMailTo) {
                        $user->notify(new MailNotification($this->mail));
                    }

                    $recipient['status'] = true;
                    continue;
                }

                // Externer Empfaenger ohne User: nur E-Mail
                if ($sendMailTo && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Notification::route('mail', $email)->notify(new MailNotification($this->mail));
                    $recipient['status'] = true;
                    continue;
                }

                Log::warning('Empfaenger ohne gueltigen user_id/email in ProcessMailJob.', [
                    'recipient' => $recipient,
                    'mail_id' => $this->mail->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Fehler beim Senden der Mail.', [
                    'mail_id' => $this->mail->id,
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);
                $recipient['status'] = false;
            }
        }

        $this->mail->update([
            'recipients' => $recipients,
            'status' => collect($recipients)->every(fn ($r) => (bool) ($r['status'] ?? false)),
        ]);
    }
}
