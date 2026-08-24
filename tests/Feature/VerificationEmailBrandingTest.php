<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationEmailBrandingTest extends TestCase
{
    public function test_the_custom_verification_notification_uses_the_current_cbw_logo_and_a_valid_signed_url(): void
    {
        $user = new User([
            'name' => 'Test Teilnehmer',
            'email' => 'teilnehmer@example.test',
        ]);
        $user->id = 42;

        $mail = (new CustomVerifyEmail)->toMail($user);
        $html = (string) $mail->render();
        $logoSource = 'data:image/png;base64,'.base64_encode(
            file_get_contents(public_path('site-images/logo.png'))
        );

        $this->assertStringContainsString($logoSource, $html);
        $this->assertStringContainsString('CBW College Berufliche Weiterbildung', $html);
        $this->assertStringContainsString('/email/verify/42/', $mail->actionUrl);
        $this->assertTrue(URL::hasValidSignature(Request::create($mail->actionUrl)));
    }

    public function test_all_model_driven_verification_sends_use_the_custom_notification(): void
    {
        Notification::fake();

        $user = new User([
            'name' => 'Test Teilnehmer',
            'email' => 'teilnehmer@example.test',
        ]);
        $user->id = 42;

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, CustomVerifyEmail::class);
    }
}
