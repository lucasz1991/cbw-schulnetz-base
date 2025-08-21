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
        $recipients = $this->mail->recipients;
        $sendMailTo = $this->mail->type == 'message' ? false : true;
        $files = $this->mail->files ?? []; 

        foreach ($recipients as &$recipient) {
            try {
                // Lade den Benutzer anhand der Empfänger-ID
                $user = User::find($recipient['user_id']);
                if ($user) {
                    // Sende die Message
                    $user->receiveMessage(
                        $this->mail->content['subject'],
                        $this->mail->content['body'],
                        $this->mail->from_user_id ?? 1,
                        $files
                    );

                    if ($sendMailTo) {
                        $user->notify(new MailNotification($this->mail));
                    }

                    // Markiere den Empfänger als erfolgreich
                    $recipient['status'] = true;
                } else {
                    Log::warning("Benutzer mit ID {$recipient['user_id']} nicht gefunden.");
                }
            } catch (\Exception $e) {
                // Fehler beim Senden protokollieren
                Log::error("Fehler beim Senden der Mail an {$recipient['email']}: {$e->getMessage()}");
                $recipient['status'] = false;
            }
        }

        // Aktualisiere den Status der Empfänger und der Mail
        $this->mail->update([
            'recipients' => $recipients,
            'status' => collect($recipients)->every(fn($r) => $r['status']),
        ]);
    }
}
