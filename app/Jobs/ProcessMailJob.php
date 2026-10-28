<?php

namespace App\Jobs;

use App\Models\Mail;
use App\Models\User;
use App\Notifications\MailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $mail;

    public function __construct(Mail $mail)
    {
        $this->mail = $mail;
    }

    public function handle(): void
    {
        $storedRecipients = $this->mail->fresh()?->recipients;
        $recipientCount = is_array($storedRecipients) ? count($storedRecipients) : 0;

        for ($index = 0; $index < $recipientCount; $index++) {
            // Erfolge pro Empfaenger festschreiben, damit ein spaeterer Abbruch sie nicht wiederholt.
            $this->mail->getConnection()->transaction(function () use ($index): void {
                $mail = $this->mail->newQuery()->whereKey($this->mail->getKey())->lockForUpdate()->first();
                $recipients = $mail && is_array($mail->recipients) ? $mail->recipients : [];
                if (! isset($recipients[$index]) || ! is_array($recipients[$index])) {
                    return;
                }

                $type = is_bool($mail->type) ? ($mail->type ? 'both' : 'message') : strtolower((string) $mail->type);
                $sendMessage = in_array($type, ['message', 'both'], true);
                $sendMail = in_array($type, ['mail', 'both'], true);

                if (empty($recipients[$index]['status'])) {
                    $userIds = collect($recipients)->pluck('user_id')->filter()->unique()->all();
                    $users = $userIds ? User::query()->whereKey($userIds)->get()->keyBy('id') : collect();
                    [$doneUsers, $doneEmails] = $this->completedChannels($recipients, $users);
                    $recipient = $recipients[$index];
                    $userId = (int) ($recipient['user_id'] ?? 0);
                    $user = $users->get($userId);
                    $email = $this->recipientEmail($recipient, $user);

                    if (($userId > 0 && ! $user) || (! $user && ! $sendMail)) {
                        Log::warning('Mail-Empfaenger konnte nicht verarbeitet werden.', [
                            'mail_id' => $mail->id, 'recipient_index' => $index,
                        ]);
                    } else {
                        $messageDone = ! $sendMessage || ! empty($recipient['message_status']) || isset($doneUsers[$userId]);
                        if ($sendMessage && $user && ! $messageDone) {
                            try {
                                $mail->getConnection()->transaction(fn () => $user->receiveMessage(
                                    $mail->content['subject'] ?? 'Nachricht',
                                    $mail->content['body'] ?? '',
                                    $mail->from_user_id ?? 1,
                                    $mail->files
                                ));
                                $messageDone = true;
                            } catch (\Throwable $error) {
                                if ($error instanceof \PDOException) {
                                    throw $error;
                                }
                                $this->logChannelFailure($mail, $index, 'message', $error);
                            }
                        }

                        $mailDone = ! $sendMail || ! empty($recipient['mail_status']) || isset($doneEmails[$email]);
                        if ($sendMail && ! $mailDone && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            try {
                                // Dieser Job laeuft bereits in der Queue; keine zweite, wiederholbare Versandqueue.
                                if ($user) {
                                    $user->notifyNow(new MailNotification($mail));
                                } else {
                                    Notification::route('mail', $email)->notifyNow(new MailNotification($mail));
                                }
                                $mailDone = true;
                            } catch (\Throwable $error) {
                                if ($error instanceof \PDOException) {
                                    throw $error;
                                }
                                $this->logChannelFailure($mail, $index, 'mail', $error);
                            }
                        }

                        $recipient['message_status'] = $sendMessage && $user && $messageDone;
                        $recipient['mail_status'] = $sendMail && $mailDone;
                        if ($recipient['mail_status']) {
                            $recipient['mail_address'] ??= $email;
                        }
                        $recipient['status'] = ($sendMessage || $sendMail)
                            && (! $sendMessage || ! $user || $messageDone) && $mailDone;
                        $recipients[$index] = $recipient;
                    }
                }

                $mail->update([
                    'recipients' => $recipients,
                    'status' => $recipients !== [] && collect($recipients)->every(fn ($recipient) => ! empty($recipient['status'])),
                ]);
            });
        }
    }

    /** @return array{array<int, true>, array<string, true>} */
    private function completedChannels(array $recipients, Collection $users): array
    {
        $doneUsers = [];
        $doneEmails = [];
        foreach ($recipients as $recipient) {
            if (! is_array($recipient)) {
                continue;
            }
            $userId = (int) ($recipient['user_id'] ?? 0);
            if ($userId > 0 && (! empty($recipient['status']) || ! empty($recipient['message_status']))) {
                $doneUsers[$userId] = true;
            }
            if (! empty($recipient['status']) || ! empty($recipient['mail_status'])) {
                $email = $recipient['mail_address'] ?? $this->recipientEmail($recipient, $users->get($userId));
                $email = strtolower(trim((string) $email));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $doneEmails[$email] = true;
                }
            }
        }

        return [$doneUsers, $doneEmails];
    }

    private function recipientEmail(array $recipient, ?User $user): string
    {
        return strtolower(trim((string) ($user ? $user->email : ($recipient['email'] ?? ''))));
    }

    private function logChannelFailure(Mail $mail, int $index, string $channel, \Throwable $error): void
    {
        Log::error('Fehler beim Verarbeiten eines Mail-Kanals.', [
            'mail_id' => $mail->id, 'recipient_index' => $index, 'channel' => $channel, 'error_class' => $error::class,
        ]);
    }
}
