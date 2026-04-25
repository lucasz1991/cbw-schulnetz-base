<?php

namespace App\Services\Atera;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AteraService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected string $technicianEmail;

    public function __construct()
    {
        $this->baseUrl = (string) (Setting::getValue('atera', 'base_url') ?: 'https://app.atera.com');
        $this->apiKey = Setting::getValue('atera', 'api_key');
        $this->technicianEmail = (string) (Setting::getValue('atera', 'technician_email') ?: 'support@cbw-weiterbildung.de');
    }

    public function createPortalTicketForUser(User $user, string $title, string $priority, string $message): array
    {
        if (! is_string($this->apiKey) || trim($this->apiKey) === '') {
            return [
                'ok' => false,
                'status' => null,
                'message' => 'Der Atera API-Key ist nicht konfiguriert.',
            ];
        }

        $contact = $this->findContactByEmail((string) $user->email);
        $nameParts = $this->splitName((string) $user->name);

        $payload = [
            'TicketTitle' => trim($title),
            'Description' => $this->buildDescription($user, trim($message)),
            'TicketPriority' => $priority,
            'TicketType' => 'Request',
            'TechnicianEmail' => $this->technicianEmail,
        ];

        if ($contact !== null && isset($contact['EndUserID'])) {
            $payload['EndUserID'] = (int) $contact['EndUserID'];
        } else {
            // Keine separate Kontaktanlage: Atera bekommt die Benutzerdaten direkt am Ticket.
            $payload['EndUserFirstName'] = $nameParts['first_name'];
            $payload['EndUserLastName'] = $nameParts['last_name'];
            $payload['EndUserEmail'] = (string) $user->email;
        }

        return $this->request('POST', '/api/v3/tickets', $payload);
    }

    public function findContactByEmail(string $email): ?array
    {
        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $response = $this->request('GET', '/api/v3/contacts', [], [
            'searchOptions.email' => $email,
            'itemsInPage' => 50,
        ]);

        if (! ($response['ok'] ?? false)) {
            return null;
        }

        $items = data_get($response, 'data.items', []);

        if (! is_array($items) || $items === []) {
            return null;
        }

        $exact = collect($items)->first(function (mixed $item) use ($email): bool {
            return is_array($item)
                && strcasecmp((string) data_get($item, 'Email', ''), $email) === 0;
        });

        if (is_array($exact)) {
            return $exact;
        }

        $first = $items[0] ?? null;

        return is_array($first) ? $first : null;
    }

    protected function buildDescription(User $user, string $message): string
    {
        return implode("\n", [
            'Technische Anfrage aus dem CBW Schulnetz',
            '',
            'Beschreibung:',
            $message,
            '',
            'Benutzerdaten:',
            'Name: ' . (string) $user->name,
            'E-Mail: ' . (string) $user->email,
            'Benutzer-ID: ' . (string) $user->id,
            'Rolle: ' . (string) ($user->role ?? 'unbekannt'),
        ]);
    }

    protected function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ($name === '') {
            return [
                'first_name' => 'Schulnetz',
                'last_name' => 'Teilnehmer',
            ];
        }

        $parts = explode(' ', $name);
        $firstName = array_shift($parts) ?: 'Schulnetz';
        $lastName = trim(implode(' ', $parts));

        return [
            'first_name' => $firstName,
            'last_name' => $lastName !== '' ? $lastName : 'Teilnehmer',
        ];
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-API-KEY' => (string) $this->apiKey,
            ]);
    }

    protected function request(string $method, string $path, array $payload = [], array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        try {
            $response = match (strtoupper($method)) {
                'GET' => $this->http()->get($url, $query),
                'POST' => $this->http()->withQueryParameters($query)->post($url, $payload),
                'PUT' => $this->http()->withQueryParameters($query)->put($url, $payload),
                'PATCH' => $this->http()->withQueryParameters($query)->patch($url, $payload),
                'DELETE' => $this->http()->delete($url, $query),
                default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
            };

            $status = $response->status();
            $json = $response->json();

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'status' => $status,
                    'data' => $json,
                    'headers' => $response->headers(),
                ];
            }

            $message = is_array($json)
                ? ((string) ($json['message'] ?? $json['error'] ?? 'Atera request failed'))
                : 'Atera request failed';

            Log::warning('Atera request failed', [
                'method' => $method,
                'url' => $url,
                'status' => $status,
                'message' => $message,
                'payload' => $payload,
                'query' => $query,
                'response' => $json,
            ]);

            return [
                'ok' => false,
                'status' => $status,
                'message' => $message,
                'data' => $json,
            ];
        } catch (Throwable $exception) {
            Log::error('Atera request exception', [
                'method' => $method,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => null,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
