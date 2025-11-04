<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Models\Person;
use Livewire\Component;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Notifications\SetPasswordNotification;
use App\Services\ApiUvs\ApiUvsService;

class Register extends Component
{
    public $email, $username, $terms = false;
    public $message;
    public $messageType;
    protected ApiUvsService $apiService;
    public $personStatus;

    public function register()
    {
        // 1) Basis-Validierung (ohne unique auf email, damit wir Logik steuern können)
        $this->validate(
            [
                'email'    => ['required', 'email', Rule::unique('users', 'email')],
                'username' => ['required', 'string', 'max:255', Rule::unique('users', 'name')],
                'terms'    => 'accepted',
            ],
            [
                'email.required'   => 'Die E-Mail-Adresse ist erforderlich.',
                'email.email'      => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
                'email.unique'     => 'Für diese E-Mail-Adresse existiert bereits ein aktives Konto. Bitte nutze „Passwort vergessen“, um Zugang zu erhalten.',

                'username.required' => 'Der Benutzername ist erforderlich.',
                'username.string'   => 'Der Benutzername muss eine Zeichenkette sein.',
                'username.max'      => 'Der Benutzername darf maximal 255 Zeichen lang sein.',
                'username.unique'   => 'Dieser Benutzername wird bereits verwendet.',

                'terms.accepted'    => 'Sie müssen den AGBs und der Datenschutzerklärung zustimmen.',
            ]
        );

        // 2) Person aus UVS per Mail holen
        $person = null;
        try {
            $personRequest = app(ApiUvsService::class)->getParticipantbyMail($this->email);
            if (!empty($personRequest['ok']) && !empty($personRequest['data']['person'])) {
                $person = (object) $personRequest['data']['person'];
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'email' => 'Es gab ein Problem beim Abgleich mit der Teilnehmerdatenbank. Bitte später erneut versuchen.',
            ]);
        }

        if (!$person) {
            throw ValidationException::withMessages([
                'email' => 'Die angegebene E-Mail-Adresse wurde nicht in unserer Teilnehmerdatenbank gefunden. Bitte kontaktiere den Support, wenn du glaubst, dass dies ein Fehler ist.',
            ]);
        }

        // 3) Benutzer-Existenz prüfen:
        //    a) Unvollständiger Benutzer (z. B. Onboarding nicht abgeschlossen)
        $existingIncomplete = User::where('email', $person->email_priv ?? $this->email)
            ->whereNull('current_team_id')
            ->first();

        if ($existingIncomplete) {
            // Link zum Setzen des Passworts erneut senden
             // $existingIncomplete->notify(new SetPasswordNotification($existingIncomplete, $this->generateResetToken($existingIncomplete)));

                $this->dispatch('showAlert', [
                    'type' => 'warning',
                    'title' => 'Konto bereits vorhanden',
                    'text' => 'Dein Konto wurde bereits erstellt, ist aber noch nicht aktiviert. 
                            Wir haben dir den Link zum Setzen deines Passworts erneut gesendet.',
                    'confirmText' => 'Zum Login',
                    'allowOutsideClick' => false,
                    'redirectTo' => route('login'),
                    'redirectOn' => 'confirm',
                ]);
            return;
        }

        //    b) Bereits aktiver/normaler Benutzer vorhanden -> Registrierung blockieren
        $existingActive = User::where('email', $person->email_priv ?? $this->email)->first();
        if ($existingActive) {
            throw ValidationException::withMessages([
                'email' => 'Für diese E-Mail-Adresse existiert bereits ein aktives Konto. Bitte nutze „Passwort vergessen“, um Zugang zu erhalten.',
            ]);
        }

        // 4) PersonStatus laden (für Rollenableitung)
        $this->apiService   = app(ApiUvsService::class);
        $this->personStatus = $this->apiService->getPersonStatus($person->person_id) ?? null;

        if (!$this->personStatus || !isset($this->personStatus['data']['data'])) {
            throw ValidationException::withMessages([
                'email' => 'Es gab ein Problem beim Abrufen des Status der Person. Bitte später erneut versuchen.',
            ]);
        }

        $statusData = $this->personStatus['data']['data'];
        if (!array_key_exists('mitarbeiter_nr', $statusData)) {
            throw ValidationException::withMessages([
                'email' => 'Der Status der Person ist ungültig. Bitte versuchen Sie es später erneut oder kontaktieren Sie den Support.',
            ]);
        }

        // 5) Rolle bestimmen
        $role = $statusData['mitarbeiter_nr'] !== null ? 'tutor' : 'guest';

        // 6) Erstellen in Transaktion: User anlegen, Person upserten + verknüpfen, Mail verschicken
        DB::transaction(function () use ($person, $role) {
            $randomPassword = Str::random(12);

            $newUser = User::create([
                'name'     => $this->username,
                'email'    => $person->email_priv ?? $this->email,
                'status'   => 1,
                'role'     => $role,
                'password' => bcrypt($randomPassword),
                // vorläufig in dev verifizieren
                'email_verified_at' => now(),
            ]);

            // Person anhand person_id (präferiert) oder email_priv finden
            $personModel = Person::where('person_id', $person->person_id)->first()
                ?: (isset($person->email_priv)
                    ? Person::whereNotNull('email_priv')->where('email_priv', $person->email_priv)->first()
                    : null);

            $mapped = $this->mapPersonPayload($person, $role);

            if ($personModel) {
                // Update vorhandener Datensatz
                $personModel->fill($mapped);

                // user_id nur setzen, wenn noch frei (niemals stillschweigend überschreiben)
                if (empty($personModel->user_id)) {
                    $personModel->user_id = $newUser->id;
                }

                $personModel->save();
            } else {
                // Neu anlegen inkl. Verknüpfung
                Person::create(array_merge($mapped, [
                    'user_id' => $newUser->id,
                ]));
            }

            // Passwort-Setzen-Mail versenden
            //$newUser->notify(new SetPasswordNotification($newUser, $this->generateResetToken($newUser)));
        });

        // 7) Erfolgsmeldung
$this->dispatch('showAlert', [
    'type' => 'success',
    'title' => 'Konto erstellt',
    'text' => 'Bitte prüfe deine E-Mails, um dein Passwort zu setzen und dein Konto zu aktivieren.',
    'confirmText' => 'Zum Login',
    'allowOutsideClick' => false,
    'redirectTo' => route('login'),
    'redirectOn' => 'confirm', 
]);



    }

    // === Hilfsfunktionen ===

    protected function generateResetToken($user)
    {
        return Password::createToken($user);
    }

    private function safeDate(?string $value, string $mode = 'date'): ?string
    {
        try {
            if (!$value) return null;
            return $mode === 'datetime'
                ? Carbon::parse($value)->toDateTimeString()
                : Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function mapPersonPayload(object $p, string $role): array
    {
        return [
            'person_id'        => $p->person_id,
            'institut_id'      => $p->institut_id ?? null,
            'person_nr'        => $p->person_nr ?? null,
            'role'             => $role,
            'status'           => $p->status ?? null,
            'upd_date'         => $this->safeDate($p->upd_date ?? null, 'datetime'),
            'nachname'         => $p->nachname ?? null,
            'vorname'          => $p->vorname ?? null,
            'geschlecht'       => $p->geschlecht ?? null,
            'titel_kennz'      => $p->titel_kennz ?? null,
            'nationalitaet'    => $p->nationalitaet ?? null,
            'familien_stand'   => $p->familien_stand ?? null,
            'geburt_datum'     => $this->safeDate($p->geburt_datum ?? null, 'date'),
            'geburt_name'      => $p->geburt_name ?? null,
            'geburt_land'      => $p->geburt_land ?? null,
            'geburt_ort'       => $p->geburt_ort ?? null,
            'lkz'              => $p->lkz ?? null,
            'plz'              => $p->plz ?? null,
            'ort'              => $p->ort ?? null,
            'strasse'          => $p->strasse ?? null,
            'adresszusatz1'    => $p->adresszusatz1 ?? null,
            'adresszusatz2'    => $p->adresszusatz2 ?? null,
            'plz_pf'           => $p->plz_pf ?? null,
            'postfach'         => $p->postfach ?? null,
            'plz_gk'           => $p->plz_gk ?? null,
            'telefon1'         => $p->telefon1 ?? null,
            'telefon2'         => $p->telefon2 ?? null,
            'person_kz'        => $p->person_kz ?? null,
            'plz_alt'          => $p->plz_alt ?? null,
            'ort_alt'          => $p->ort_alt ?? null,
            'strasse_alt'      => $p->strasse_alt ?? null,
            'telefax'          => $p->telefax ?? null,
            'kunden_nr'        => $p->kunden_nr ?? null,
            'stamm_nr_aa'      => $p->stamm_nr_aa ?? null,
            'stamm_nr_bfd'     => $p->stamm_nr_bfd ?? null,
            'stamm_nr_sons'    => $p->stamm_nr_sons ?? null,
            'stamm_nr_kst'     => $p->stamm_nr_kst ?? null,
            'kostentraeger'    => $p->kostentraeger ?? null,
            'bkz'              => $p->bkz ?? null,
            'email_priv'       => $p->email_priv ?? null,
            'email_cbw'        => $p->email_cbw ?? null,
            'geb_mmtt'         => $p->geb_mmtt ?? null,
            'org_zeichen'      => $p->org_zeichen ?? null,
            'personal_nr'      => $p->personal_nr ?? null,
            'kred_nr'          => $p->kred_nr ?? null,
            'angestellt_von'   => $this->safeDate($p->angestellt_von ?? null, 'datetime'),
            'angestellt_bis'   => $this->safeDate($p->angestellt_bis ?? null, 'datetime'),
            'leer'             => $p->leer ?? null,
            'last_api_update'  => now(),
        ];
    }

    public function mount()
    {
        if (session()->has('message')) {
            $this->message = session()->get('message');
            $this->messageType = session()->get('messageType', 'default');
            $this->dispatch('showAlert', $this->message, $this->messageType);
        }
    }

    public function render()
    {
        return view('livewire.auth.register')->layout('layouts/app');
    }
}
