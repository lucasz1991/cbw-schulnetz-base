<?php

namespace App\Livewire\Tools\Signatures;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\On;


class SignatureForm extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public ?string $fileableType = null;
    public ?int    $fileableId   = null;

    /** Wird auf "sign_*" normalisiert */
    public ?string $fileType = 'sign_generic';

    public ?string $label       = 'Unterschrift';
    public ?string $confirmText = null;
    public ?string $contextName = null;
    public ?string $signForName = null;
    /** Canvas */
    public ?string $signatureDataUrl = null;

    /** Upload */
    public $upload;

    public ?string $errorMsg = null;
    protected ?string $createdSignaturePath = null;

    public function mount(
        ?string $fileableType = null,
        ?int $fileableId = null,
        ?string $fileType = 'sign_generic',
        ?string $label = 'Unterschrift',
        ?string $confirmText = null,
        ?string $contextName = null,
        ?string $signForName = 'Vorgang',
        bool $open = false,
    ): void {
        $this->fileableType = $fileableType;
        $this->fileableId   = $fileableId;
        $this->fileType     = $fileType;
        $this->label        = $label;
        $this->confirmText  = $confirmText;
        $this->contextName  = $contextName;
        $this->signForName  = $signForName;
        $this->open         = $open;
    }


#[On('openSignatureForm')]
public function openSignatureForm(array $payload): void
{
    if (isset($payload['fileableType'])) {
        $this->fileableType = $payload['fileableType'];
    }

    if (isset($payload['fileableId'])) {
        $this->fileableId = (int) $payload['fileableId'];
    }

    if (isset($payload['fileType'])) {
        $this->fileType = $payload['fileType'];
    }

    if (isset($payload['label'])) {
        $this->label = $payload['label'];
    }

    if (isset($payload['contextName'])) {
        $this->contextName = $payload['contextName'];
    }

    if (isset($payload['signForName'])) {
        $this->signForName = $payload['signForName'];
    }

    if (isset($payload['confirmText'])) {
        $this->confirmText = $payload['confirmText'];   // 👈 WICHTIG
    } else {
        // wenn kein eigener Text mitgegeben wurde, alten evtl. resetten,
        // damit getDefaultConfirmTextProperty wieder greift
        $this->confirmText = null;
    }

    $this->reset(['signatureDataUrl', 'upload', 'errorMsg']);

    if ($this->fileableType === \App\Models\ReportBook::class) {
        $this->resolveFileable();
    }
    $this->open = true;
}

    public function hydrate(): void
    {
        if ($this->open && $this->fileableType === \App\Models\ReportBook::class) {
            $this->resolveFileable();
        }
    }

    protected function resolveFileable(): ?Model
    {
        if (!$this->fileableType || !$this->fileableId) {
            return null;
        }

        if (!class_exists($this->fileableType)) {
            return null;
        }

        $fileable = ($this->fileableType)::find($this->fileableId);
        if ($fileable instanceof \App\Models\Course && \App\Services\Courses\CourseLecturerAccess::hasPlan($fileable)) {
            abort_unless(Auth::user() && in_array($this->fileType,['sign_course_doku_participant','course_doku_participant'],true)
                && $fileable->participants()->whereIn('persons.id',Auth::user()->persons()->pluck('persons.id'))->exists(),403);
        }
        if ($fileable instanceof \App\Models\CourseDay && ($fileable->course?->type === 'coaching'
            || \App\Services\Courses\CourseLecturerAccess::hasPlan($fileable->course))) {
            \App\Services\Coaching\Access::guardTutorCourse($fileable->course_id);
            \App\Services\Coaching\Access::guardDayWrite($fileable, false);
            abort_unless(in_array($this->fileType, ['sign_courseday_doku_tutor', 'courseday_doku_tutor'], true), 403);
            if (trim((string)$fileable->notes) === '') {
                throw \Illuminate\Validation\ValidationException::withMessages(['signatureDataUrl' => 'Bitte erst die Dokumentation speichern.']);
            }
            if (\App\Services\Courses\CourseLecturerAccess::hasPlan($fileable->course)) {
                abort_unless((int)$fileable->notes_author_user_id===(int)Auth::id() && (int)$fileable->notes_revision>0,403);
                abort_if((int)$fileable->note_status===\App\Models\CourseDay::NOTE_STATUS_COMPLETED
                    || $fileable->tutorSignatures()->exists(),409,'Diese Dokumentation ist bereits unterschrieben.');
            }
        }
        if ($fileable instanceof \App\Models\ReportBook) {
            abort_unless(\App\Support\ParticipantReportBookAccess::canAccessBook(Auth::user(), $fileable)
                && in_array($this->fileType, ['sign_reportbook_participant', 'reportbook_participant'], true), 403);
        }

        return $fileable;
    } 

    public function getDefaultConfirmTextProperty(): string
    {
        if ($this->confirmText) {
            return $this->confirmText;
        }

        // das ist der sichtbare Name, den du aus finalizeDay schickst
        $subject = $this->signForName
            ?: ($this->fileableType ? class_basename($this->fileableType) : 'diesem Eintrag');

        if ($this->contextName) {
            return "Ich bestätige, dass meine Angaben zu der <br><strong>{$subject} <br>({$this->contextName})</strong><br> vollständig und korrekt sind.";
        }

        return "Ich bestätige, dass meine Angaben zu {$subject} vollständig und korrekt sind.";
    }


    public function cancel(): void
    {
        $this->reset(['signatureDataUrl', 'upload', 'errorMsg']);
        $this->open = false;

        $this->dispatch('signatureAborted', [
            'fileableType' => $this->fileableType,
            'fileableId'   => $this->fileableId,
            'fileType'     => $this->fileType,
        ]);
    }

    public function save(): void
    {
        $this->createdSignaturePath = null;
        if ($this->fileableType === \App\Models\CourseDay::class) {
            $day=\App\Models\CourseDay::findOrFail($this->fileableId);
            if (\App\Services\Courses\CourseLecturerAccess::hasPlan($day->course)) {
                try {
                    \Illuminate\Support\Facades\DB::transaction(function(){
                        $courseId=\App\Models\CourseDay::whereKey($this->fileableId)->value('course_id');
                        \App\Models\Course::whereKey($courseId)->lockForUpdate()->firstOrFail();
                        \App\Models\CourseDay::whereKey($this->fileableId)->lockForUpdate()->firstOrFail();
                        $this->persistSignature();
                    },1);
                } catch (\Throwable $e) {
                    if($this->createdSignaturePath) Storage::disk('private')->delete($this->createdSignaturePath);
                    throw $e;
                }
                return;
            }
        }
        if ($this->fileableType !== \App\Models\ReportBook::class) {
            $this->persistSignature();
            return;
        }
        $book = $this->resolveFileable();
        if (! $book) {
            $this->errorMsg = 'Zugehöriger Datensatz wurde nicht gefunden.';
            return;
        }
        try {
            // File IO cannot be rolled back/retried by the database. Use one attempt
            // and remove only this attempt's uniquely named new file on failure.
            \App\Support\ParticipantReportBookAccess::withWriteLock(Auth::user(), $book,
                fn () => $this->persistSignature(), 1);
        } catch (\Throwable $e) {
            if ($this->createdSignaturePath) {
                Storage::disk('private')->delete($this->createdSignaturePath);
            }
            throw $e;
        } finally {
            $this->createdSignaturePath = null;
        }
    }

    private function persistSignature(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->errorMsg = 'Nicht eingeloggt.';
            return;
        }

        if (!$this->fileableType || !$this->fileableId) {
            $this->errorMsg = 'Signatur-Kontext fehlt.';
            return;
        }

        $fileable = $this->resolveFileable();
        if (!$fileable) {
            $this->errorMsg = 'Zugehöriger Datensatz wurde nicht gefunden.';
            return;
        }

        $disk = 'private';
        $slug = Str::kebab(class_basename($this->fileableType));
        $dir  = "signatures/{$slug}/{$this->fileableId}";

        /** wir füllen später */
        $mimeType = null;
        $size = null;
        $path = null;

        /* ---------------------------------------------------------
         | 1) Upload hat Vorrang
         --------------------------------------------------------- */
        if ($this->upload) {
            $this->validate([
                'upload' => 'image|max:4096',
            ]);

            $ext      = $this->upload->getClientOriginalExtension() ?: 'png';
            $mimeType = $this->upload->getMimeType() ?: 'image/png';
            $size     = $this->upload->getSize() ?: 0;

            $filename = 'sig_upload_' . $user->id . '_' . Str::uuid() . '.' . $ext;
            $this->createdSignaturePath = $dir . '/' . $filename;
            $path = $this->upload->storeAs($dir, $filename, $disk);
            if (! $path) {
                throw new \RuntimeException('Die Unterschrift konnte nicht gespeichert werden.');
            }
            $this->createdSignaturePath = $path;
        }
        /* ---------------------------------------------------------
         | 2) Canvas-Daten
         --------------------------------------------------------- */
        elseif ($this->signatureDataUrl && str_starts_with($this->signatureDataUrl, 'data:image')) {

            $parts = explode(',', $this->signatureDataUrl, 2);
            $png   = base64_decode($parts[1] ?? '', true);

            if ($png === false || strlen($png) < 200) {
                $this->errorMsg = 'Ungültige oder leere Unterschrift.';
                return;
            }

            $mimeType = 'image/png';
            $size = strlen($png);
            $filename = 'sig_draw_' . $user->id . '_' . Str::uuid() . '.png';
            $path = $dir . '/' . $filename;

            $this->createdSignaturePath = $path;
            if (! Storage::disk($disk)->put($path, $png)) {
                throw new \RuntimeException('Die Unterschrift konnte nicht gespeichert werden.');
            }
        }
        else {
            $this->errorMsg = 'Bitte unterschreiben oder ein Bild hochladen.';
            return;
        }

        /* ---------------------------------------------------------
         | type normalisieren: sign_*
         --------------------------------------------------------- */
        if (!$this->fileType) {
            $this->fileType = 'sign_generic';
        } elseif (!Str::startsWith($this->fileType, 'sign_')) {
            $this->fileType = 'sign_' . ltrim($this->fileType, '_');
        }

        /* ---------------------------------------------------------
         | File-Modell speichern
         --------------------------------------------------------- */
        $evidence=[];
        if ($fileable instanceof \App\Models\CourseDay && \App\Services\Courses\CourseLecturerAccess::hasPlan($fileable->course)) {
            $assignment=\App\Services\Courses\CourseLecturerAccess::currentAssignment($fileable->course,$fileable->date);
            $evidence=['lecturer_person_id'=>$assignment['person_id']??null,'lecturer_assignment_id'=>$assignment['assignment_id']??null,
                'lecturer_contract_id'=>$assignment['contract_id']??null,'lecturer_plan_revision'=>$fileable->course->lecturer_plan_revision,
                'document_revision'=>(int)$fileable->notes_revision];
        }
        $file = $fileable->files()->create(array_merge([
            'user_id'   => $user->id,
            'name'      => $this->label,
            'path'      => $path,
            'disk'      => $disk,
            'type'      => $this->fileType,
            'mime_type' => $mimeType,
            'size'      => $size,
            'checksum'  => hash('sha256', $path . '|' . $size),
        ],$evidence));

        $this->reset(['signatureDataUrl', 'upload', 'errorMsg', 'open']);

        $this->dispatch('signatureCompleted', [
            'fileableType' => $this->fileableType,
            'fileableId'   => $this->fileableId,
            'fileType'     => $this->fileType,
            'fileId'       => $file->id,
        ]);
    }

    public function render()
    {
        return view('livewire.tools.signatures.signature-form');
    }
}
