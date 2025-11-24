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
        // fileableType / fileableId optional aus Payload übernehmen
        if (isset($payload['fileableType'])) {
            $this->fileableType = $payload['fileableType'];
        }

        if (isset($payload['fileableId'])) {
            $this->fileableId = (int) $payload['fileableId'];
        }

        // Optional: fileType überschreiben
        if (isset($payload['fileType'])) {
            $this->fileType = $payload['fileType'];
        }

        // Optional: Label
        if (isset($payload['label'])) {
            $this->label = $payload['label'];
        }

        // Optional: Kontext-Text
        if (isset($payload['contextName'])) {
            $this->contextName = $payload['contextName'];
        }

        if (isset($payload['signForName'])) {     
            $this->signForName = $payload['signForName'];
        }

        // Reset 
        $this->reset(['signatureDataUrl', 'upload', 'errorMsg']);

        // Modal öffnen
        $this->open = true;
    }

    protected function resolveFileable(): ?Model
    {
        if (!$this->fileableType || !$this->fileableId) {
            return null;
        }

        if (!class_exists($this->fileableType)) {
            return null;
        }

        return ($this->fileableType)::find($this->fileableId);
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

            $filename = 'sig_upload_' . $user->id . '_' . time() . '.' . $ext;
            $path = $this->upload->storeAs($dir, $filename, $disk);
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
            $filename = 'sig_draw_' . $user->id . '_' . time() . '.png';
            $path = $dir . '/' . $filename;

            Storage::disk($disk)->put($path, $png);
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
        $file = $fileable->files()->create([
            'user_id'   => $user->id,
            'name'      => $this->label,
            'path'      => $path,
            'disk'      => $disk,
            'type'      => $this->fileType,
            'mime_type' => $mimeType,
            'size'      => $size,
            'checksum'  => hash('sha256', $path . '|' . $size),
        ]);

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
