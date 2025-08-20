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



    public function mount(string $modelType, int $modelId): void
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
            $path     = $uploadedFile->store('uploads/files', 'public');
            $mime     = Storage::disk('public')->mimeType($path) ?? $uploadedFile->getClientMimeType();

            $this->filePool->files()->create([
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

    public function downloadFiles(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $zipFileName = 'CBW_Schulnetz_downloads_' . now()->format('Ymd_His') . '.zip';
        $zipPath = storage_path("app/public/zips/{$zipFileName}");

        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($this->filePool->files as $file) {
                $filePath = Storage::disk('public')->path($file->path);
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $file->name);
                }
            }
            $zip->close();
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }


    public function downloadFile(int $fileId): StreamedResponse
    {
        $file = File::findOrFail($fileId);
        return Storage::disk('public')->download($file->path, $file->name);
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


    public function deleteFile(int $fileId)
    {
        $file = File::findOrFail($fileId);
        Storage::delete($file->path);
        $file->delete();
    }

    public function placeholder()
    {
        return <<<'HTML'
            <div role="status" class="space-y-8 py-8 animate-pulse md:flex md:items-center md:space-x-8 w-full">
                <div class="w-full space-y-2">
                    <div class="h-2.5 bg-gray-300 rounded-full w-48 mb-4"></div>
                    <div class="h-2 bg-gray-300 rounded-full max-w-[480px] mb-2.5"></div>
                    <div class="h-2 bg-gray-300 rounded-full mb-2.5"></div>
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
