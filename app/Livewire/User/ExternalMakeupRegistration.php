<?php

namespace App\Livewire\User;

use App\Models\ExamAppointment;
use App\Models\UserRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class ExternalMakeupRegistration extends Component
{
    use WithFileUploads;

    public bool $showModal = false;
    public int $minimumLeadDays = 14;
    public int $maximumWindowDays = 365;

    // Formularfelder
    public ?string $klasse = null;
    public ?string $certification_key = null;
    public ?string $certification_label = null;
    public ?string $scheduled_at = null; // YYYY-mm-dd
    public ?string $exam_modality = 'online';
    public ?string $reason = null;
    public ?string $email_priv = null;

    // Dynamische Optionen
    public array $certOptions = [];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[]|array */
    public array $exam_registration_attachments = [];

    protected $listeners = [
        'open-request-form' => 'handleOpen',
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
        // Durchfuehrungsort wird systemseitig fix auf online gesetzt.
        $this->exam_modality = 'online';
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
            'reason',
            'email_priv',
            'exam_registration_attachments',
        ]);

        $this->exam_modality = 'online';
        $this->resetErrorBag();
    }

    public function updatedCertificationKey($value): void
    {
        $this->certification_label = null;

        if (blank($value)) {
            return;
        }

        $appointment = ExamAppointment::query()
            ->where('type', 'extern')
            ->whereKey($value)
            ->first();

        if (! $appointment) {
            return;
        }

        $this->certification_label = $appointment->name ?? null;
    }

    protected function loadCertOptions(): array
    {
        $appointments = ExamAppointment::query()
            ->where('type', 'extern')
            ->orderBy('name')
            ->get();

        return $appointments->map(function (ExamAppointment $appointment) {
            return [
                'key' => (string) $appointment->id,
                'label' => $appointment->name,
                'price' => $appointment->preis !== null
                    ? '€ '.number_format((float) $appointment->preis, 2, ',', '.')
                    : '€ –',
            ];
        })->all();
    }

    public function rules(): array
    {
        return [
            'klasse' => ['nullable', 'string', 'max:12'],
            'certification_key' => ['required', 'exists:exam_appointments,id'],
            'scheduled_at' => ['required', 'date_format:Y-m-d'],
            'exam_modality' => ['required', 'in:online'],
            'reason' => ['required', 'in:zert_faild,krankMitAtest,krankOhneAtest'],
            'email_priv' => ['nullable', 'email'],
            'exam_registration_attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:8192'],
        ];
    }

    public function save(): void
    {
        // Durchfuehrungsort fuer diesen Buchungsweg immer online.
        $this->exam_modality = 'online';
        $this->validate();

        $appointment = ExamAppointment::query()
            ->where('type', 'extern')
            ->findOrFail($this->certification_key);

        $minimumAllowedDate = Carbon::today()->addDays($this->minimumLeadDays)->startOfDay();
        $maximumAllowedDate = Carbon::today()->addDays($this->maximumWindowDays)->endOfDay();
        $scheduledAt = Carbon::createFromFormat('Y-m-d', (string) $this->scheduled_at)->startOfDay();

        if (
            $scheduledAt->lt($minimumAllowedDate) ||
            $scheduledAt->gt($maximumAllowedDate) ||
            $scheduledAt->isWeekend()
        ) {
            $this->addError(
                'scheduled_at',
                'Bitte waehlen Sie einen Werktag zwischen heute + '.$this->minimumLeadDays.' Tagen und maximal einem Jahr.'
            );

            return;
        }

        $feeCents = $appointment->preis !== null
            ? (int) round(((float) $appointment->preis) * 100)
            : null;

        $withAttest = $this->reason === 'krankMitAtest';
        $title = 'Externe Prüfung '.($this->certification_label ?? $appointment->name ?? '');

        $request = UserRequest::create([
            'user_id' => Auth::id(),
            'type' => 'external_makeup',
            'title' => $title,
            'status' => 'pending',
            'submitted_at' => now(),

            'class_label' => $this->klasse,

            'certification_key' => (string) $appointment->id,
            'certification_label' => $appointment->name,
            'scheduled_at' => $scheduledAt,
            'exam_modality' => 'online',

            'reason' => $this->reason,
            'with_attest' => $withAttest,
            'fee_cents' => $feeCents,

            'email_priv' => $this->email_priv,

            'data' => [
                'source' => 'external-makeup-registration',
                'exam_appointment_id' => $appointment->id,
            ],
        ]);

        if (! empty($this->exam_registration_attachments)) {
            foreach ($this->exam_registration_attachments as $upload) {
                $ext = strtolower($upload->getClientOriginalExtension() ?: 'bin');
                $orig = $upload->getClientOriginalName();
                $mime = $upload->getMimeType();
                $size = $upload->getSize();
                $name = pathinfo($orig, PATHINFO_FILENAME);
                $storeAs = Str::uuid()->toString().'_'.Str::slug($name).'.'.$ext;

                $path = $upload->storeAs(
                    'uploads/user-requests/'.$request->id,
                    $storeAs,
                    'private'
                );

                $request->files()->create([
                    'user_id' => Auth::id(),
                    'name' => $orig,
                    'path' => $path,
                    'mime_type' => $mime,
                    'type' => 'request-attachment',
                    'size' => $size,
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
        $this->certOptions = $this->loadCertOptions();

        $minimumDate = Carbon::today()->addDays($this->minimumLeadDays);
        $maximumDate = Carbon::today()->addDays($this->maximumWindowDays);

        return view('livewire.user.external-makeup-registration', [
            'minimumDateLabel' => $minimumDate->format('d.m.Y'),
            'maximumDateLabel' => $maximumDate->format('d.m.Y'),
            'minimumDateForPicker' => $minimumDate->format('Y-m-d'),
            'maximumDateForPicker' => $maximumDate->format('Y-m-d'),
        ])->layout('layouts.app');
    }
}