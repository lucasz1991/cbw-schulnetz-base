<?php

namespace App\Livewire\Tools\FilePools;

use Livewire\Component;
use App\Models\FilePool;
use App\Models\File;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class ManageFilePools extends Component
{
    use WithPagination;
    use WithFileUploads;
    
    public string $modelType;
    public int $modelId;

    public ?int $filePoolId = null;
    public ?FilePool $filePool = null;

    public array $fileUploads = [];
    public array $expires = [];

    public bool $openFileForm = false;



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
    }

    public function uploadFile(int $filePoolId)
    {
        $this->validate([
            "fileUploads.$filePoolId"     => ['required','array','min:1','max:302400'],
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

    public function deleteFile(int $fileId)
    {
        $file = File::findOrFail($fileId);
        Storage::delete($file->path);
        $file->delete();
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
