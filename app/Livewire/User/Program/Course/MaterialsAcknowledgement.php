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

    /** Speichert das aktuell zu signierende Ack-Model */
    public ?CourseMaterialAcknowledgement $materialsAcknowledgement = null;


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
            $this->dispatch('toast', type:'error', message:'Kein Teilnehmerkonto verknüpft.');
            return;
        }

        if ($this->alreadyAcknowledged) {
            $this->dispatch('toast', type:'info', message:'Die Bereitstellung wurde bereits bestätigt.');
            return;
        }

        // Record erzeugen oder holen
        $ack = CourseMaterialAcknowledgement::firstOrCreate(
            [
                'course_id' => $this->course->id,
                'person_id' => $personId,
            ],
            [
                'acknowledged_at' => null,
                'meta' => [
                    'created_by_user_id'    => $user->id,
                    'created_for_person_id' => $personId,
                    'created_at'            => now()->toIso8601String(),
                    'ip'                    => request()->ip(),
                    'user_agent'            => request()->userAgent(),
                ],
            ]
        );

        // MODEL merken (statt nur ID)
        $this->materialsAcknowledgement = $ack;

        // Signature-Modal öffnen
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
        // Muss ein CourseMaterialAcknowledgement sein
        if (
            !$this->materialsAcknowledgement ||
            data_get($payload, 'fileableType') !== CourseMaterialAcknowledgement::class
        ) {
            return;
        }

        $ack = $this->materialsAcknowledgement;

        // Final stempeln
        $ack->acknowledged_at = Carbon::now('Europe/Berlin');
        $ack->save();

        // Cleanup: Modellstate zurücksetzen
        $this->materialsAcknowledgement = null;

        $this->dispatch('toast', type:'success', message:'Bereitstellung der Kursmaterialien wurde bestätigt.');
    }


    #[On('signatureAborted')]
    public function handleSignatureAborted(): void
    {
        // Wenn kein laufender Vorgang => nichts tun
        if (!$this->materialsAcknowledgement) {
            return;
        }

        $ack = $this->materialsAcknowledgement;


        // Alle Signaturdateien löschen (File::booted kümmert sich um Storage)
        foreach ($ack->files as $file) {
            $file->delete();
        }

        // Ack vollständig entfernen
        $ack->delete();

        // Reset
        $this->materialsAcknowledgement = null;
    }


    public function render()
    {
        return view('livewire.user.program.course.materials-acknowledgement');
    }
}
