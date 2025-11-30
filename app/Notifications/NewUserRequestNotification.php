<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;

class NewUserRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $request; // Der Antrag (Fehlzeit, Prüfung etc.)

    /**
     * Create a new notification instance.
     */
    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the PDF and mail.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Welches PDF sollen wir verwenden?
        [$view, $filename] = $this->resolvePdfViewAndName();

        // Daten für das PDF
        $data = [
            'request' => $this->request,
            'user'    => $this->request->user ?? null,
            'course'  => $this->request->course ?? null,
        ];

        // PDF generieren
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
        $pdfData = $pdf->output();

        return (new MailMessage)
            ->subject('Neuer Antrag eines Teilnehmers')
            ->greeting('Hallo Admin-Team,')
            ->line('Ein neuer Antrag wurde eingereicht.')
            ->line('Typ: ' . $this->getReadableType())
            ->line('Teilnehmer: ' . ($this->request->user?->person?->full_name ?? $this->request->user?->name ?? 'Unbekannt'))
            ->line('Der Antrag ist als PDF im Anhang.')
            ->attachData($pdfData, $filename, ['mime' => 'application/pdf']);
    }

    /**
     * Resolve which PDF view to use based on request type.
     */
    protected function resolvePdfViewAndName(): array
    {
        $type = $this->request->type ?? 'unknown';

        return match ($type) {
            'absence' => [
                'pdf.requests.absence',
                'Fehlzeitmeldung.pdf',
            ],
            'exam' => [
                'pdf.requests.exam-registration',
                'Nachpruefung_Anmeldung.pdf',
            ],
            'external_exam' => [
                'pdf.requests.external-exam',
                'Externe_Pruefung_Anmeldung.pdf',
            ],
            default => [
                'pdf.requests.generic',
                'Antrag.pdf',
            ],
        };
    }

    /**
     * Human readable type name.
     */
    protected function getReadableType(): string
    {
        return match ($this->request->type ?? '') {
            'absence'       => 'Fehlzeitmeldung',
            'exam'          => 'Anmeldung Nachprüfung',
            'external_exam' => 'Anmeldung Externe Prüfung',
            default         => 'Antrag',
        };
    }

    /**
     * Optional: Database array representation.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'    => $this->request->type ?? 'unknown',
            'user_id' => $this->request->user_id ?? null,
        ];
    }
}
