<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Models\User;
use App\Services\Atera\AteraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AteraServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_ticket_with_existing_contact_id(): void
    {
        Setting::setValue('atera', 'base_url', 'https://app.atera.com');
        Setting::setValue('atera', 'api_key', 'test-key');
        Setting::setValue('atera', 'technician_email', 'support@cbw-weiterbildung.de');

        $user = User::factory()->create([
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'role' => 'guest',
        ]);

        Http::fake([
            'https://app.atera.com/api/v3/contacts*' => Http::response([
                'items' => [
                    [
                        'EndUserID' => 44,
                        'Email' => 'max@example.com',
                    ],
                ],
            ], 200),
            'https://app.atera.com/api/v3/tickets' => Http::response([], 201),
        ]);

        $result = app(AteraService::class)->createPortalTicketForUser(
            $user,
            'Druckerproblem',
            'High',
            'Der Drucker reagiert nicht.'
        );

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://app.atera.com/api/v3/tickets') {
                return false;
            }

            $data = $request->data();

            return ($data['EndUserID'] ?? null) === 44
                && ($data['TicketPriority'] ?? null) === 'High'
                && ($data['TechnicianEmail'] ?? null) === 'support@cbw-weiterbildung.de'
                && str_contains((string) ($data['Description'] ?? ''), 'Benutzer-ID:');
        });
    }

    public function test_it_falls_back_to_end_user_fields_without_separate_contact_creation(): void
    {
        Setting::setValue('atera', 'base_url', 'https://app.atera.com');
        Setting::setValue('atera', 'api_key', 'test-key');
        Setting::setValue('atera', 'technician_email', 'support@cbw-weiterbildung.de');

        $user = User::factory()->create([
            'name' => 'Erika Muster',
            'email' => 'erika@example.com',
            'role' => 'guest',
        ]);

        Http::fake([
            'https://app.atera.com/api/v3/contacts*' => Http::response([
                'items' => [],
            ], 200),
            'https://app.atera.com/api/v3/tickets' => Http::response([], 201),
        ]);

        $result = app(AteraService::class)->createPortalTicketForUser(
            $user,
            'Loginproblem',
            'Medium',
            'Ich kann mich nicht anmelden.'
        );

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://app.atera.com/api/v3/tickets') {
                return false;
            }

            $data = $request->data();

            return ($data['EndUserFirstName'] ?? null) === 'Erika'
                && ($data['EndUserLastName'] ?? null) === 'Muster'
                && ($data['EndUserEmail'] ?? null) === 'erika@example.com'
                && ! array_key_exists('EndUserID', $data);
        });
    }
}
