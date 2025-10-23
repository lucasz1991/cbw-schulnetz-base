<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Course;
use App\Models\CourseMaterialAcknowledgement;
use Carbon\Carbon;

class MaterialsAcknowledgement extends Component
{
    public Course $course;
    public bool $open = false;
    public ?string $signatureDataUrl = null;
    public ?string $errorMsg = null;

    public function getAlreadyAcknowledgedProperty(): bool
    {
        $personId = Auth::user()?->person?->id;
        return $personId ? $this->course->isMaterialsAcknowledgedBy($personId) : false;
    }

    public function save(): void
    {
        $user = Auth::user();
        $personId = $user?->person?->id;

        if (!$personId) {
            $this->errorMsg = 'Kein Teilnehmerkonto verknüpft.';
            return;
        }

        if (!$this->signatureDataUrl || !str_starts_with($this->signatureDataUrl, 'data:image/png;base64,')) {
            $this->errorMsg = 'Bitte unterschreiben Sie im Feld.';
            return;
        }

        $png = base64_decode(explode(',', $this->signatureDataUrl, 2)[1]);
        if ($png === false || strlen($png) < 200) {
            $this->errorMsg = 'Unterschrift ungültig.';
            return;
        }

        $disk = 'private';
        $dir  = "courses/{$this->course->id}/materials_ack";
        $filename = 'sig_'.$personId.'_'.time().'.png';
        $path = $dir.'/'.$filename;
        Storage::disk($disk)->put($path, $png);

        CourseMaterialAcknowledgement::create([
            'course_id'       => $this->course->id,
            'person_id'       => $personId,
            'acknowledged_at' => Carbon::now('Europe/Berlin'),
            'signature_path'  => $path,
            'signature_hash'  => hash('sha256', $png),
        ]);

        $this->reset(['signatureDataUrl','open']);
        $this->dispatch('toast', type:'success', message:'Bereitstellung bestätigt.');
    }

    public function render()
    {
        return view('livewire.user.program.course.materials-acknowledgement');
    }
}
