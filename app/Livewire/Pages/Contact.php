<?php

namespace App\Livewire\Pages;

use App\Services\Atera\AteraService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Contact extends Component
{
    public string $name = '';
    public string $email = '';
    public string $subject = '';
    public string $priority = 'Medium';
    public string $message = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = (string) ($user?->name ?? '');
        $this->email = (string) ($user?->email ?? '');
    }

    public function send(AteraService $ateraService): void
    {
        $user = Auth::user();

        if (! $user) {
            session()->flash('error', 'Du musst angemeldet sein, um eine technische Anfrage zu senden.');

            return;
        }

        $this->name = trim((string) ($user->name ?: $this->name));
        $this->email = trim((string) ($user->email ?: $this->email));

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:Low,Medium,High,Critical'],
            'message' => ['required', 'string'],
        ], [
            'name.required' => 'Bitte gib deinen Namen ein.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            'email.max' => 'Die E-Mail-Adresse darf maximal 255 Zeichen lang sein.',
            'subject.required' => 'Bitte gib einen Ticket-Titel ein.',
            'subject.max' => 'Der Ticket-Titel darf maximal 255 Zeichen lang sein.',
            'priority.required' => 'Bitte wähle eine Priorität aus.',
            'priority.in' => 'Bitte wähle eine gültige Priorität aus.',
            'message.required' => 'Bitte gib eine Problembeschreibung ein.',
        ]);

        $result = $ateraService->createPortalTicketForUser(
            $user,
            $this->subject,
            $this->priority,
            $this->message
        );

        if (! ($result['ok'] ?? false)) {
            session()->flash('error', (string) ($result['message'] ?? 'Das Ticket konnte nicht erstellt werden. Bitte versuche es später erneut.'));

            return;
        }

        session()->flash('success', 'Deine technische Anfrage wurde als Ticket an die IT-Abteilung übermittelt.');

        $this->reset(['subject', 'message']);
        $this->priority = 'Medium';
    }

    public function render()
    {
        return view('livewire.pages.contact')
            ->layout('layouts.app');
    }
}
