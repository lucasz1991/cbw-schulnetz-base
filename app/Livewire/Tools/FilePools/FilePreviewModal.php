<?php

namespace App\Livewire\Tools\FilePools;

use Livewire\Component;
use App\Models\File;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilePreviewModal extends Component
{
    public bool $open = false;
    public ?int $fileId = null;
    public ?File $file = null;

    #[On('filepool:preview')] // Livewire-Event (PHP → PHP / JS → PHP)
    public function handlePreview(int $id): void
    {
        $this->openWith($id);
    }

    public function downloadFile(int $fileId): StreamedResponse
    {
        $file = File::findOrFail($fileId);
        return $file->download(); // 👈 zentral im Model
    }

    public function openWith(int $id): void
    {
        $this->fileId = $id;
        $this->file   = File::find($id);

        if (!$this->file) {
            // Optional: globaler Toast/Alert
            $this->dispatch('toast', type: 'error', message: 'Datei nicht gefunden.');
            return;
        }

        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function getUrlProperty(): ?string
    {
        return $this->file ? $this->file->getEphemeralPublicUrl() : null;
    }

    public function render()
    {
        return view('livewire.tools.file-pools.file-preview-modal');
    }
}
