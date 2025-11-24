<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;
use App\Models\CourseMaterialAcknowledgement;
use Carbon\Carbon;
use Livewire\Attributes\On;

class MaterialsAcknowledgement extends Component
{
    public Course $course;

    /**
     * Merkt sich den aktuellen Acknowledgement-Datensatz,
     * für den die Signatur gestartet wurde (für Abbruch/Completion).
     */
    public ?int $ackId = null;

    public function getAlreadyAcknowledgedProperty(): bool
    {
        $personId = Auth::user()?->person?->id;
        return $personId ? $this->course->isMaterialsAcknowledgedBy($personId) : false;
    }

    public function startAcknowledgement(): void
    {
        $user = Auth::user();
        $personId = $user?->person?->id;

        if (!$personId) {
            $this->dispatch('toast', type: 'error', message: 'Kein Teilnehmerkonto verknüpft.');
            return;
        }

        // Wenn schon bestätigt, kein neuer Flow
        if ($this->alreadyAcknowledged) {
            $this->dispatch('toast', type: 'info', message: 'Die Bereitstellung wurde bereits bestätigt.');
            return;
        }

        // Ack-Datensatz für Kurs + Person vorbereiten (falls noch nicht vorhanden)
        $ack = CourseMaterialAcknowledgement::firstOrCreate(
            [
                'course_id' => $this->course->id,
                'person_id' => $personId,
            ],
            [
                'acknowledged_at' => null,
                'meta' => [
                    'created_by_user_id'    => $user->id,
                    'created_at'            => now()->toIso8601String(),
                    'ip'                    => request()->ip(),
                    'user_agent'            => request()->userAgent(),
                ],
            ]
        );

        // aktuelle Ack-ID merken (für Abbruch/Completion)
        $this->ackId = $ack->id;

        // Generisches Signature-Modal öffnen
        $this->dispatch('openSignatureForm', [
            'fileableType' => CourseMaterialAcknowledgement::class,
            'fileableId'   => $ack->id,
            'fileType'     => 'sign_materials_ack',
            'label'        => 'Bereitstellung der Kursmaterialien bestätigen',
            'confirmText'  => 'Ich bestätige, dass ich die oben aufgeführten Kursmaterialien erhalten habe und zur Kenntnis genommen habe.',
        ]);
    }

    #[On('signatureCompleted')]
    public function handleSignatureCompleted(array $payload): void
    {
        $fileableType = data_get($payload, 'fileableType');
        $fileableId   = (int) data_get($payload, 'fileId'); // oder fileableId? je nach deinem SignatureForm-Event

        // Nur reagieren, wenn es sich um unsere Ack-Klasse handelt
        if ($fileableType !== CourseMaterialAcknowledgement::class) {
            return;
        }

        $user = Auth::user();
        $personId = $user?->person?->id;
        if (!$personId) {
            return;
        }

        // Ack über gemerkte ID bevorzugt holen
        $ackId = $this->ackId ?: data_get($payload, 'fileableId');
        if (!$ackId) {
            return;
        }

        $ack = CourseMaterialAcknowledgement::find($ackId);
        if (
            !$ack ||
            $ack->course_id !== $this->course->id ||
            $ack->person_id !== $personId
        ) {
            return;
        }

        // Teilnehmer-Bestätigung stempeln
        $ack->acknowledged_at = Carbon::now('Europe/Berlin');
        $ack->save();

        // Flow abschließen
        $this->ackId = null;

        $this->dispatch('toast', type: 'success', message: 'Bereitstellung der Kursmaterialien wurde bestätigt.');
    }

    #[On('signatureAborted')]
    public function handleSignatureAborted($payload = null): void
    {
        // Ohne Payload-Logik: nur auf die gemerkte Ack-ID reagieren
        if (!$this->ackId) {
            return;
        }

        $user = Auth::user();
        $personId = $user?->person?->id;
        if (!$personId) {
            return;
        }

        $ack = CourseMaterialAcknowledgement::find($this->ackId);
        if (
            !$ack ||
            $ack->course_id !== $this->course->id ||
            $ack->person_id !== $personId
        ) {
            return;
        }

        // Wenn schon bestätigt, nichts löschen
        if ($ack->acknowledged_at !== null) {
            $this->ackId = null;
            return;
        }

        // Falls schon Files dranhängen → löschen (File::booted kümmert sich um Storage)
        foreach ($ack->files as $file) {
            $file->delete();
        }

        // Ack-Datensatz wieder entfernen
        $ack->delete();

        // Flow zurücksetzen
        $this->ackId = null;
    }

    public function render()
    {
        return view('livewire.user.program.course.materials-acknowledgement');
    }
}
