<?php

namespace App\Livewire\User;

use Livewire\Component;
use App\Models\ExamAppointment;
use Illuminate\Support\Carbon;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\UserRequest;
use App\Models\File;

class ExternalMakeupRegistration extends Component
{
    use WithFileUploads;

    public bool $showModal = false;

    // Formularfelder
    public ?string $klasse = null;                 // class_label / Klassen-Eingabe
    public ?string $certification_key = null;      // ID der Zertifizierung (ExamAppointment-ID)
    public ?string $certification_label = null;    // Anzeigename (aus DB)
    public ?string $scheduled_at = null;           // Unix-Timestamp als String (Select)
    public ?string $exam_modality = null;          // online | praesenz
    public ?string $reason = null;                 // zert_faild | krankMitAtest | krankOhneAtest
    public ?string $email_priv = null;             // optionale private E-Mail

    // Dynamische Optionen
    public array $certOptions = [];
    public array $dateOptions = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[]|array */
    public array $exam_registration_attachments = [];

    protected $listeners = [
        'open-request-form'         => 'handleOpen',
        'open-external-makeup-form' => 'open',
    ];

    public function handleOpen($payload = []): void
    {
        if (($payload['type'] ?? null) === 'external_makeup') {
            $this->resetForm();
            $this->open();
        }
    }

    public function open(): void
    {
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->showModal = false;
    }

    public function resetForm(): void
    {
        $this->reset([
            'klasse',
            'certification_key',
            'certification_label',
            'scheduled_at',
            'exam_modality',
            'reason',
            'email_priv',
            'dateOptions',
            'exam_registration_attachments',
        ]);

        $this->resetErrorBag();
    }

    /**
     * Wenn im Zertifizierungs-Select etwas gewählt wird:
     * - Label aus DB holen
     * - passende Termine (dates[]) des ExamAppointment in $dateOptions füllen
     */
    public function updatedCertificationKey($value): void
    {
        $this->scheduled_at = null;
        $this->dateOptions  = [];
        $this->certification_label = null;

        if (blank($value)) {
            return;
        }

        // Sicherstellen, dass es wirklich ein externer Termin ist
        $ap = ExamAppointment::query()
            ->where('type', 'extern')
            ->whereKey($value)
            ->first();

        if (! $ap) {
            return;
        }

        $this->certification_label = $ap->name ?? null;

        // dates → dateOptions (nur zukünftige Termine)
        if (! is_array($ap->dates)) {
            return;
        }

        $now = Carbon::now();
        $slots = [];

        foreach ($ap->dates as $entry) {
            $dtStr = $entry['datetime'] ?? $entry['from'] ?? null;
            if (! $dtStr) {
                continue;
            }

            $dt = Carbon::parse($dtStr);

            if ($dt->lt($now)) {
                continue; // vergangene Termine ausblenden
            }

            $slots[] = [
                'ts'    => (string) $dt->timestamp,
                'label' => $dt->format('d.m.Y').' - '.$dt->format('H:i'),
            ];
        }

        // Sortiert nach Datum
        usort($slots, fn($a, $b) => (int)$a['ts'] <=> (int)$b['ts']);

        $this->dateOptions = $slots;
    }

    /**
     * Zertifizierungs-Optionen dynamisch aus ExamAppointment (type=extern) laden.
     */
    protected function loadCertOptions(): array
    {
        $appointments = ExamAppointment::query()
            ->where('type', 'extern')
            ->orderBy('name')
            ->get();

        return $appointments->map(function (ExamAppointment $ap) {
            return [
                'key'   => (string) $ap->id,
                'label' => $ap->name,
                'price' => $ap->preis !== null
                    ? '€ '.number_format((float)$ap->preis, 2, ',', '.')
                    : '€ –',
            ];
        })->all();
    }


        public function rules(): array
    {
        return [
            'klasse'             => ['nullable','string','max:12'],
            'certification_key'  => ['required','exists:exam_appointments,id'],
            'scheduled_at'       => ['required','numeric'],
            'exam_modality'      => ['required','in:online,praesenz'],
            'reason'             => ['required','in:zert_faild,krankMitAtest,krankOhneAtest'],
            'email_priv'         => ['nullable','email'],

            'exam_registration_attachments.*' => [
                'file','mimes:jpg,jpeg,png,gif,pdf','max:8192',
            ],
        ];
    }

    public function save(): void
{
    $this->validate();

    // Externen Prüfungstermin aus DB holen (Sicherheitsanker)
    $appointment = ExamAppointment::query()
        ->where('type', 'extern')
        ->findOrFail($this->certification_key);

    // Gebühren-Logik: direkt aus ExamAppointment->preis (in Euro) -> cents
    $feeCents = $appointment->preis !== null
        ? (int) round(((float) $appointment->preis) * 100)
        : null;

    // Attest-Flag aus Grund
    $withAttest = $this->reason === 'krankMitAtest';

    // Geplanter Termin aus Unix TS (aus dem Termin-Select)
    $scheduledAt = Carbon::createFromTimestamp((int) $this->scheduled_at);

    // Titel für Übersicht
    $title = 'Externe Prüfung ' . ($this->certification_label ?? $appointment->name ?? '');

    // UserRequest anlegen
    $request = UserRequest::create([
        'user_id'            => Auth::id(),
        'type'               => 'external_makeup',
        'title'              => $title,
        'status'             => 'pending',
        'submitted_at'       => now(),

        // Personen-/Klassendaten
        'class_label'        => $this->klasse,

        // Prüfungsbezogen
        'certification_key'   => (string) $appointment->id,
        'certification_label' => $appointment->name,
        'scheduled_at'        => $scheduledAt,
        'exam_modality'       => $this->exam_modality,

        // Gründe / Gebühren
        'reason'             => $this->reason,   // zert_faild | krankMitAtest | krankOhneAtest
        'with_attest'        => $withAttest,
        'fee_cents'          => $feeCents,

        // Kontakt
        'email_priv'         => $this->email_priv,

        // Flex: Rohwerte zusätzlich sichern
        'data'               => [
            'source'              => 'external-makeup-registration',
            'exam_appointment_id' => $appointment->id,
        ],
    ]);

    // Uploads -> File-Model (morphMany: files)
    if (!empty($this->exam_registration_attachments)) {
        foreach ($this->exam_registration_attachments as $upload) {
            $ext     = strtolower($upload->getClientOriginalExtension() ?: 'bin');
            $orig    = $upload->getClientOriginalName();
            $mime    = $upload->getMimeType();
            $size    = $upload->getSize();
            $name    = pathinfo($orig, PATHINFO_FILENAME);
            $storeAs = Str::uuid()->toString().'_'.Str::slug($name).'.'.$ext;

            $path = $upload->storeAs(
                'uploads/user-requests/'.$request->id,
                $storeAs,
                'private'
            );

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

    $this->dispatch('notify', type: 'success', message: 'Antrag auf externe Nachprüfung eingereicht.');
    $this->dispatch('user-request:created');

    $this->close();
    $this->resetForm();
}

    public function render()
    {
        // Bei jedem Render aktuelle externen Prüfungen laden
        $this->certOptions = $this->loadCertOptions();

        return view('livewire.user.external-makeup-registration')->layout('layouts.app');
    }
}
