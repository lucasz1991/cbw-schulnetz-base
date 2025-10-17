<?php

namespace App\Livewire;

use Livewire\Component;

class UserAlert extends Component
{
    public $message = 'Standardnachricht'; // (Tippfehler korrigiert)
    public $type = 'info';

    protected $listeners = [
        'showAlert' => 'displayAlert', // Modal
        'toast'     => 'displayToast', // Toast
    ];

public function displayToast($message, $type = 'info', array $options = [])
{
    $payload = is_array($message) ? $message : array_merge(['text' => $message], $options);
    $this->dispatch('swal:toast',
        type: $payload['type']   ?? $type,
        title: $payload['title'] ?? null,
        text: $payload['text']   ?? null,
        html: $payload['html']   ?? null,
        position: $payload['position'] ?? null,
        timer: $payload['timer'] ?? null
    );
}

public function displayAlert($message, $type = 'info', array $options = [])
{
    $payload = is_array($message) ? $message : array_merge(['text' => $message], $options);

    $this->dispatch('swal:alert',
        type: $payload['type'] ?? $type,
        title: $payload['title'] ?? ucfirst(($payload['type'] ?? $type) ?: 'Hinweis'),
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
        // Kann leer sein, wenn du keinen Blade-Output brauchst
        return view('livewire.user-alert');
    }
}
