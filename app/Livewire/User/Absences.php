<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;
use App\Models\UserRequest;

class Absences extends Component
{
    use WithFileUploads;

    public bool $showModal = false;

    // Formularfelder
    public ?string $klasse = null;
    public bool $fehltag = false;
    public ?string $fehlDatum = null;
    public ?string $fehlUhrGek = null;
    public ?string $fehlUhrGeg = null;
    public ?string $abw_grund = 'abw_unwichtig';
    public ?string $grund_item = null;
    public ?string $begruendung = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $attachments = [];

    /** Gründe lokal im Modul fest verdrahtet */
    public array $reasons = [
        'wohnungswechsel'    => 'Wohnungswechsel',
        'krankheit'          => 'Krankheit',
        'eheschliessung'     => 'Eheschließung (TN/Kind)',
        'ehejubilaeum'       => 'Ehejubiläum (TN/Eltern/Schwiegereltern)',
        'schwere_erkrankung' => 'Schwere Erkrankung (Partner/Kind)',
        'geburt'             => 'Niederkunft der Ehefrau',
        'todesfall'          => 'Todesfall (Partner/Kind/Eltern/Schwiegereltern)',
        'amtstermin'         => 'Amtlicher Termin',
        'ehrenamt'           => 'Öffentliches Ehrenamt',
        'religioese_feste'   => 'Religiöse Feste',
        'katastrophenschutz' => 'Katastrophenschutz-Einsätze',
        'sonstiges'          => 'Sonstiges',
    ];

    protected $listeners = [
        'open-request-form' => 'handleOpen',
        'open-absence-form' => 'open',
    ];

    public function handleOpen($payload = []): void
    {
        if (($payload['type'] ?? null) === 'absence') {
            $this->resetForm();
            $this->open();
        }
    }

    public function open(): void
    {
        $this->showModal = true;
        $this->fehlDatum ??= now()->toDateString();
        $this->abw_grund ??= 'abw_unwichtig';
    }

    public function close(): void { $this->showModal = false; }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function rules(): array
    {
        $rules = [
            'klasse'        => ['required','string','max:12'],
            'fehlDatum'     => ['required','date'],
            'abw_grund'     => ['required', Rule::in(['abw_wichtig','abw_unwichtig'])],
            'begruendung'   => ['nullable','string','max:400'],
            'attachments.*' => ['file','max:5120','mimes:jpg,jpeg,png,gif,pdf'],
        ];

        if (!$this->fehltag) {
            $rules['fehlUhrGek'] = ['required','date_format:H:i'];
            $rules['fehlUhrGeg'] = ['required','date_format:H:i','after:fehlUhrGek'];
        }

        if ($this->abw_grund === 'abw_wichtig') {
            $rules['grund_item'] = ['required', Rule::in(array_keys($this->reasons))];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'klasse.required'     => 'Bitte Klasse angeben.',
            'fehlDatum.required'  => 'Bitte ein Datum wählen.',
            'fehlUhrGeg.after'    => 'Endzeit muss nach der Startzeit liegen.',
            'grund_item.required' => 'Bitte einen konkreten Grund auswählen.',
        ];
    }

    public function resetForm(): void
    {
        $this->reset([
            'klasse','fehltag','fehlDatum','fehlUhrGek','fehlUhrGeg',
            'abw_grund','grund_item','begruendung','attachments',
        ]);
        $this->resetErrorBag();
        $this->fehlDatum = now()->toDateString();
        $this->abw_grund = 'abw_unwichtig';
    }

    public function save(): void
    {
        $this->validate();

        $req = UserRequest::create([
            'user_id'          => auth()->id(),
            'type'             => UserRequest::TYPE_ABSENCE,
            'status'           => UserRequest::STATUS_PENDING,
            'submitted_at'     => now(),
            'class_code'       => $this->klasse,
            'date_from'        => $this->fehlDatum,
            'date_to'          => $this->fehltag ? $this->fehlDatum : null,
            'full_day'         => $this->fehltag,
            'time_arrived_late'=> $this->fehltag ? null : $this->fehlUhrGek,
            'time_left_early'  => $this->fehltag ? null : $this->fehlUhrGeg,
            'reason'           => $this->abw_grund,
            'reason_item'      => $this->grund_item,
            'message'          => $this->begruendung,
        ]);

        // Attachments -> File-Model (polymorph)
        $disk = 'private';
        foreach ($this->attachments as $up) {
            $path = $up->store('uploads/requests/'.date('Y/m'), $disk);
            $req->files()->create([
                'user_id'   => auth()->id(),
                'name'      => $up->getClientOriginalName(),
                'path'      => $path,
                'mime_type' => $up->getMimeType(),
                'type'      => 'attachment',
                'size'      => $up->getSize(),
                'expires_at'=> null,
            ]);
        }

        $this->dispatch('user-request:updated');
        $this->dispatch('toast', [
            'type' => 'success',
            'title'=> 'Gespeichert',
            'text' => 'Deine Fehlzeit wurde eingereicht.',
        ]);

        $this->close();
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.user.absences')->layout('layouts.app');
    }
}
