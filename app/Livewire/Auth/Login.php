<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use App\Models\User;
use App\Models\Person;


use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Services\ApiUvs\ApiUvsService;

use Illuminate\Support\Facades\Password;
use App\Notifications\CustomResetPasswordNotification;

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
        'password.min' => 'Das Passwort muss mindestens 6 Zeichen l
        
        ang sein.',
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
            // Wenn die Authentifizierung fehlschlägt, Person-Tabelle überprüfen
            $personRequest = app(ApiUvsService::class)->getParticipantbyMail($this->email);
            if ($personRequest['ok']) {
                $data = $personRequest['data'] ? $personRequest['data'] : null; 
                $person = !empty($data['person']) ? (object) $data['person'] : null;
            } else {
                $person = null;
            }
            if ($person) {
                // Prüfen, ob bereits ein User-Eintrag mit dieser E-Mail existiert, aber noch nicht aktiviert wurde
                $existingUser = User::where('email', $person->email_priv)
                                    ->whereNull('current_team_id')
                                    ->first();

                if ($existingUser) {
                    if ($existingUser->email_verified_at) {
                        throw ValidationException::withMessages([
                            'email' => 'Die eingegebene E-Mail-Adresse oder das Passwort ist falsch.',
                        ]);
                    }
                    // Bestehender unvollständiger Benutzer – Hinweis zur E-Mail-Aktivierung
                    $existingUser->notify(new CustomResetPasswordNotification($existingUser, $this->generateResetToken($existingUser)));
                    $this->dispatch(
                        'showAlert',
                        'Dein Konto wurde bereits erstellt, ist aber noch nicht aktiviert. Bitte prüfe deine E-Mails zur Aktivierung. Es wurde ein Link zum Setzen deines Passworts erneut gesendet.',
                        'warning'
                    );
                } else {
                    // Neuer Benutzer wird erstellt
                    $randomPassword = \Illuminate\Support\Str::random(12);
                    $newUser = User::create([
                        'name' => $person->vorname . ' ' . $person->nachname,
                        'email' => $person->email_priv,
                        'status' => 1,
                        'role' => 'guest',
                        'password' => bcrypt($randomPassword),
                    ]);

                    // Verknüpfe die Person mit dem neuen Benutzer
                    $newPerson = Person::create([
                        'user_id' => $newUser->id,
                        'person_id' => $person->person_id,
                        'institut_id' => $person->institut_id ?? null,
                        'person_nr' => $person->person_nr ?? null,
                        'status' => $person->status ?? null,
                        'upd_date' => $person->upd_date ?? null,
                        'nachname' => $person->nachname ?? null,
                        'vorname' => $person->vorname ?? null,
                        'geschlecht' => $person->geschlecht ?? null,
                        'titel_kennz' => $person->titel_kennz ?? null,
                        'nationalitaet' => $person->nationalitaet ?? null,
                        'familien_stand' => $person->familien_stand ?? null,
                        'geburt_datum' => $person->geburt_datum ?? null,
                        'geburt_name' => $person->geburt_name ?? null,
                        'geburt_land' => $person->geburt_land ?? null,
                        'geburt_ort' => $person->geburt_ort ?? null,
                        'lkz' => $person->lkz ?? null,
                        'plz' => $person->plz ?? null,
                        'ort' => $person->ort ?? null,
                        'strasse' => $person->strasse ?? null,
                        'adresszusatz1' => $person->adresszusatz1 ?? null,
                        'adresszusatz2' => $person->adresszusatz2 ?? null,
                        'plz_pf' => $person->plz_pf ?? null,
                        'postfach' => $person->postfach ?? null,
                        'plz_gk' => $person->plz_gk ?? null,
                        'telefon1' => $person->telefon1 ?? null,
                        'telefon2' => $person->telefon2 ?? null,
                        'person_kz' => $person->person_kz ?? null,
                        'plz_alt' => $person->plz_alt ?? null,
                        'ort_alt' => $person->ort_alt ?? null,
                        'strasse_alt' => $person->strasse_alt ?? null,
                        'telefax' => $person->telefax ?? null,
                        'kunden_nr' => $person->kunden_nr ?? null,
                        'stamm_nr_aa' => $person->stamm_nr_aa ?? null,
                        'stamm_nr_bfd' => $person->stamm_nr_bfd ?? null,
                        'stamm_nr_sons' => $person->stamm_nr_sons ?? null,
                        'stamm_nr_kst' => $person->stamm_nr_kst ?? null,
                        'kostentraeger' => $person->kostentraeger ?? null,
                        'bkz' => $person->bkz ?? null,
                        'email_priv' => $person->email_priv ?? null,
                        'email_cbw' => $person->email_cbw ?? null,
                        'geb_mmtt' => $person->geb_mmtt ?? null,
                        'org_zeichen' => $person->org_zeichen ?? null,
                        'personal_nr' => $person->personal_nr ?? null,
                        'kred_nr' => $person->kred_nr ?? null,
                        'angestellt_von' => $person->angestellt_von ?? null,
                        'angestellt_bis' => $person->angestellt_bis ?? null,
                        'leer' => $person->leer ?? null,
                        'last_api_update' => now(),
                    ]);

                    $newUser->notify(new CustomResetPasswordNotification($newUser, $this->generateResetToken($newUser)));
                    $this->dispatch(
                        'showAlert',
                        'Dies war dein erster Login-Versuch. Dein Konto wurde erstellt. Bitte prüfe deine E-Mails, um dein Passwort zu setzen und dein Konto zu aktivieren.',
                        'info'
                    );
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
