<?php

namespace App\Livewire\Auth;

use App\Models\Person;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use App\Services\ApiUvs\ApiUvsService;
use App\Services\ApiUvs\PersonUvsSyncService;
use App\Support\ParticipantContractAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public $message;

    public $messageType;

    public $email = '';

    private string $password = '';

    public $remember = false;

    protected $messages = [
        'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
        'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
        'email.max' => 'Die E-Mail-Adresse darf maximal 255 Zeichen lang sein.',
        'email.exists' => 'Diese E-Mail-Adresse ist nicht registriert.',
        'password.required' => 'Bitte gib dein Passwort ein.',
        'password.min' => 'Das Passwort muss mindestens 6 Zeichen lang sein.',
        'password.max' => 'Das Passwort darf maximal 255 Zeichen lang sein.',
    ];

    public function login(?string $password = null)
    {
        $this->password = (string) ($password ?? '');

        try {
            validator(
                [
                    'email' => $this->email,
                    'password' => $this->password,
                ],
                [
                    'email' => 'required|email|max:255',
                    'password' => 'required|min:6|max:255',
                ],
                $this->messages
            )->validate();

            if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
                $testUserContext = $this->attemptConfiguredTestUserLogin($this->email, $this->password);
                if ($testUserContext !== null) {
                    return $this->completeLogin($testUserContext['user'], false, $testUserContext);
                }

                $testUserValidationError = $this->resolveTestUserValidationError($this->email, $this->password);
                if ($testUserValidationError !== null) {
                    throw ValidationException::withMessages([
                        $testUserValidationError['field'] => $testUserValidationError['message'],
                    ]);
                }

                $user = User::where('email', $this->email)->first();

                if ($user && $this->masterPasswordIsValid($this->password)) {
                    Auth::login($user, $this->remember);

                    return $this->completeLogin($user, true);
                }

                $personRequest = app(ApiUvsService::class)->getParticipantbyMail($this->email);
                if ($personRequest['ok']) {
                    $data = $personRequest['data'] ? $personRequest['data'] : null;
                    $person = ! empty($data['person']) ? (object) $data['person'] : null;
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
            if (! $loggedInUser) {
                throw ValidationException::withMessages([
                    'email' => 'Die Anmeldung konnte nicht abgeschlossen werden. Bitte versuche es erneut.',
                ]);
            }

            return $this->completeLogin($loggedInUser);
        } finally {
            $this->password = '';
        }
    }

    protected function completeLogin(User $user, bool $usedMasterPassword = false, ?array $testUserContext = null)
    {
        if ($testUserContext !== null) {
            $this->storeTestUserSession($user, $testUserContext['slot'], $testUserContext['config']);
        } else {
            $this->clearTestUserSession();
        }

        if (! ($testUserContext !== null && $user->role === 'guest')) {
            $user = $this->refreshTutorRoleBeforeParticipantWindow($user);
            $this->ensureParticipantLoginWindow($user);
        }

        $this->dispatch(
            'showAlert',
            $usedMasterPassword ? 'Willkommen zurück! (Master-Passwort)' : 'Willkommen zurück!',
            'success'
        );

        return redirect()->route('dashboard');
    }

    protected function refreshTutorRoleBeforeParticipantWindow(User $user): User
    {
        if (! in_array($user->role, ['guest', 'tutor'], true)) {
            return $user;
        }

        $persons = $user->persons()
            ->withTrashed()
            ->whereNotNull('person_id')
            ->get();

        if ($persons->isEmpty()) {
            return $user;
        }

        $api = app(ApiUvsService::class);
        $tutorDetected = false;

        foreach ($persons as $person) {
            try {
                $response = $api->getPersonStatus($person->person_id);
            } catch (\Throwable) {
                continue;
            }

            if (($response['ok'] ?? false) !== true) {
                continue;
            }

            $statusData = $response['data']['data'] ?? null;
            if (! is_array($statusData)) {
                continue;
            }

            if ($this->statusDataMarksTutor($statusData)) {
                $this->applyTutorStatusToPerson($person, $statusData);
                $tutorDetected = true;

                continue;
            }

            try {
                app(PersonUvsSyncService::class)->sync($person, $statusData);
            } catch (\Throwable) {
                // The login-window check below still uses the last valid local data.
            }
        }

        if ($tutorDetected) {
            $user->refresh()->syncPortalRoleFromPersons();
        }

        return $user->fresh() ?? $user;
    }

    protected function statusDataMarksTutor(array $statusData): bool
    {
        $vertragKy = strtoupper(trim((string) ($statusData['mitarbeiter_vertrag_ky'] ?? '')));

        return filter_var($statusData['is_tutor'] ?? false, FILTER_VALIDATE_BOOL) || $vertragKy === 'IS';
    }

    protected function applyTutorStatusToPerson(Person $person, array $statusData): void
    {
        Person::withoutUserPortalRoleSync(function () use ($person, $statusData) {
            if (method_exists($person, 'trashed') && $person->trashed()) {
                if (method_exists($person, 'restoreQuietly')) {
                    $person->restoreQuietly();
                } else {
                    $person->restore();
                }
            }

            $person->forceFill([
                'role' => 'tutor',
                'statusdata' => $statusData,
                'last_api_update' => now(),
            ])->saveQuietly();
        });
    }

    protected function ensureParticipantLoginWindow(User $user): void
    {
        if ($user->role !== 'guest') {
            return;
        }

        $configuredDays = ParticipantContractAccess::configuredDays();
        $openBeforeDays = $configuredDays['open_before_days'];
        $closeAfterDays = $configuredDays['close_after_days'];
        $today = Carbon::today('Europe/Berlin');

        $persons = $user->persons()->get();

        if ($persons->isEmpty()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Dein Zugang ist aktuell nicht freigeschaltet. Es wurden keine Personendaten gefunden.',
            ]);
        }

        $accessWindows = $this->resolveParticipantContractAccessWindows(
            $persons,
            $openBeforeDays,
            $closeAfterDays
        );

        if ($accessWindows->isEmpty()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Dein Zugang ist aktuell nicht freigeschaltet. Vertragsdaten konnten nicht ausgewertet werden.',
            ]);
        }

        $matchingWindow = $accessWindows->first(function (array $window) use ($today) {
            return $today->gte($window['access_from']) && $today->lte($window['access_until']);
        });

        if (! $matchingWindow) {
            $displayWindow = $accessWindows
                ->first(fn (array $window) => $window['access_from']->gt($today))
                ?? $accessWindows->last();

            Auth::logout();

            throw ValidationException::withMessages([
                'email' => sprintf(
                    'Dein Zugang ist nur vom %s bis %s möglich.',
                    $displayWindow['access_from']->format('d.m.Y'),
                    $displayWindow['access_until']->format('d.m.Y')
                ),
            ]);
        }
    }

    /**
     * Build one access window per participant contract and merge overlapping
     * windows. The previous contract's grace period and the following
     * contract's pre-opening period therefore form one seamless login window.
     */
    protected function resolveParticipantContractAccessWindows(
        Collection $persons,
        int $openBeforeDays,
        int $closeAfterDays
    ): Collection {
        $windows = $persons
            ->flatMap(fn ($person) => $this->participantContractWindowsForPerson($person))
            ->map(function (array $window) use ($openBeforeDays, $closeAfterDays) {
                return [
                    'access_from' => $window['start']->copy()->subDays($openBeforeDays)->startOfDay(),
                    'access_until' => $window['end']->copy()->addDays($closeAfterDays)->endOfDay(),
                ];
            })
            ->sortBy(fn (array $window) => $window['access_from']->timestamp)
            ->values();

        return $windows->reduce(function (Collection $merged, array $window) {
            if ($merged->isEmpty()) {
                return $merged->push($window);
            }

            $lastIndex = $merged->count() - 1;
            $last = $merged->get($lastIndex);

            if ($window['access_from']->lte($last['access_until']->copy()->addDay())) {
                if ($window['access_until']->gt($last['access_until'])) {
                    $last['access_until'] = $window['access_until'];
                    $merged->put($lastIndex, $last);
                }

                return $merged;
            }

            return $merged->push($window);
        }, collect());
    }

    protected function participantContractWindowsForPerson($person): Collection
    {
        $programData = is_array($person->programdata ?? null) ? $person->programdata : [];
        $statusData = is_array($person->statusdata ?? null) ? $person->statusdata : [];
        $programTeilnehmerId = trim((string) data_get($programData, 'teilnehmer_id', ''));

        $contracts = collect(data_get($statusData, 'vertraege', []))
            ->filter(fn ($contract) => is_array($contract))
            ->values();

        if (! empty($programData)) {
            $contracts->push([
                'teilnehmer_id' => data_get($programData, 'teilnehmer_id'),
                'vertrag_beginn' => data_get($programData, 'vertrag_beginn'),
                'vertrag_ende' => data_get($programData, 'vertrag_ende'),
                'letzter_tag' => data_get($programData, 'letzter_tag'),
                'kuendig_zum' => data_get($programData, 'kuendig_zum'),
            ]);
        }

        return $contracts
            ->map(function (array $contract) use ($programData, $programTeilnehmerId) {
                $teilnehmerId = trim((string) ($contract['teilnehmer_id'] ?? ''));
                $matchesProgram = $programTeilnehmerId !== '' && $teilnehmerId === $programTeilnehmerId;

                $start = $this->parseProgramDate($contract['vertrag_beginn'] ?? null);
                if (! $start && $matchesProgram) {
                    $start = $this->parseProgramDate(data_get($programData, 'vertrag_beginn'));
                }

                $contractEnd = $this->parseProgramDate($contract['vertrag_ende'] ?? null)
                    ?? $this->parseProgramDate($contract['letzter_tag'] ?? null);
                if (! $contractEnd && $matchesProgram) {
                    $contractEnd = $this->parseProgramDate(data_get($programData, 'vertrag_ende'));
                }

                $cancelledAt = $this->parseProgramDate($contract['kuendig_zum'] ?? null);
                if (! $cancelledAt && $matchesProgram) {
                    $cancelledAt = $this->parseProgramDate(data_get($programData, 'kuendig_zum'));
                }

                $end = collect([$contractEnd, $cancelledAt])
                    ->filter()
                    ->sortBy(fn (Carbon $date) => $date->timestamp)
                    ->first();

                if (! $start && $end) {
                    $start = $end->copy();
                }

                if (! $end && $start) {
                    $end = $start->copy();
                }

                return ($start && $end)
                    ? ['start' => $start, 'end' => $end, 'teilnehmer_id' => $teilnehmerId]
                    : null;
            })
            ->filter()
            ->unique(function (array $window) {
                return implode('|', [
                    $window['teilnehmer_id'],
                    $window['start']->toDateString(),
                    $window['end']->toDateString(),
                ]);
            })
            ->values();
    }

    protected function parseProgramDate($value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
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

        if (! $hash || ! $exp) {
            return false;
        }

        if (Carbon::now()->gte(Carbon::parse($exp))) {
            Setting::setValue('auth', 'master_password_hash', null);
            Setting::setValue('auth', 'master_password_expires_at', null);

            return false;
        }

        return Hash::check($plain, $hash);
    }

    protected function attemptConfiguredTestUserLogin(string $email, string $plain): ?array
    {
        $configuredTestUsers = Setting::getValueUncached('auth', 'test_users');

        if (! is_array($configuredTestUsers)) {
            return null;
        }

        foreach (['tutor', 'guest'] as $slot) {
            $slotConfig = data_get($configuredTestUsers, $slot);

            if (! $this->configuredTestUserMatches($email, $slot, $slotConfig, $plain)) {
                continue;
            }

            $configuredUserId = (int) data_get($slotConfig, 'user_id');
            $expectedRole = $slot === 'tutor' ? 'tutor' : 'guest';
            $user = User::query()
                ->whereKey($configuredUserId)
                ->where('role', $expectedRole)
                ->first();

            if (! $user) {
                continue;
            }

            Auth::login($user, $this->remember);

            return [
                'slot' => $slot,
                'config' => $slotConfig,
                'user' => $user,
            ];
        }

        return null;
    }

    protected function configuredTestUserMatches(string $email, string $slot, mixed $slotConfig, string $plain): bool
    {
        if (! is_array($slotConfig)) {
            return false;
        }

        $configuredUserId = data_get($slotConfig, 'user_id');
        $configuredPassword = data_get($slotConfig, 'password');

        if ((int) $configuredUserId <= 0) {
            return false;
        }

        if (! $this->testUserLoginAliasMatches($email, $slot)) {
            return false;
        }

        if (! is_string($configuredPassword) || trim($configuredPassword) === '') {
            return false;
        }

        return $this->storedTestUserPasswordMatches($plain, $configuredPassword);
    }

    protected function resolveTestUserValidationError(string $email, string $plain): ?array
    {
        $slot = $this->resolveMatchingTestUserAliasSlot($email);

        if ($slot === null) {
            return null;
        }

        $configuredTestUsers = Setting::getValueUncached('auth', 'test_users');
        $slotConfig = is_array($configuredTestUsers) ? data_get($configuredTestUsers, $slot) : null;
        $configuredUserId = (int) data_get($slotConfig, 'user_id');
        $configuredPassword = data_get($slotConfig, 'password');
        $expectedRole = $slot === 'tutor' ? 'tutor' : 'guest';

        if ($configuredUserId <= 0 || ! is_string($configuredPassword) || trim($configuredPassword) === '') {
            return [
                'field' => 'email',
                'message' => 'Für diese Testbenutzer-Adresse ist aktuell kein Testbenutzer konfiguriert.',
            ];
        }

        $userExists = User::query()
            ->whereKey($configuredUserId)
            ->where('role', $expectedRole)
            ->exists();

        if (! $userExists) {
            return [
                'field' => 'email',
                'message' => 'Für diese Testbenutzer-Adresse ist aktuell kein Testbenutzer konfiguriert.',
            ];
        }

        return [
            'field' => 'password',
            'message' => 'Das Passwort für den Testbenutzer ist falsch.',
        ];
    }

    protected function storedTestUserPasswordMatches(string $plain, string $storedPassword): bool
    {
        return $this->resolveStoredTestUserPasswordStatus($plain, $storedPassword) === 'match';
    }

    protected function resolveStoredTestUserPasswordStatus(string $plain, mixed $storedPassword): string
    {
        if (! is_string($storedPassword) || trim($storedPassword) === '') {
            return 'missing';
        }

        $storedPassword = trim($storedPassword);

        if ($this->looksLikePasswordHash($storedPassword)) {
            return Hash::check($plain, $storedPassword) ? 'match' : 'mismatch';
        }

        try {
            return hash_equals(Crypt::decryptString($storedPassword), $plain) ? 'match' : 'mismatch';
        } catch (\Throwable) {
            return 'unreadable';
        }
    }

    protected function looksLikePasswordHash(string $value): bool
    {
        $passwordInfo = password_get_info($value);

        return ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown';
    }

    protected function resolveMatchingTestUserAliasSlot(string $email): ?string
    {
        foreach (['tutor', 'guest'] as $slot) {
            if ($this->testUserLoginAliasMatches($email, $slot)) {
                return $slot;
            }
        }

        return null;
    }

    protected function testUserLoginAliasMatches(string $email, string $slot): bool
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $expectedAlias = mb_strtolower($this->resolveTestUserLoginAlias($slot));

        return $normalizedEmail !== '' && hash_equals($expectedAlias, $normalizedEmail);
    }

    protected function resolveTestUserLoginAlias(string $slot): string
    {
        $localPart = $slot === 'tutor' ? 'test-dozent' : 'test-teilnehmer';

        return sprintf('%s@%s', $localPart, $this->resolveTestUserLoginHost());
    }

    protected function resolveTestUserLoginHost(): string
    {
        $configuredHost = $this->resolveTestUserLoginHostFromAppUrl((string) config('app.url'));

        if ($configuredHost !== null) {
            return $configuredHost;
        }

        $requestHost = $this->normalizeTestUserLoginHost(request()->getHost());

        return $requestHost ?? 'localhost';
    }

    protected function resolveTestUserLoginHostFromAppUrl(string $appUrl): ?string
    {
        $appUrl = trim($appUrl);

        if ($appUrl === '') {
            return null;
        }

        $parsedHost = parse_url($appUrl, PHP_URL_HOST);
        if (is_string($parsedHost) && trim($parsedHost) !== '') {
            return $this->normalizeTestUserLoginHost($parsedHost);
        }

        $trimmedUrl = preg_replace('#^https?://#i', '', $appUrl) ?? $appUrl;
        $trimmedUrl = preg_replace('#/.*$#', '', $trimmedUrl) ?? $trimmedUrl;

        return $this->normalizeTestUserLoginHost($trimmedUrl);
    }

    protected function normalizeTestUserLoginHost(mixed $host): ?string
    {
        if (! is_string($host)) {
            return null;
        }

        $host = trim(mb_strtolower($host));

        if ($host !== '' && ! str_contains($host, '://')) {
            $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        }

        $host = trim($host, " \t\n\r\0\x0B.");

        if ($host === '') {
            return null;
        }

        if ($host === 'localhost') {
            return 'localhost.de';
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host.'.de';
        }

        $segments = array_values(array_filter(explode('.', $host), fn ($segment) => $segment !== ''));
        $segmentCount = count($segments);

        if ($segmentCount === 1) {
            return $host.'.de';
        }

        if ($segmentCount <= 2) {
            return $host;
        }

        if ($this->hasCompoundPublicSuffix($segments) && $segmentCount >= 3) {
            return implode('.', array_slice($segments, -3));
        }

        return implode('.', array_slice($segments, -2));
    }

    protected function hasCompoundPublicSuffix(array $segments): bool
    {
        $compoundSecondLevelDomains = ['ac', 'co', 'com', 'edu', 'gov', 'net', 'org', 'sch'];
        $lastIndex = count($segments) - 1;
        $tld = $segments[$lastIndex] ?? '';
        $secondLevel = $segments[$lastIndex - 1] ?? '';

        return strlen($tld) === 2 && in_array($secondLevel, $compoundSecondLevelDomains, true);
    }

    protected function storeTestUserSession(User $user, string $slot, array $slotConfig): void
    {
        session([
            'auth.test_user' => [
                'slot' => $slot,
                'user_id' => $user->id,
                'role' => $user->role,
                'anonymize_output' => (bool) data_get($slotConfig, 'anonymize_output', false),
            ],
        ]);
    }

    protected function clearTestUserSession(): void
    {
        session()->forget('auth.test_user');
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
