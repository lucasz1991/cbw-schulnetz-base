<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use App\Models\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rules\File as FileRule;

class ManageCourseMedia extends Component
{
    use WithFileUploads;

    public Course $course;

    // Modal & Upload-Props für Roter Faden
    public bool $openRoterFadenForm = false;
    public $roterFadenUpload = null;         // \Livewire\TemporaryUploadedFile
    public ?string $roterFadenExpires = null;

    // ... oben in der Klasse
public bool $openPreview = false;



    protected function rules(): array
    {
        return [
            'roterFadenUpload' => 'nullable|file|max:30720', 
            'roterFadenExpires' => ['nullable', 'date'],
        ];
    }

    /** Deutsche Fehlermeldungen */
    protected function messages(): array
    {
        return [
            // Upload
            'roterFadenUpload.file'       => 'Die ausgewählte Datei konnte nicht gelesen werden.',
            'roterFadenUpload.mimetypes'  => 'Bitte lade eine PDF-Datei hoch.',
            'roterFadenUpload.mimes'      => 'Bitte lade eine PDF-Datei hoch.', // falls mimes statt mimetypes greift
            'roterFadenUpload.max'        => 'Die Datei darf maximal 30 MB groß sein.',

            // Ablaufdatum
            'roterFadenExpires.date'      => 'Bitte gib ein gültiges Datum ein.',
        ];
    }

        /** Schöne Attribut-Namen in den Meldungen */
    protected function validationAttributes(): array
    {
        return [
            'roterFadenUpload' => 'Roter Faden (PDF)',
            'roterFadenExpires' => 'Ablaufdatum',
        ];
    }


    public function mount(Course $course): void
    {
        $this->course = $course;
    }

    /** Aktuelle Roter-Faden-Datei (Single) */
    public function getRoterFadenFileProperty(): ?File
    {

        return $this->course->files()
            ->where('type', 'roter_faden')
            ->latest('id')
            ->first();
    }

    // optional: wenn du die URL vorher berechnen/loggen willst
public function openPreview(): void
{
    // nur öffnen, wenn es überhaupt einen Roten Faden gibt
    if ($this->roterFadenFile) {
        $this->openPreview = true;
    }
}

public function closePreview(): void
{
    $this->openPreview = false;
}

    /** Upload / Ersetzen des Roten Fadens */
    public function uploadRoterFaden(): void
    {
        $this->validate();

        if (!$this->roterFadenUpload) {
            $this->dispatch('toast', type:'error', message:'Bitte eine PDF-Datei auswählen.');
            return;
        }



        // Bestehenden Roter-Faden (falls vorhanden) entfernen
        if ($this->roterFadenFile) {
            $this->deleteFileRecord($this->roterFadenFile);
        }

        // Speichern auf "private" (wie in deinem File::getEphemeralPublicUrl erwartet)
        $disk = 'private';
        $dir  = "courses/{$this->course->id}/roter-faden";
        $path = $this->roterFadenUpload->store($dir, $disk);

        // File-Datensatz anlegen
        $file =  $this->course->files()->create([
                    'user_id'    => Auth::id(),
                    'name'       => $this->roterFadenUpload->getClientOriginalName(),
                    'path'       => $path,
                    'mime_type'  => 'application/pdf',
                    'type'       => 'roter_faden',
                    'size'       => $this->roterFadenUpload->getSize(),
                    'expires_at' =>  null,
                ]);

        // Reset + Close
        $this->reset(['roterFadenUpload', 'roterFadenExpires', 'openRoterFadenForm']);

        $this->dispatch('toast', type:'success', message:'Roter Faden aktualisiert.');
    }

    /** Entfernt den bestehenden Roten Faden */
    public function removeRoterFaden(): void
    {
        if (!$this->roterFadenFile) return;
        $this->deleteFileRecord($this->roterFadenFile);
        $this->roterFadenFile = null;
        $this->dispatch('toast', type:'success', message:'Roter Faden entfernt.');
    }

    /** Hilfsfunktion: Physisch + DB löschen */
    protected function deleteFileRecord(File $file): void
    {
        try {
            // Primär auf dem gespeicherten Disk löschen (hier: private)
            Storage::disk('private')->delete($file->path);
        } catch (\Throwable $e) {
            // Ignorieren/loggen – falls Datei bereits weg ist
        }
        $file->delete();
    }

    public function render()
    {
        return view('livewire.tutor.courses.manage-course-media', [
            'roterFaden' => $this->roterFadenFile,
        ]);
    }
}
