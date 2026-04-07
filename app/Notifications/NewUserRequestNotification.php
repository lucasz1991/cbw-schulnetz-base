<?php

namespace App\Notifications;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

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
        [$view, $filename] = $this->resolvePdfViewAndName();

        $mail = (new MailMessage)
            ->subject($this->getReadableTypeSubject())
            ->greeting('Hallo CBW Admin-Team,')
            ->line('Ein neuer Antrag wurde eingereicht.')
            ->line('Typ: ' . $this->getReadableType())
            ->line('Teilnehmer: ' . ($this->request->user?->person?->full_name ?? $this->request->user?->name ?? 'Unbekannt'));

        $data = [
            'request' => $this->request,
            'user' => $this->request->user ?? null,
            'course' => $this->request->course ?? null,
        ];

        $mainPdfAttached = false;

        // Nur bei gueltiger View erzeugen; sonst Queue nicht scheitern lassen.
        if (is_string($view) && $view !== '' && View::exists($view)) {
            try {
                $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
                $mail->attachData($pdf->output(), $filename, ['mime' => 'application/pdf']);
                $mainPdfAttached = true;
            } catch (\Throwable $e) {
                Log::error('PDF konnte fuer NewUserRequestNotification nicht generiert werden.', [
                    'user_request_id' => $this->request->id ?? null,
                    'type' => $this->request->type ?? null,
                    'view' => $view,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('Keine gueltige PDF-View fuer NewUserRequestNotification gefunden.', [
                'user_request_id' => $this->request->id ?? null,
                'type' => $this->request->type ?? null,
                'view' => $view,
            ]);
        }

        if ($mainPdfAttached) {
            $mail->line('Der Antrag ist als PDF im Anhang.');
        } else {
            $mail->line('Fuer diesen Antrag wurde kein automatisch generiertes PDF angehaengt.');
        }

        $disk = 'private';

        foreach ($this->request->files as $file) {
            if (! $file->path) {
                continue;
            }

            if (! Storage::disk($disk)->exists($file->path)) {
                continue;
            }

            $attachName = $file->name_with_extension ?? $file->name ?? basename($file->path);

            $mime = $file->mime_type
                ?: (Storage::disk($disk)->mimeType($file->path) ?: 'application/octet-stream');

            $mail->attach(
                Storage::disk($disk)->path($file->path),
                [
                    'as' => $attachName,
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
            'external_exam', 'external_makeup' => [
                'pdf.requests.external-exam',
                'Externe_Pruefung_Anmeldung.pdf',
            ],
            default => [
                null,
                'Antrag.pdf',
            ],
        };
    }

    protected function getReadableType(): string
    {
        return match ($this->request->type ?? '') {
            'absence' => 'Fehlzeitmeldung',
            'makeup' => 'Anmeldung Nachpruefung',
            'external_exam', 'external_makeup' => 'Anmeldung Externe Pruefung',
            default => 'Antrag',
        };
    }

    protected function getReadableTypeSubject(): string
    {
        return match ($this->request->type ?? '') {
            'absence' => 'Entschuldigung von Fehlzeiten: ' . ($this->request->user?->person?->full_name ?? $this->request->user?->name ?? 'Unbekannt'),
            'makeup' => 'Antrag auf Nachpruefung: ' . ($this->request->user?->person?->full_name ?? $this->request->user?->name ?? 'Unbekannt'),
            'external_exam', 'external_makeup' => 'Antrag auf Externe Pruefung: ' . ($this->request->user?->person?->full_name ?? $this->request->user?->name ?? 'Unbekannt'),
            default => 'Antrag',
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->request->type ?? 'unknown',
            'user_id' => $this->request->user_id ?? null,
        ];
    }
}
