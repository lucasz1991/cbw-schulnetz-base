<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoachingNotification extends Notification
{
    public function __construct(public array $content, public ?string $url, public bool $registration = false) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        // MailMessage uses the existing Schulnetz vendor/notifications/email and mail layout.
        $mail = (new MailMessage)->subject($this->content['subject'])->greeting('Guten Tag,');
        foreach ($this->content['lines'] as $line) $mail->line($line);
        if ($this->registration) {
            $mail->line('Für diese E-Mail-Adresse ist noch kein Schulnetz-Konto mit dem Vorgang verknüpft. Bitte registrieren Sie sich mit genau dieser Adresse. Sie erhalten anschließend eine E-Mail zum Setzen Ihres Passworts.');
        }
        if ($this->url) $mail->action($this->registration ? 'Im Schulnetz registrieren' : ($this->content['action'] ?? 'Einzelcoaching öffnen'), $this->url);
        $mail->salutation('Ihr CBW Schulnetz Team');
        // Admin keeps its other mail templates. Coaching alone uses the copied Base layout.
        if (is_file(resource_path('views/coaching-mail/notification.blade.php'))) {
            $renderer = new \Illuminate\Mail\Markdown(app('view'), ['paths' => [resource_path('views/coaching-mail')]]);
            $data = $mail->data() + ['coachingBaseUrl' => \App\Models\Setting::getValueUncached('api', 'base_api_url')];
            $mail->view(['html' => $renderer->render('coaching-mail.notification', $data),
                'text' => $renderer->renderText('coaching-mail.notification', $data)]);
        }
        return $mail;
    }
}
