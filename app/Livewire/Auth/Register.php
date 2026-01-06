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
    public $email, $terms = false;
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
            ],
            [
                'email.required'   => 'Die E-Mail-Adresse ist erforderlich.',
                'email.email'      => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
                'email.unique'     => 'Für diese E-Mail-Adresse existiert bereits ein aktives Konto. Bitte nutze „Passwort vergessen“, um Zugang zu erhalten.',
            ]
        );

        // 2) Person aus UVS per Mail holen
        //    (jetzt: ggf. mehrere Personen, z. B. bei mehreren institut_id-Einträgen)
        $persons = [];
        try {
            $personRequest = app(ApiUvsService::class)->getParticipantbyMail($this->email);

            if (!empty($personRequest['ok']) && !empty($personRequest['data'])) {
                $data = $personRequest['data'];

                // a) Altes Format: einzelne Person unter 'person'
                if (!empty($data['person'])) {
                    $persons[] = (object) $data['person'];
                }

                // b) Neues Format: mehrere Personen unter 'persons'
                if (!empty($data['persons']) && is_array($data['persons'])) {
                    foreach ($data['persons'] as $p) {
                        $persons[] = (object) $p;
                    }
                }
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'email' => 'Es gab ein Problem beim Abgleich mit der Teilnehmerdatenbank. Bitte später erneut versuchen.',
            ]);
        }

        if (empty($persons)) {
            throw ValidationException::withMessages([
                'email' => 'Die angegebene E-Mail-Adresse wurde nicht in unserer Teilnehmerdatenbank gefunden. Bitte kontaktiere den Support, wenn du glaubst, dass dies ein Fehler ist.',
            ]);
        }

        // Duplikate aus der API nach person_id rausfiltern (falls der Endpoint mal doppelt liefert)
        $persons = collect($persons)
            ->unique('person_id')
            ->values()
            ->all();

        // "Hauptperson" für Status/Rollenermittlung = erste gefundene Person
        $person = $persons[0];

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

        // 6) Erstellen in Transaktion: User anlegen, Person(en) upserten + verknüpfen, Mail verschicken
        DB::transaction(function () use ($persons, $person, $role) {
            $randomPassword = Str::random(12);

            $newUser = User::create([
                'name'     => $person->vorname.' '.$person->nachname,
                'email'    => $person->email_priv ?? $this->email,
                'status'   => 1,
                'role'     => $role,
                'password' => bcrypt($randomPassword),
                // vorläufig in dev verifizieren
                'email_verified_at' => now(),
            ]);

            // Alle gefundenen Personen aus UVS durchlaufen und in unserer persons-Tabelle upserten
            // Alle gefundenen Personen aus UVS durchlaufen und in unserer persons-Tabelle upserten
            foreach ($persons as $p) {

                // 1. und einzige Priorität: person_id
                //    → wenn es diese person_id schon gibt, IMMER diesen Datensatz updaten
                //    → keine E-Mail-Vergleiche hier, weil die Mail in UVS „unsauber“ sein kann
                $personModel = Person::firstWhere('person_id', $p->person_id);

                $mapped = Person::mapFromUvsPayload($p, $role);

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
                    $newPerson = new Person(array_merge($mapped, [
                        'user_id' => $newUser->id,
                    ]));
                    $newPerson->save();
                }
            }


            // Passwort-Setzen-Mail versenden
            // $newUser->notify(new SetPasswordNotification($newUser, $this->generateResetToken($newUser)));
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
