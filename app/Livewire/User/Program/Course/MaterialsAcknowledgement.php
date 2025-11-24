<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;
use App\Models\CourseMaterialAcknowledgement;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Storage;

class MaterialsAcknowledgement extends Component
{
    public Course $course;

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

        // Ack-Datensatz für Kurs + Person vorbereiten (falls noch nicht vorhanden)
        $ack = CourseMaterialAcknowledgement::firstOrCreate(
            [
                'course_id' => $this->course->id,
                'person_id' => $personId,
            ],
            [
                'acknowledged_at' => null,
            ]
        );

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
        $fileableId   = (int) data_get($payload, 'fileableId');

        if ($fileableType !== CourseMaterialAcknowledgement::class) {
            return;
        }

        $user = Auth::user();
        $personId = $user?->person?->id;
        if (!$personId) {
            return;
        }

        $ack = CourseMaterialAcknowledgement::find($fileableId);
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

        $this->dispatch('toast', type: 'success', message: 'Bereitstellung der Kursmaterialien wurde bestätigt.');
    }




#[On('signatureAborted')]
public function handleSignatureAborted(array $payload): void
{
    $fileableType = data_get($payload, 'fileableType');
    $fileableId   = (int) data_get($payload, 'fileableId');
    $fileType     = data_get($payload, 'fileType');

    // Nur reagieren, wenn es unsere Material-Bestätigung ist
    if (
        $fileableType !== CourseMaterialAcknowledgement::class ||
        $fileType !== 'sign_materials_ack'
    ) {
        return;
    }

    $user = Auth::user();
    $personId = $user?->person?->id;
    if (!$personId) {
        return;
    }

    $ack = CourseMaterialAcknowledgement::find($fileableId);
    if (
        !$ack ||
        $ack->course_id !== $this->course->id ||
        $ack->person_id !== $personId
    ) {
        return;
    }

    // Wenn schon bestätigt, nichts löschen
    if ($ack->acknowledged_at !== null) {
        return;
    }

    // Falls doch schon Files dranhängen sollten, mit aufräumen
    foreach ($ack->files as $file) {
        $file->delete();
    }

    // Ack-Datensatz wieder entfernen
    $ack->delete();
}


    public function render()
    {
        return view('livewire.user.program.course.materials-acknowledgement');
    }
}
