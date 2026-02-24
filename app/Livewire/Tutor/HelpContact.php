<?php

namespace App\Livewire\Tutor;

use App\Models\Setting;
use App\Notifications\ContactFormSubmitted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;

class HelpContact extends Component
{
    public $subject = '';
    public $message = '';

    public function send(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ], [
            'subject.required' => 'Bitte gib einen Betreff ein.',
            'subject.string' => 'Der Betreff muss aus Zeichen bestehen.',
            'subject.max' => 'Der Betreff darf maximal 255 Zeichen lang sein.',
            'message.required' => 'Bitte gib deine Nachricht ein.',
            'message.string' => 'Die Nachricht muss aus Zeichen bestehen.',
        ]);

        $user = Auth::user();

        if (!$user) {
            session()->flash('error', 'Du musst angemeldet sein, um eine Nachricht zu senden.');
            return;
        }

        try {
            $adminEmailFromSettings = Setting::getValue('mails', 'admin_email');
            $superAdminEmail = env('SUPER_ADMIN_MAIL');

            $recipients = array_values(array_unique(array_filter([
                $adminEmailFromSettings,
                $superAdminEmail,
            ], fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))));

            foreach ($recipients as $recipient) {
                Notification::route('mail', $recipient)->notify(
                    new ContactFormSubmitted($user->name, $user->email, '[Dozenten-Hilfe] ' . $this->subject, $this->message)
                );
            }

            session()->flash('success', 'Vielen Dank! Deine Nachricht wurde an den Support gesendet.');
            $this->reset(['subject', 'message']);
        } catch (\Swift_TransportException $e) {
            session()->flash('error', 'Die E-Mail konnte nicht gesendet werden. Bitte versuche es später erneut.');
        } catch (\Exception $e) {
            session()->flash('error', 'Ein Fehler ist aufgetreten. Bitte versuche es später erneut.');
        }
    }

    public function render()
    {
        return view('livewire.tutor.help-contact')
            ->layout('layouts.app-tutor');
    }
}

