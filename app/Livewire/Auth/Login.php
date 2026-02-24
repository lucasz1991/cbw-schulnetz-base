<?php

namespace App\Livewire\Auth;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public $message;
    public $messageType;
    public $email = '';
    public $password = '';
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

    public function login()
    {
        $this->validate();

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            // Master-Passwort-Fallback
            $user = User::where('email', $this->email)->first();
            if ($user && $this->masterPasswordIsValid($this->password)) {
                Auth::login($user, $this->remember);
                return $this->completeLogin($user, true);
            }

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

            return;
        }

        /** @var User|null $loggedInUser */
        $loggedInUser = Auth::user();
        if (!$loggedInUser) {
            throw ValidationException::withMessages([
                'email' => 'Die Anmeldung konnte nicht abgeschlossen werden. Bitte versuche es erneut.',
            ]);
        }

        return $this->completeLogin($loggedInUser);
    }

    protected function completeLogin(User $user, bool $usedMasterPassword = false)
    {
        $this->ensureParticipantLoginWindow($user);

        $this->dispatch(
            'showAlert',
            $usedMasterPassword ? 'Willkommen zurück! (Master-Passwort)' : 'Willkommen zurück!',
            'success'
        );

        return redirect()->route('dashboard');
    }

    protected function ensureParticipantLoginWindow(User $user): void
    {
        if ($user->role !== 'guest') {
            return;
        }

        $openBeforeDays = max(0, (int) (Setting::getValue('course_registration', 'open_before_start_days') ?? 14));
        $closeAfterDays = max(0, (int) (Setting::getValue('course_registration', 'close_after_end_days') ?? 7));
        $today = Carbon::today('Europe/Berlin');

        $persons = $user->persons()->get();

        if ($persons->isEmpty()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Dein Zugang ist aktuell nicht freigeschaltet. Es wurden keine Personendaten gefunden.',
            ]);
        }

        [$contractStart, $contractEnd] = $this->resolveParticipantContractWindowBounds($persons);

        if (!$contractStart || !$contractEnd) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Dein Zugang ist aktuell nicht freigeschaltet. Vertragsdaten konnten nicht ausgewertet werden.',
            ]);
        }

        $accessFrom = $contractStart->copy()->subDays($openBeforeDays)->startOfDay();
        $accessUntil = $contractEnd->copy()->addDays($closeAfterDays)->endOfDay();

        if ($today->lt($accessFrom) || $today->gt($accessUntil)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => sprintf(
                    'Dein Zugang ist nur vom %s bis %s möglich.',
                    $accessFrom->format('d.m.Y'),
                    $accessUntil->format('d.m.Y')
                ),
            ]);
        }
    }

    protected function resolveParticipantContractWindowBounds(Collection $persons): array
    {
        $starts = $persons
            ->map(function ($person) {
                $value = data_get($person->programdata, 'vertrag_beginn');
                return $this->parseProgramDate($value);
            })
            ->filter();

        $ends = $persons
            ->map(function ($person) {
                $value = data_get($person->programdata, 'vertrag_ende');
                return $this->parseProgramDate($value);
            })
            ->filter();

        $contractStart = $starts->isNotEmpty() ? $starts->sort()->first() : null;
        $contractEnd = $ends->isNotEmpty() ? $ends->sort()->last() : null;

        if (!$contractStart && $contractEnd) {
            $contractStart = $contractEnd->copy();
        }

        if (!$contractEnd && $contractStart) {
            $contractEnd = $contractStart->copy();
        }

        return [$contractStart, $contractEnd];
    }

    protected function parseProgramDate($value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'Europe/Berlin');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function masterPasswordIsValid(string $plain): bool
    {
        $hash = Setting::getValueUncached('auth', 'master_password_hash');
        $exp = Setting::getValueUncached('auth', 'master_password_expires_at');

        if (!$hash || !$exp) {
            return false;
        }

        if (Carbon::now()->gte(Carbon::parse($exp))) {
            Setting::setValue('auth', 'master_password_hash', null);
            Setting::setValue('auth', 'master_password_expires_at', null);
            return false;
        }

        return Hash::check($plain, $hash);
    }

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
        return view('livewire.auth.login')->layout('layouts/app');
    }
}
