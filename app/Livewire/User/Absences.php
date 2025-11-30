<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Validation\Rule;
use App\Models\UserRequest;
use App\Models\File;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

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
    public array $absence_attachments = [];

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
        $this->klasse ??= data_get(Auth::user()?->person?->programdata, 'stammklasse');
        $this->dispatch('filepool:saved', ['model' => 'attachments']);
    }

    public function close(): void { $this->showModal = false; }

    public function removeAttachment(int $index): void
    {
        unset($this->absence_attachments[$index]);
        $this->absence_attachments = array_values($this->absence_attachments);
    }

    public function rules(): array
    {
        $rules = [
            'klasse'      => ['required','string','max:12'],

            // Für <input type="date">:
            'fehlDatum'   => ['required','date','date_format:Y-m-d','after_or_equal:today'],

            // Wenn Vergangenheit erlaubt sein soll (stattdessen):
            // 'fehlDatum' => ['required','date','date_format:Y-m-d','before_or_equal:today'],

            'abw_grund'   => ['required', Rule::in(['abw_wichtig','abw_unwichtig'])],
            'begruendung' => ['nullable','string','max:400'],

            // Times nur wenn nicht ganztägig
            'fehlUhrGek'  => ['exclude_if:fehltag,true','required','date_format:H:i'],
            'fehlUhrGeg'  => ['exclude_if:fehltag,true','required','date_format:H:i','after:fehlUhrGek'],

            // Uploads
            'absence_attachments'   => ['array'],
            'absence_attachments.*' => ['file','max:5120','mimes:jpg,jpeg,png,gif,pdf'],
        ];

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
            'fehlDatum.after_or_equal'     => 'Das Datum darf nicht in der Vergangenheit liegen.',
            'fehlUhrGek.required' => 'Bitte eine Uhrzeit angeben.',
            'fehlUhrGeg.required' => 'Bitte eine Uhrzeit angeben.',
            'fehlUhrGeg.after'    => 'Endzeit muss nach der Startzeit liegen.',
            'grund_item.required' => 'Bitte einen konkreten Grund auswählen.',
        ];
    }

    public function resetForm(): void
    {
        $this->reset([
            'klasse','fehltag','fehlDatum','fehlUhrGek','fehlUhrGeg',
            'abw_grund','grund_item','begruendung','absence_attachments',
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
        foreach ($this->absence_attachments as $up) {
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
            $this->dispatch('filepool:saved', ['model' => 'absence_attachments']);


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
