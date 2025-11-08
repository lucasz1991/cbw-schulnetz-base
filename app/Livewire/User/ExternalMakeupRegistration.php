<?php

namespace App\Livewire\User;

use Livewire\Component;

class ExternalMakeupRegistration extends Component
{
    public bool $showModal = false;

    // Formularfelder
    public ?string $klasse = null;                 // class_label / Klassen-Eingabe
    public ?string $certification_key = null;      // ID der Zertifizierung (Select)
    public ?string $certification_label = null;    // Anzeigename (aus Options übernommen)
    public ?string $scheduled_at = null;           // Unix-Timestamp als String (Select)
    public ?string $exam_modality = null;          // online | praesenz
    public ?string $reason = null;                 // zert_faild | krankMitAtest | krankOhneAtest
    public ?string $email_priv = null;             // optionale private E-Mail

    // (Optional) Preisliste/Optionen – später ggf. per DB/Config laden
    public array $certOptions = [
        ['key' => '31', 'label' => 'SAP – UCDE_FL_S41809 FOUNDATION LEVEL - SYSTEM HANDLING', 'price' => '€ 179,00'],
        ['key' => '55', 'label' => 'Zend PHP-Zertifizierung', 'price' => '€ 259,00'],
        ['key' => '49', 'label' => 'TOEIC® Listening and Reading-Test', 'price' => '€ 149,00'],
        ['key' => '6',  'label' => 'ICDL - Datenschutz', 'price' => '€ -25,00'],
    ];

    public array $dateOptions = [
        ['ts' => '1760088600', 'label' => '10.10.2025 - 11:30'],
        ['ts' => '1761304500', 'label' => '24.10.2025 - 13:15'],
        ['ts' => '1762511400', 'label' => '07.11.2025 - 11:30'],
        ['ts' => '1763721000', 'label' => '21.11.2025 - 11:30'],
        ['ts' => '1764930600', 'label' => '05.12.2025 - 11:30'],
        ['ts' => '1767954600', 'label' => '09.01.2026 - 11:30'],
    ];

    protected $listeners = [
        // Globales Event aus der Liste
        'open-request-form' => 'handleOpen',
        // Optional direktes Event nur für diesen Dialog
        'open-external-makeup-form' => 'open',
    ];

    public function handleOpen($payload = []): void
    {
        if (($payload['type'] ?? null) === 'external_makeup') {
            $this->resetForm();
            $this->open();
        }
    }

    public function open(): void  { $this->showModal = true; }
    public function close(): void { $this->showModal = false; }

    public function resetForm(): void
    {
        $this->reset([
            'klasse','certification_key','certification_label','scheduled_at',
            'exam_modality','reason','email_priv',
        ]);
        $this->resetErrorBag();
    }

    // Wenn im Select die Option gewählt wird, kannst du hier gleich das Label mitziehen
    public function updatedCertificationKey($value): void
    {
        $found = collect($this->certOptions)->firstWhere('key', $value);
        $this->certification_label = $found['label'] ?? null;
    }

    public function render()
    {
        return view('livewire.user.external-makeup-registration')->layout('layouts.app');
    }
}
