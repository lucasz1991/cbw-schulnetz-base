<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class NewUserRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // PDF-View & Dateiname ermitteln
        [$view, $filename] = $this->resolvePdfViewAndName();

        $data = [
            'request' => $this->request,
            'user'    => $this->request->user ?? null,
            'course'  => $this->request->course ?? null,
        ];

        // PDF generieren
        $pdf     = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
        $pdfData = $pdf->output();

        $mail = (new MailMessage)
            ->subject($this->getReadableTypeSubject())
            ->greeting('Hallo CBW Admin-Team,')
            ->line('Ein neuer Antrag wurde eingereicht.')
            ->line('Typ: ' . $this->getReadableType())
            ->line('Teilnehmer: ' . ($this->request->user?->person?->full_name ?? $this->request->user?->name ?? 'Unbekannt'))
            ->line('Der Antrag ist als PDF im Anhang.');

        // Haupt-PDF anhängen
        $mail->attachData($pdfData, $filename, ['mime' => 'application/pdf']);

        // ---------------------------------------------------------
        // Zusätzliche Dateien des UserRequest als Anhänge
        // ---------------------------------------------------------
        // Relationen: UserRequest::files() -> morphMany(File::class, 'fileable')
        $disk = 'private';

        foreach ($this->request->files as $file) {
            if (! $file->path) {
                continue;
            }

            if (! Storage::disk($disk)->exists($file->path)) {
                continue;
            }

            // schöner Dateiname (inkl. Extension, über dein Accessor)
            $attachName = $file->name_with_extension ?? $file->name ?? basename($file->path);

            // MIME-Typ bestimmen
            $mime = $file->mime_type
                ?: (Storage::disk($disk)->mimeType($file->path) ?: 'application/octet-stream');

            // Variante mit lokalem Pfad
            $mail->attach(
                Storage::disk($disk)->path($file->path),
                [
                    'as'   => $attachName,
                    'mime' => $mime,
                ]
            );
        }

        return $mail;
    }

    protected function resolvePdfViewAndName(): array
    {
        $type = $this->request->type ?? 'unknown';

        return match ($type) {
            'absence' => [
                'pdf.requests.absence',
                'Fehlzeitmeldung.pdf',
            ],
            'makeup' => [
                'pdf.requests.exam-registration',
                'Nachpruefung_Anmeldung.pdf',
            ],
            'external_exam' => [
                'pdf.requests.external-exam',
                'Externe_Pruefung_Anmeldung.pdf',
            ],
            default => [
                'none',
                'none.pdf',
            ],
        };
    }

    protected function getReadableType(): string
    {
        return match ($this->request->type ?? '') {
            'absence'       => 'Fehlzeitmeldung',
            'makeup'        => 'Anmeldung Nachprüfung',
            'external_exam' => 'Anmeldung Externe Prüfung',
            default         => 'Antrag',
        };
    }
    protected function getReadableTypeSubject(): string
    {
        return match ($this->request->type ?? '') {
            'absence'       => 'Entschuldigung von Fehlzeiten: ' . ($this->request->user?->person?->full_name ?? $this->request->user?->name ?? 'Unbekannt'),
            'makeup'        => 'Antrag auf Nachprüfung: ' . ($this->request->user?->person?->full_name ?? $this->request->user?->name ?? 'Unbekannt'),
            'external_exam' => 'Antrag auf Externe Prüfung: ' . ($this->request->user?->person?->full_name ?? $this->request->user?->name ?? 'Unbekannt'),
            default         => 'Antrag',
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => $this->request->type ?? 'unknown',
            'user_id' => $this->request->user_id ?? null,
        ];
    }
}
