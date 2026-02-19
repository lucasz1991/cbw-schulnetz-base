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
        $type = strtolower((string) $this->mail->type);
        $sendMessageTo = $type !== 'mail';
        $sendMailTo = $type !== 'message';
        $files = $this->mail->files ?? [];

        foreach ($recipients as &$recipient) {
            try {
                $userId = (int) ($recipient['user_id'] ?? 0);
                $email = (string) ($recipient['email'] ?? '');
                $recipient['status'] = false;

                // Bei User-Empfaengern je nach Typ interne Nachricht und/oder E-Mail senden.
                if ($userId > 0) {
                    $user = User::find($userId);
                    if (! $user) {
                        Log::warning("Benutzer mit ID {$userId} nicht gefunden.");
                        continue;
                    }

                    $didProcess = false;

                    if ($sendMessageTo) {
                        $user->receiveMessage(
                            $this->mail->content['subject'] ?? 'Nachricht',
                            $this->mail->content['body'] ?? '',
                            $this->mail->from_user_id ?? 1,
                            $files
                        );
                        $didProcess = true;
                    }

                    if ($sendMailTo) {
                        $user->notify(new MailNotification($this->mail));
                        $didProcess = true;
                    }

                    $recipient['status'] = $didProcess;
                    continue;
                }

                // Externer Empfaenger ohne User: nur E-Mail
                if ($sendMailTo && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Notification::route('mail', $email)->notify(new MailNotification($this->mail));
                    $recipient['status'] = true;
                    continue;
                }

                Log::warning('Empfaenger konnte fuer den gewaehlten Mail-Typ nicht verarbeitet werden.', [
                    'recipient' => $recipient,
                    'mail_id' => $this->mail->id,
                    'type' => $this->mail->type,
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
