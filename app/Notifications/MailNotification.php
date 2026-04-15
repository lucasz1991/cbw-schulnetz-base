<?php

namespace App\Notifications;

use App\Models\Mail as MailModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MailNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected MailModel $mail;

    public function __construct(MailModel $mail)
    {
        // Dank SerializesModels wird hier nur die Model-ID in die Queue geschrieben.
        $this->mail = $mail;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Content aus dem Mail-Model holen, falls als Array oder JSON gespeichert.
        $content = is_array($this->mail->content) ? $this->mail->content : [];
        $subject = $content['subject'] ?? 'Nachricht';
        $greeting = $content['header'] ?? null;
        $body = $content['body'] ?? '';
        $link = $content['link'] ?? null;

        // Sicherstellen, dass die Relation vorhanden ist.
        $this->mail->loadMissing('files');

        $message = (new MailMessage)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($subject);

        if ($greeting) {
            $message->greeting($greeting);
        }

        $message->line($body);

        if (! empty($link)) {
            $message->action('Weiter', $link);
        }

        $message->salutation('Mit freundlichen Grüßen, dein CBW Schulnetz Team');

        foreach ($this->mail->files as $file) {
            $disk = $file->disk ?? 'private';
            $path = $file->path;

            if (method_exists($message, 'attachFromStorageDisk')) {
                $message->attachFromStorageDisk($disk, $path, [
                    'as' => $file->name ?: basename($path),
                    'mime' => $file->mime_type ?: null,
                ]);
            } else {
                $absolutePath = Storage::disk($disk)->path($path);
                $message->attach($absolutePath, [
                    'as' => $file->name ?: basename($path),
                    'mime' => $file->mime_type ?: null,
                ]);
            }
        }

        return $message;
    }
}
