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

    public function getInvoiceFileProperty(): ?File
    {
        return $this->course->files()
            ->where('type', 'invoice') 
            ->latest('id')
            ->first();
    }

    public function openPreview(): void
    {
        if ($this->invoiceFile) $this->openPreview = true;
    }
    public function closePreview(): void
    {
        $this->openPreview = false;
    }

    public function uploadInvoice(): void
    {
        $this->validate();

        if (!$this->invoiceUpload) {
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

        $this->dispatch('toast', type:'success', message:'Rechnung aktualisiert.');
        $this->dispatch('filepool:saved', model: 'invoiceUpload');
    }

    public function removeInvoice(): void
    {
        if (!$this->invoiceFile) return;
        $this->deleteFileRecord($this->invoiceFile);
        $this->invoiceFile = null;
        $this->dispatch('toast', type:'success', message:'Rechnung entfernt.');
    }

    protected function deleteFileRecord(File $file): void
    {
        try {
            Storage::disk('private')->delete($file->path);
        } catch (\Throwable $e) {
          
        }
        $file->delete();
    }

    public function render()
    {
        return view('livewire.tutor.courses.manage-course-invoice', [
            'invoice' => $this->invoiceFile,
        ]);
    }
}
