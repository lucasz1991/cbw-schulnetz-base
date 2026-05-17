<?php

namespace App\Livewire\Tutor\Courses;

use App\Models\Course;
use App\Models\File;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ManageCourseMedia extends Component
{
    use WithFileUploads;

    private const ROTER_FADEN_TEMPLATE_SETTINGS_TYPE = 'course_media';
    private const ROTER_FADEN_TEMPLATE_SETTINGS_KEY = 'roter_faden_template';

    public Course $course;

    public bool $openRoterFadenForm = false;
    public $roterFadenUpload = null;
    public ?string $roterFadenExpires = null;

    public bool $openPreview = false;

    protected function rules(): array
    {
        return [
            'roterFadenUpload' => 'nullable|file|max:30720|mimes:pdf', // max 30 MB
            'roterFadenExpires' => ['nullable', 'date'],
        ];
    }

    protected function messages(): array
    {
        return [
            'roterFadenUpload.file'       => 'Die ausgewählte Datei konnte nicht gelesen werden.',
            'roterFadenUpload.mimetypes'  => 'Bitte lade eine PDF-Datei hoch.',
            'roterFadenUpload.mimes'      => 'Bitte lade eine PDF-Datei hoch.',
            'roterFadenUpload.max'        => 'Die Datei darf maximal 30 MB groß sein.',
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

    /** Zentral hinterlegte Roter-Faden-Vorlage aus dem Adminbereich */
    public function getRoterFadenTemplateProperty(): ?array
    {
        return $this->normalizeRoterFadenTemplate(
            Setting::getValueUncached(
                self::ROTER_FADEN_TEMPLATE_SETTINGS_TYPE,
                self::ROTER_FADEN_TEMPLATE_SETTINGS_KEY
            )
        );
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

    /** Download der zentral gepflegten Roter-Faden-Vorlage */
    public function downloadRoterFadenTemplate()
    {
        $template = $this->roterFadenTemplate;

        if (! $template) {
            $this->dispatch('toast', type:'error', message:'Es ist keine Roter-Faden-Vorlage hinterlegt.');
            return null;
        }

        $disk = $template['disk'];
        $path = $template['path'];

        if (! Storage::disk($disk)->exists($path)) {
            $this->dispatch('toast', type:'error', message:'Die Roter-Faden-Vorlage konnte nicht gefunden werden.');
            return null;
        }

        $mime = $template['mime']
            ?: (Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream');

        return Storage::disk($disk)->download(
            $path,
            $this->sanitizeDownloadName($template['name'] ?: basename($path)),
            ['Content-Type' => $mime]
        );
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
        $this->roterFadenFile = $file;
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

    protected function normalizeRoterFadenTemplate(mixed $template): ?array
    {
        if (is_string($template) && trim($template) !== '') {
            $template = [
                'path' => trim($template),
                'disk' => 'private',
                'name' => basename(trim($template)),
                'mime' => null,
            ];
        }

        if (! is_array($template)) {
            return null;
        }

        $path = trim((string) ($template['path'] ?? ''));

        if ($path === '') {
            return null;
        }

        $disk = (string) ($template['disk'] ?? 'private');

        if (! in_array($disk, ['private', 'public'], true)) {
            $disk = 'private';
        }

        return [
            'path' => $path,
            'disk' => $disk,
            'name' => (string) ($template['name'] ?? basename($path)),
            'mime' => $template['mime'] ?? null,
            'size' => $template['size'] ?? null,
        ];
    }

    protected function sanitizeDownloadName(string $name): string
    {
        $name = trim($name);
        $name = str_replace(['\\', '/', "\0"], '-', $name);

        return $name === '' ? 'roter-faden-vorlage' : $name;
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
        return view('livewire.tutor.courses.manage-course-media', [
            'roterFaden' => $this->roterFadenFile,
            'roterFadenTemplate' => $this->roterFadenTemplate,
        ]);
    }
}
