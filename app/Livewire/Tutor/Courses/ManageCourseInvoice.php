<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use App\Models\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ManageCourseInvoice extends Component
{
    use WithFileUploads;

    public Course $course;

    public bool $openInvoiceForm = false;
    public $invoiceUpload = null;
    public ?string $invoiceExpires = null;

    public bool $openPreview = false;
    public ?string $invoiceAssignmentId = null;

    public function getOwnInvoiceAssignmentsProperty(): array
    {
        $this->course->refresh();
        $ids=\App\Services\Courses\CourseLecturerAccess::personIds(Auth::user());
        return array_values(array_filter(\App\Services\Courses\CourseLecturerAccess::assignments($this->course),
            fn($row)=>in_array((int)$row['person_id'],$ids,true) && $row['starts_on']<=now('Europe/Berlin')->toDateString()));
    }

    protected function ownAssignment(): ?array
    {
        $rows=$this->ownInvoiceAssignments;
        if($this->invoiceAssignmentId!==null) {
            foreach($rows as$row) if($row['assignment_id']===$this->invoiceAssignmentId)return$row;
            abort(403);
        }
        return $rows ? $rows[count($rows)-1] : null;
    }

    protected function guardInvoice(): void
    {
        $this->course->refresh();
        if(\App\Services\Courses\CourseLecturerAccess::hasPlan($this->course)) {
            abort_unless(\App\Services\Courses\CourseLecturerAccess::canInvoice(Auth::user(),$this->course),403);
            abort_unless($this->ownAssignment(),403);
        }
    }

    protected function rules(): array
    {
        return [
            'invoiceUpload'   => 'nullable|file|mimetypes:application/pdf|max:30720',
            'invoiceExpires'  => ['nullable', 'date'],
        ];
    }

    protected function messages(): array
    {
        return [
            'invoiceUpload.file'       => 'Die ausgewählte Datei konnte nicht gelesen werden.',
            'invoiceUpload.mimetypes'  => 'Bitte lade eine PDF-Datei hoch.',
            'invoiceUpload.max'        => 'Die Datei darf maximal 30 MB groß sein.',
            'invoiceExpires.date'      => 'Bitte gib ein gültiges Datum ein.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'invoiceUpload'  => 'Rechnung (PDF)',
            'invoiceExpires' => 'Ablaufdatum',
        ];
    }

    public function mount(Course $course): void
    {
        $this->course = $course;
        \App\Services\Courses\CourseLecturerEvidence::guardView($course);
        if(\App\Services\Courses\CourseLecturerAccess::hasPlan($course)) {
            $rows=$this->ownInvoiceAssignments;
            $this->invoiceAssignmentId=$rows ? $rows[count($rows)-1]['assignment_id'] : null;
        }
    }

    public function reloadOnTabActivated(): void
    {
        $this->course->refresh();
    }

    public function getCanUploadInvoiceProperty(): bool
    {
        $this->course->refresh();
        if(\App\Services\Courses\CourseLecturerAccess::hasPlan($this->course)) {
            if(!\App\Services\Courses\CourseLecturerAccess::canInvoice(Auth::user(),$this->course))return false;
            return collect($this->invoiceRequirements)->every(fn($row)=>$row['ok']);
        }
        return $this->course->isReadyForInvoice();
    }

    public function getInvoiceFileProperty(): ?File
    {
        if(\App\Services\Courses\CourseLecturerAccess::hasPlan($this->course)) {
            if(!\App\Services\Courses\CourseLecturerAccess::canInvoice(Auth::user(),$this->course))return null;
            $this->guardInvoice();
            return $this->course->files()->where('type','invoice')->where('user_id',Auth::id())
                ->where('lecturer_assignment_id',$this->ownAssignment()['assignment_id'])->latest('id')->first();
        }
        return $this->course->files()
            ->where('type', 'invoice')
            ->latest('id')
            ->first();
    }

    public function getInvoiceRequirementsProperty(): array
    {
        $this->course->refresh();
        if(\App\Services\Courses\CourseLecturerAccess::hasPlan($this->course)) {
            $assignment=$this->ownAssignment();
            if(!$assignment)return [['ok'=>false,'label'=>'Eigener begonnener Dozenteneinsatz','detail'=>null]];
            $days=$this->course->days()->whereBetween('date',[$assignment['starts_on'],$assignment['ends_on']])->get();
            $participantIds=$this->course->participants()->pluck('persons.id')->map(fn($id)=>(int)$id)->all();
            return [
                ['ok'=>$days->isNotEmpty() && $days->every(fn($d)=>(int)$d->note_status===\App\Models\CourseDay::NOTE_STATUS_COMPLETED),
                 'label'=>'Dokumentation des eigenen Einsatzes abgeschlossen','detail'=>null],
                ['ok'=>$days->isNotEmpty() && $days->every(fn($d)=>!array_diff($participantIds,array_map('intval',array_keys($d->attendance_data['participants']??[])))),
                 'label'=>'Anwesenheit des eigenen Einsatzes vollständig','detail'=>null],
            ];
        }

        $participantIds = $this->course->participants()
            ->pluck('persons.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $days = $this->course->days()->get();
        $totalDays = $days->count();
        $completedDays = $days->where('note_status', \App\Models\CourseDay::NOTE_STATUS_COMPLETED)->count();

        $attendanceIncompleteDays = $days
            ->filter(function ($day) use ($participantIds) {
                $recordedParticipantIds = collect($day->attendance_data['participants'] ?? [])
                    ->keys()
                    ->map(fn ($id) => (int) $id)
                    ->all();

                return ! empty(array_diff($participantIds, $recordedParticipantIds));
            })
            ->map(fn ($day) => $day->date?->format('d.m.Y') ?? 'unbekannter Termin')
            ->values()
            ->all();

        $hasExternalExam = (bool) $this->course->getSetting('isExternalExam', false);

        $participantsWithResults = empty($participantIds)
            ? []
            : $this->course->results()
                ->whereIn('person_id', $participantIds)
                ->where(function ($q) {
                    $q->whereNotNull('result')
                        ->orWhere(function ($qq) {
                            $qq->whereNotNull('status')
                                ->where('status', '<>', '');
                        });
                })
                ->pluck('person_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $missingResultsCount = $hasExternalExam
            ? 0
            : count(array_diff($participantIds, $participantsWithResults));

        return [
            [
                'ok' => $this->course->hasRoterFaden(),
                'label' => 'Roter Faden hochgeladen',
                'detail' => $this->course->hasRoterFaden() ? null : 'Es fehlt eine Datei vom Typ "Roter Faden".',
            ],
            [
                'ok' => $this->course->areAllCourseDaysDocumentationCompleted(),
                'label' => 'Kursdokumentation für alle Kurstage abgeschlossen',
                'detail' => $this->buildDocumentationCompletionDetail($totalDays, $completedDays),
            ],
            [
                'ok' => $this->course->hasParticipantDocumentationSignature(),
                'label' => 'Teilnehmer-Unterschrift zur Kursdokumentation vorhanden',
                'detail' => $this->course->hasParticipantDocumentationSignature()
                    ? null 
                    : 'Die Teilnehmer-Unterschrift zur Kursdokumentation fehlt. Bitte lasse sie auf der Kursansicht von einem Teilnehmer erfassen.',
            ],
            [
                'ok' => $this->course->hasAttendanceForAllCourseDays(),
                'label' => 'Teilnehmer-Anwesenheit für alle Kurstage vollständig erfasst',
                'detail' => $this->buildAttendanceRequirementDetail($totalDays, $attendanceIncompleteDays),
            ],
            [
                'ok' => $this->course->hasResultsForAllParticipantsOrExternalExam(),
                'label' => 'Klausurergebnisse vollständig oder externe Prüfung gesetzt',
                'detail' => $this->buildResultsRequirementDetail($hasExternalExam, count($participantIds), $missingResultsCount),
            ],
        ];
    }

    public function openInvoiceDialog(): void
    {
        $this->guardInvoice();
        if (! $this->canUploadInvoice) {
            $this->dispatch('toast', type:'error', message:'Bitte zuerst Kursdaten (z. B. Roter Faden & Prüfungsergebnisse) vollständig pflegen.');
            return;
        }

        $this->openInvoiceForm = true;
    }

    public function openPreview(): void
    {
        $this->guardInvoice();
        if ($this->invoiceFile) {
            $this->openPreview = true;
        }
    }

    public function closePreview(): void
    {
        $this->openPreview = false;
    }

    public function uploadInvoice(): void
    {
        $this->course=$this->course->fresh();
        if (!\App\Services\Courses\CourseLecturerAccess::hasPlan($this->course)) {
            $this->persistInvoice();
            return;
        }
        $newPath=null;
        try {
            \Illuminate\Support\Facades\DB::transaction(function() use (&$newPath) {
                $this->course=Course::whereKey($this->course->id)->lockForUpdate()->firstOrFail();
                unset($this->invoiceFile,$this->ownInvoiceAssignments,$this->invoiceRequirements,$this->canUploadInvoice);
                $this->persistInvoice($newPath);
            },1);
        } catch (\Throwable $e) {
            if($newPath) Storage::disk('private')->delete($newPath);
            throw $e;
        }
    }

    protected function persistInvoice(?string &$newPath=null): void
    {
        $this->guardInvoice();
        $this->validate();

        if (! $this->canUploadInvoice) {
            $this->dispatch('toast', type:'error', message:'Rechnungen können erst nach vollständiger Kursdokumentation hochgeladen werden.');
            return;
        }

        if (! $this->invoiceUpload) {
            $this->dispatch('toast', type:'error', message:'Bitte eine PDF-Datei auswählen.');
            return;
        }

        $temporal=\App\Services\Courses\CourseLecturerAccess::hasPlan($this->course);
        $previous=$this->invoiceFile;
        if ($previous && !$temporal) {
            $this->deleteFileRecord($this->invoiceFile);
        }

        $disk = 'private';
        $dir  = "courses/{$this->course->id}/invoice";
        $path = $this->invoiceUpload->store($dir, $disk);
        $newPath=$path;

        $evidence=[];
        if($temporal) {
            $assignment=$this->ownAssignment();
            $evidence=['lecturer_person_id'=>$assignment['person_id'],'lecturer_assignment_id'=>$assignment['assignment_id'],
                'lecturer_contract_id'=>$assignment['contract_id']??null,'lecturer_plan_revision'=>$this->course->lecturer_plan_revision,
                'document_revision'=>(int)($previous?->document_revision??0)+1,'supersedes_file_id'=>$previous?->id];
        }
        $file = $this->course->files()->create(array_merge([
            'user_id'    => Auth::id(),
            'name'       => $this->invoiceUpload->getClientOriginalName(),
            'path'       => $path,
            'mime_type'  => 'application/pdf',
            'type'       => 'invoice',
            'size'       => $this->invoiceUpload->getSize(),
            'expires_at' => null,
        ],$evidence));

        $this->reset(['invoiceUpload', 'invoiceExpires', 'openInvoiceForm']);
        unset($this->invoiceFile);
        $this->dispatch('toast', type:'success', message:'Rechnung aktualisiert.');
        $this->dispatch('filepool:saved', model: 'invoiceUpload');
    }

    public function removeInvoice(): void
    {
        $this->guardInvoice();
        abort_if(\App\Services\Courses\CourseLecturerAccess::hasPlan($this->course),409,
            'Der Rechnungsstand bleibt erhalten. Bitte bei Bedarf eine neue Fassung hochladen.');
        if (! $this->invoiceFile) {
            return;
        }

        $this->deleteFileRecord($this->invoiceFile);
        unset($this->invoiceFile);
        $this->dispatch('toast', type:'success', message:'Rechnung entfernt.');
    }

    protected function deleteFileRecord(File $file): void
    {
        \App\Services\Courses\CourseLecturerEvidence::guardFileDelete($file);
        try {
            Storage::disk('private')->delete($file->path);
        } catch (\Throwable $e) {
            // ignore missing file
        }

        $file->delete();
    }

    protected function buildDocumentationCompletionDetail(int $totalDays, int $completedDays): ?string
    {
        if ($this->course->areAllCourseDaysDocumentationCompleted()) {
            return null;
        }

        if ($totalDays === 0) {
            return 'Es sind noch keine Kurstage vorhanden.';
        }

        if ($completedDays < $totalDays) {
            return "{$completedDays} von {$totalDays} Kurstagen sind als \"Fertig & unterschrieben\" markiert.";
        }

        return null;
    }

    protected function buildAttendanceRequirementDetail(int $totalDays, array $attendanceIncompleteDays): ?string
    {
        if ($this->course->hasAttendanceForAllCourseDays()) {
            return null;
        }

        if ($totalDays === 0) {
            return 'Es sind noch keine Kurstage vorhanden.';
        }

        $count = count($attendanceIncompleteDays);

        if ($count === 0) {
            return 'Für mindestens einen Kurstag fehlen Anwesenheitseinträge.';
        }

        $listedDays = implode(', ', array_slice($attendanceIncompleteDays, 0, 3));
        $suffix = $count > 3 ? ' ...' : '';

        return "Für {$count} Kurstag(e) fehlen Anwesenheitseinträge, z. B.: {$listedDays}{$suffix}.";
    }

    protected function buildResultsRequirementDetail(bool $hasExternalExam, int $participantCount, int $missingResultsCount): ?string
    {
        if ($this->course->hasResultsForAllParticipantsOrExternalExam()) {
            return $hasExternalExam ? 'Der Kurs ist als externe Prüfung markiert.' : null;
        }

        if ($participantCount === 0) {
            return 'Dem Kurs sind keine aktiven Teilnehmer zugeordnet.';
        }

        return "Für {$missingResultsCount} von {$participantCount} Teilnehmern fehlen Klausurergebnisse oder ein gesetzter Status.";
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class="h-32 w-full relative animate-pulse">
                    <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 transition-opacity">
                        <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow">
                            <span class="loader"></span>
                            <span class="text-sm text-gray-700">wird geladen…</span>
                        </div>
                    </div>
            </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.tutor.courses.manage-course-invoice', [
            'invoice' => $this->invoiceFile,
            'canUploadInvoice' => $this->canUploadInvoice,
            'invoiceRequirements' => $this->invoiceRequirements,
        ]);
    }
}
