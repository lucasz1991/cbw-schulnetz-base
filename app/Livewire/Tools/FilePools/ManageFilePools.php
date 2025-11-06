<?php

namespace App\Livewire\Tools\FilePools;

use Livewire\Component;
use App\Models\FilePool;
use App\Models\File;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Response;
use ZipArchive;
use Illuminate\Support\Facades\Auth;

class ManageFilePools extends Component
{
    use WithPagination;
    use WithFileUploads;
    
    public string $modelType;
    public int $modelId;

    public ?int $filePoolId = null;
    public ?FilePool $filePool = null;

    public array $fileUploads = [];
    public array $selectedFiles = [];
    public array $expires = [];

    public ?File $file = null;
    public string $selectedFileName;
    public string $selectedFileExpiresDate;

    public bool $openFileForm = false;
    public bool $openEditFileForm = false;

    public bool $readOnly = true;



    public function mount(string $modelType, int $modelId, bool $readOnly): void
    {
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $model = $modelType::find($modelId);
        $this->filePool = $model->filePool()->firstOrCreate([
            'title' => 'Standart Ordner',
            'type' => $modelType,
            'description' => '',
        ]);
        $this->filePoolId = $this->filePool->id;
        $this->fileUploads = [$this->filePool->id => []];

        $this->openFileForm = false;
        $this->openEditFileForm = false;
        $this->readOnly = $readOnly;
    }

    public function uploadFile(int $filePoolId)
    {
        $this->validate([
            "fileUploads.$filePoolId"     => ['required','array','min:1'],
            "fileUploads.$filePoolId.*"   => ['file','max:302400'], // 300 MB je Datei
            "expires.$filePoolId"         => ['nullable','date','after:today'],
        ]);

        foreach ($this->fileUploads[$filePoolId] as $uploadedFile) {
            $filename = $uploadedFile->getClientOriginalName();
            $path     = $uploadedFile->store('uploads/files', 'private');
            $mime     = Storage::disk('private')->mimeType($path) ?? $uploadedFile->getClientMimeType();

            $this->filePool->files()->create([
                'user_id'    => Auth::user()->id ?? null,
                'name'       => $filename,
                'path'       => $path,
                'mime_type'  => $mime,
                'size'       => $uploadedFile->getSize(),
                'expires_at' => $this->expires[$filePoolId] ?? null,
            ]);
        }
        unset($this->fileUploads[$filePoolId], $this->expires[$filePoolId]);
        $this->openFileForm = false;
        $this->filePool->refresh();
        $this->resetErrorBag();

        // >>> Dropzone-Reset anstoßen (model-Pfad mitgeben!)
        $this->dispatch('filepool:saved', model: "fileUploads.$filePoolId");
    }


    public function downloadFile(int $fileId): StreamedResponse
    {
        $file = File::findOrFail($fileId);
        return $file->download(); // 👈 zentral im Model
    }


    public function editFile($id)
    {
        $this->file = File::findOrFail($id);
        $this->selectedFileName = $this->file->name;
        $this->selectedFileExpiresDate = $this->file->expires_at ?? '';
        $this->openEditFileForm = true;
    }

    public function safeFile()
    {
        $this->validate([
            'selectedFileName' => 'required|string|max:255',
            'selectedFileExpiresDate' => 'nullable|date|after_or_equal:today',
        ]);

        if (!$this->file) {
            $this->addError('file', 'Keine Datei ausgewählt.');
            return;
        }

        $this->file->update([
            'name' => $this->selectedFileName,
            'expires_at' => $this->selectedFileExpiresDate ?: null,
        ]);

        $this->reset(['file', 'selectedFileName', 'selectedFileExpiresDate', 'openEditFileForm']);
        $this->filePool->refresh();
    }

    /**
     * Gemeinsamer Helper: erzeugt ein ZIP unter storage_path("app/private/zips")
     * und liefert eine BinaryFileResponse, die die ZIP-Datei nach dem Senden löscht.
     *
     * @param  string                      $baseName (ohne .zip)
     * @param  \Illuminate\Support\Collection|\Traversable|array  $files  // Sammlung von File-Modellen
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    protected function buildZipResponse(string $baseName, $files)
    {
        $zipFileName = trim($baseName) . '.zip';
        $zipDir      = storage_path('app/private/zips');
        $zipPath     = $zipDir . DIRECTORY_SEPARATOR . $zipFileName;

        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'ZIP konnte nicht erzeugt werden.');
        }

        $countAdded = 0;

        foreach ($files as $file) {
            // Datei ggf. überspringen, wenn abgelaufen
            if ($file->expires_at && now()->isAfter($file->expires_at)) {
                continue;
            }

            // Wir speichern auf 'private' -> also von dort lesen
            $absolutePath = Storage::disk('private')->path($file->path);
            if (is_file($absolutePath) && is_readable($absolutePath)) {
                // Im Archiv mit Originalnamen ablegen
                $zip->addFile($absolutePath, $file->name_with_extension);
                $countAdded++;
            }
        }

        $zip->close();

        if ($countAdded === 0) {
            // ZIP wieder löschen, wenn leer
            @unlink($zipPath);
            abort(404, 'Keine (nicht abgelaufenen) Dateien gefunden.');
        }

        // Download-Response mit Auto-Löschung
        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * FIX: nutzt nun die 'private' Disk (Uploads wurden dort gespeichert)
     * und ignoriert abgelaufene Dateien.
     */
    public function downloadFiles()
    {
        $files = $this->filePool
            ? $this->filePool->files()->get()
            : collect();

        $base = 'CBW_Schulnetz_downloads_' . now()->format('Ymd_His');

        return $this->buildZipResponse($base, $files);
    }

    /**
     * Lädt ALLE nicht abgelaufenen Dateien des aktuellen Pools als ZIP.
     */
    public function downloadAll()
    {
        if (!$this->filePool) {
            abort(404, 'FilePool nicht gefunden.');
        }

        $files = $this->filePool->files()->get();

        $base = 'CBW_Schulnetz_Medien_' . now()->format('Y_m_d-H_i_s');

        return $this->buildZipResponse($base, $files);
    }


    public function deleteFile(int $fileId)
    {
        $file = File::findOrFail($fileId);
        Storage::disk('private')->delete($file->path);
        $file->delete();
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
        $filePool = FilePool::where('filepoolable_type', $this->modelType)
            ->where('filepoolable_id', $this->modelId)
            ->first();

        return view('livewire.tools.file-pools.manage-file-pools', [
            'filePool' => $filePool
        ]);
    }
}
