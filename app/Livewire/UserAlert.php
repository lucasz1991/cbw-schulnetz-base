<?php

namespace App\Livewire;

use Livewire\Component;

class UserAlert extends Component
{
    public $message = 'Standardnachricht';
    public $type = 'info';

    /** Übersetzungen für die Typen */
    public array $typeLabels = [
        'info'    => 'Information',
        'success' => 'Erfolgreich',
        'warning' => 'Warnung',
        'error'   => 'Fehler',
        'danger'  => 'Achtung',
        'question'=> 'Frage',
        'notice'  => 'Hinweis',
    ];

    protected $listeners = [
        'showAlert' => 'displayAlert',
        'toast'     => 'displayToast',
    ];

    public function displayToast($message, $type = 'info', array $options = [])
    {
        $payload = is_array($message) ? $message : array_merge(['text' => $message], $options);
        $this->dispatch('swal:toast',
            type: $payload['type']   ?? $type,
            title: $payload['title'] ?? ($this->typeLabels[$type] ?? ucfirst($type)),
            text: $payload['text']   ?? null,
            html: $payload['html']   ?? null,
            position: $payload['position'] ?? null,
            timer: $payload['timer'] ?? null
        );
    }

    public function displayAlert($message, $type = 'info', array $options = [])
    {
        $payload = is_array($message) ? $message : array_merge(['text' => $message], $options);

        $typeKey = $payload['type'] ?? $type;
        $title   = $payload['title'] ?? ($this->typeLabels[$typeKey] ?? ucfirst($typeKey));

        $this->dispatch('swal:alert',
            type: $typeKey,
            title: $title,
            text:  $payload['text'] ?? null,
            html:  $payload['html'] ?? null,
            showCancel:  $payload['showCancel']  ?? false,
            confirmText: $payload['confirmText'] ?? 'OK',
            cancelText:  $payload['cancelText']  ?? 'Abbrechen',
            allowOutsideClick: $payload['allowOutsideClick'] ?? true,
            onConfirm: $payload['onConfirm'] ?? null
        );
    }

    public function render()
    {
        return view('livewire.user-alert');
    }
}
