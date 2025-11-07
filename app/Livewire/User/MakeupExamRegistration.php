<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\UserRequest;
use App\Models\File;

class MakeupExamRegistration extends Component
{
    use WithFileUploads;

    public bool $showModal = false;

    // Formularfelder
    public ?string $klasse = null;         // -> class_code
    public ?string $wiederholung = null;   // wiederholung_1 | wiederholung_2
    public ?string $nachKlTermin = null;   // Unix-Timestamp (string)
    public ?string $nKlBaust = null;       // -> module_code
    public ?string $nKlDozent = null;      // -> instructor_name
    public ?string $nKlOrig = null;        // Y-m-d -> original_exam_date
    public ?string $grund = null;          // unter51 | krankMitAtest | krankOhneAtest

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[]|array */
    public array $examRegistrationAttachments = [];

    protected $listeners = [
        'open-request-form' => 'handleOpen',   // aus der Liste
        'open-makeup-form'  => 'open',         // direkter Trigger
    ];

    public function rules(): array
    {
        return [
            'klasse'        => ['nullable','string','max:12'],
            'wiederholung'  => ['required', Rule::in(['wiederholung_1','wiederholung_2'])],
            'nachKlTermin'  => ['required','numeric'], // Unix TS
            'nKlBaust'      => ['required','string','max:10'],
            'nKlDozent'     => ['required','string','max:120'],
            'nKlOrig'       => ['required','date'],
            'grund'         => ['required', Rule::in(['unter51','krankMitAtest','krankOhneAtest'])],

            'examRegistrationAttachments.*' => ['file','mimes:jpg,jpeg,png,gif,pdf','max:8192'], // 8192 KB = 8 MB
        ];
    }

    public function messages(): array
    {
        return [
            'wiederholung.required' => 'Bitte wähle eine Nachprüfungs-Option.',
            'nachKlTermin.required' => 'Bitte einen Termin wählen.',
            'nKlBaust.required'     => 'Bitte Baustein angeben.',
            'nKlDozent.required'    => 'Bitte Dozent angeben.',
            'nKlOrig.required'      => 'Bitte das ursprüngliche Prüfungsdatum angeben.',
            'grund.required'        => 'Bitte eine Begründung wählen.',
            'examRegistrationAttachments.*.mimes'   => 'Erlaubt sind: JPG, PNG, GIF, PDF.',
            'examRegistrationAttachments.*.max'     => 'Max. 8 MB pro Datei.',
        ];
    }

    public function handleOpen($payload = []): void
    {
        if (($payload['type'] ?? null) === 'makeup') {
            $this->resetForm();
            $this->open();
        }
    }

    public function open(): void  { $this->showModal = true; }
    public function close(): void { $this->showModal = false; }

    public function resetForm(): void
    {
        $this->reset([
            'klasse','wiederholung','nachKlTermin','nKlBaust',
            'nKlDozent','nKlOrig','grund','examRegistrationAttachments',
        ]);
        $this->resetValidation();
        $this->resetErrorBag();
    }

    public function removeAttachment(int $index): void
    {
        if (isset($this->examRegistrationAttachments[$index])) {
            unset($this->examRegistrationAttachments[$index]);
            $this->examRegistrationAttachments = array_values($this->examRegistrationAttachments);
        }
    }

    public function save(): void
    {
        $this->validate();

        // Gebühren-Logik
        $feeCents = match ($this->wiederholung) {
            'wiederholung_1' => 2000, // 20,00 €
            'wiederholung_2' => 4000, // 40,00 €
            default => null,
        };

        // Attest-Flag aus Grund
        $withAttest = $this->grund === 'krankMitAtest';

        // Geplanter Termin aus Unix TS
        $scheduledAt = Carbon::createFromTimestamp((int)$this->nachKlTermin);

        // Ursprüngliche Prüfung
        $originalExamDate = Carbon::parse($this->nKlOrig)->startOfDay();

        // Titel für Übersicht
        $title = 'Nachprüfung ' . ($this->nKlBaust ?: '');

        // Create UserRequest
        $request = UserRequest::create([
            'user_id'            => Auth::id(),
            'type'               => 'makeup',
            'title'              => $title,
            'status'             => 'pending',
            'submitted_at'       => now(),

            // Personen-/Klassendaten
            'class_code'         => $this->klasse,

            // Prüfungsbezogen
            'module_code'        => $this->nKlBaust,
            'instructor_name'    => $this->nKlDozent,
            'original_exam_date' => $originalExamDate,
            'scheduled_at'       => $scheduledAt,

            // Gründe / Gebühren
            'reason'             => $this->grund,      // unter51 | krankMitAtest | krankOhneAtest
            'with_attest'        => $withAttest,
            'fee_cents'          => $feeCents,

            // Flex: Rohwerte zusätzlich sichern
            'data'               => [
                'wiederholung'   => $this->wiederholung,
                'source'         => 'makeup-exam-registration',
            ],
        ]);

        // Uploads -> File-Model (morphMany: files)
        if (!empty($this->examRegistrationAttachments)) {
            foreach ($this->examRegistrationAttachments as $upload) {
                $ext     = strtolower($upload->getClientOriginalExtension() ?: 'bin');
                $orig    = $upload->getClientOriginalName();
                $mime    = $upload->getMimeType();
                $size    = $upload->getSize();
                $name    = pathinfo($orig, PATHINFO_FILENAME);
                $storeAs = Str::uuid()->toString().'_'.Str::slug($name).'.'.$ext;

                $path = $upload->storeAs('uploads/user-requests/'.$request->id, $storeAs, 'private');

                $request->files()->create([
                    'user_id'   => Auth::id(),
                    'name'      => $orig,
                    'path'      => $path,
                    'mime_type' => $mime,
                    'type'      => 'request-attachment',
                    'size'      => $size,
                ]);
            }
        }

        $this->dispatch('notify', type: 'success', message: 'Nachprüfungs-Antrag eingereicht.');
        $this->dispatch('user-request:created');
        $this->close();
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.user.makeup-exam-registration')->layout('layouts.app');
    }
}
