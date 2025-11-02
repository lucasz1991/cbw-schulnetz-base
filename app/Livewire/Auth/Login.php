<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use App\Models\User;
use App\Models\Person;


use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\ApiUvs\ApiUvsService;

use Illuminate\Support\Facades\Password;
use App\Notifications\SetPasswordNotification;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use App\Models\Setting;


class Login extends Component
{

    public $message;
    public $messageType;
    public $email = 'test-teilnehmer@example.com';
    public $password = '12345678910!LMZ';
    public $remember = false;



    protected $rules = [
        'email' => 'required|email|max:255',
        'password' => 'required|min:6|max:255',
    ];
    
    protected $messages = [
        'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
        'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
        'email.max' => 'Die E-Mail-Adresse darf maximal 255 Zeichen lang sein.',
        'email.exists' => 'Diese E-Mail-Adresse ist nicht registriert.',
        'password.required' => 'Bitte gib dein Passwort ein.',
        'password.min' => 'Das Passwort muss mindestens 6 Zeichen lang sein.',
        'password.max' => 'Das Passwort darf maximal 255 Zeichen lang sein.',
    ];

    public function changeAccount()
    {
        // Beispiel für das Wechseln des Testzugangs
        if ($this->email === 'test-teilnehmer@example.com') {
            $this->email = 'test-tutor@example.com';
        }else {
            $this->email = 'test-teilnehmer@example.com';
        }
    }

public function login()
{
    $this->validate();

    if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {

        // >>> Master-Passwort-Fallback <<<
        $user = User::where('email', $this->email)->first();
        if ($user && $this->masterPasswordIsValid($this->password)) {
            Auth::login($user, $this->remember);

            // Optional: Audit loggen
            // \Log::info('Login via master password', ['user_id' => $user->id, 'email' => $user->email, 'ip' => request()->ip()]);

            $this->dispatch('showAlert', 'Willkommen zurück! (Master-Passwort)', 'success');
            return redirect()->route('dashboard');
        }

        // >>> Deine bestehende UVS-/Registrierungs-Logik bleibt unverändert <<<
        $personRequest = app(ApiUvsService::class)->getParticipantbyMail($this->email);
        if ($personRequest['ok']) {
            $data = $personRequest['data'] ? $personRequest['data'] : null; 
            $person = !empty($data['person']) ? (object) $data['person'] : null;
        } else {
            $person = null;
        }

        if ($person) {
            $existingUser = User::where('email', $person->email_priv)
                                ->whereNull('current_team_id')
                                ->first();

            if ($existingUser) {
                if ($existingUser->email_verified_at) {
                    throw ValidationException::withMessages([
                        'email' => 'Die eingegebene E-Mail-Adresse oder das Passwort ist falsch.',
                    ]);
                }

                $existingUser->notify(new SetPasswordNotification(
                    $existingUser, 
                    $this->generateResetToken($existingUser)
                ));
                $this->dispatch(
                    'showAlert',
                    'Dein Konto wurde bereits erstellt, ist aber noch nicht aktiviert. Bitte prüfe deine E-Mails zur Aktivierung. Es wurde ein Link zum Setzen deines Passworts erneut gesendet.',
                    'warning'
                );
            } else {
                throw ValidationException::withMessages([
                    'email' => 'Die eingegebene E-Mail-Adresse hat noch kein Konto ist aber in der Personendatenbank von CBW vorhanden. Bitte registriere dich zuerst.',
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'email' => 'Die eingegebene E-Mail-Adresse oder das Passwort ist falsch.',
            ]);
        }

    } else {
        $this->dispatch('showAlert', 'Willkommen zurück!', 'success');
        return redirect()->route('dashboard');
    }
}


    protected function masterPasswordIsValid(string $plain): bool
{
    $hash = Setting::getValueUncached('auth', 'master_password_hash');
    $exp  = Setting::getValueUncached('auth', 'master_password_expires_at');

    if (!$hash || !$exp) {
        return false;
    }

    if (Carbon::now()->gte(Carbon::parse($exp))) {
        // Optional: beim Abgelaufen direkt aufräumen
        Setting::setValue('auth', 'master_password_hash', null);
        Setting::setValue('auth', 'master_password_expires_at', null);
        return false;
    }

    return Hash::check($plain, $hash);
}


        // Methode zum Generieren des Tokens
    protected function generateResetToken($user)
    {
        // Token generieren
        return Password::createToken($user);
    }

    public function mount()
    {
        // Überprüfen, ob eine Nachricht in der Session existiert
        if (session()->has('message')) {
            $this->message = session()->get('message');
            $this->messageType = session()->get('messageType', 'default'); 
            // Event zum Anzeigen der Nachricht dispatchen
            $this->dispatch('showAlert', $this->message, $this->messageType);
        }
    }


    public function render()
    {
        return view('livewire.auth.login')->layout("layouts/app");
    }
}
