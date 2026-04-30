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
    }

    public function reloadOnTabActivated(): void
    {
        $this->course->refresh();
    }

    public function getCanUploadInvoiceProperty(): bool
    {
        $this->course->refresh();

        return $this->course->isReadyForInvoice();
    }

    public function getInvoiceFileProperty(): ?File
    {
        return $this->course->files()
            ->where('type', 'invoice')
            ->latest('id')
            ->first();
    }

    public function getInvoiceRequirementsProperty(): array
    {
        $this->course->refresh();

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
        if (! $this->canUploadInvoice) {
            $this->dispatch('toast', type:'error', message:'Bitte zuerst Kursdaten (z. B. Roter Faden & Prüfungsergebnisse) vollständig pflegen.');
            return;
        }

        $this->openInvoiceForm = true;
    }

    public function openPreview(): void
    {
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
        $this->validate();

        if (! $this->canUploadInvoice) {
            $this->dispatch('toast', type:'error', message:'Rechnungen können erst nach vollständiger Kursdokumentation hochgeladen werden.');
            return;
        }

        if (! $this->invoiceUpload) {
            $this->dispatch('toast', type:'error', message:'Bitte eine PDF-Datei auswählen.');
            return;
        }

        if ($this->invoiceFile) {
            $this->deleteFileRecord($this->invoiceFile);
        }

        $disk = 'private';
        $dir  = "courses/{$this->course->id}/invoice";
        $path = $this->invoiceUpload->store($dir, $disk);

        $file = $this->course->files()->create([
            'user_id'    => Auth::id(),
            'name'       => $this->invoiceUpload->getClientOriginalName(),
            'path'       => $path,
            'mime_type'  => 'application/pdf',
            'type'       => 'invoice',
            'size'       => $this->invoiceUpload->getSize(),
            'expires_at' => null,
        ]);

        $this->reset(['invoiceUpload', 'invoiceExpires', 'openInvoiceForm']);
        $this->invoiceFile = $file;
        $this->dispatch('toast', type:'success', message:'Rechnung aktualisiert.');
        $this->dispatch('filepool:saved', model: 'invoiceUpload');
    }

    public function removeInvoice(): void
    {
        if (! $this->invoiceFile) {
            return;
        }

        $this->deleteFileRecord($this->invoiceFile);
        $this->invoiceFile = null;
        $this->dispatch('toast', type:'success', message:'Rechnung entfernt.');
    }

    protected function deleteFileRecord(File $file): void
    {
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
