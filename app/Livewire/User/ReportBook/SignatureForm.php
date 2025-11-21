<?php

namespace App\Livewire\User\ReportBook;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\ReportBook;
use App\Models\File;

class SignatureForm extends Component
{
    public bool $open = false;

    public ?int $reportBookId = null;
    public ?int $courseId = null;
    public ?int $entryId = null;
    public ?string $courseName = null;

    public ?string $signatureDataUrl = null;
    public ?string $errorMsg = null;

    // NEU: Props aus dem Parent übernehmen
    public function mount(
        ?int $reportBookId = null,
        ?int $courseId = null,
        ?string $courseName = null,
        ?int $entryId = null,
        bool $open = false,
    ): void {
        $this->reportBookId = $reportBookId;
        $this->courseId     = $courseId;
        $this->courseName   = $courseName;
        $this->entryId      = $entryId;
        $this->open         = $open;
    }

    public function cancel(): void
    {
        // Reset alles, was zum geöffneten Dialog gehört
        $this->reset(['signatureDataUrl', 'errorMsg']);

        // Modal schließen
        $this->open = false;

        // Parent informieren, falls er state zurücksetzen soll
        $this->dispatch('signatureAborted');
    }

    public function save(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->errorMsg = 'Nicht eingeloggt.';
            return;
        }

        if (!$this->reportBookId || !$this->courseId) {
            $this->errorMsg = 'Berichtsheft-Kontext fehlt.';
            return;
        }

        if (!$this->signatureDataUrl || !str_starts_with($this->signatureDataUrl, 'data:image/png;base64,')) {
            $this->errorMsg = 'Bitte unterschreiben Sie im Feld.';
            return;
        }

        $parts = explode(',', $this->signatureDataUrl, 2);
        $png   = base64_decode($parts[1] ?? '', true);

        if ($png === false || strlen($png) < 200) {
            $this->errorMsg = 'Unterschrift ungültig.';
            return;
        }

        $book = ReportBook::find($this->reportBookId);
        if (!$book) {
            $this->errorMsg = 'Berichtsheft nicht gefunden.';
            return;
        }

        // Datei speichern
        $disk = 'private';
        $dir  = "reportbooks/{$book->id}/signatures";
        $filename = 'sig_user_'.$user->id.'_'.time().'.png';
        $path = $dir.'/'.$filename;

        Storage::disk($disk)->put($path, $png);

        // File-Eintrag am Berichtsheft anhängen
        $book->files()->create([
            'name'      => 'Teilnehmer-Unterschrift',
            'path'      => $path,
            'disk'      => $disk,
            'type'      => 'participant_signature',
            'mime_type' => 'image/png',
            'size'      => strlen($png),
            // ggf. weitere Spalten deines File-Modells hier ergänzen
            'checksum'  => hash('sha256', $png), // wenn du so ein Feld hast, sonst weglassen
        ]);

        $this->reset(['signatureDataUrl', 'errorMsg', 'open']);

        $this->dispatch('signatureCompleted');
    }

    public function render()
    {
        return view('livewire.user.report-book.signature-form');
    }
}
