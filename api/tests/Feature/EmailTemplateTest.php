<?php

namespace Tests\Feature;

use App\Notifications\TestEmailNotification;
use App\Support\EmailTemplates\EmailTemplateRegistry;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function testStandardUserCannotSeeOrSendTemplates()
    {
        $this->beUser();

        $this->getJson('email-templates')->assertForbidden();
        $this->getJson('email-templates/password_reset')->assertForbidden();
        $this->postJson('email-templates/password_reset/test', ['email' => 'me@example.com'])->assertForbidden();
    }

    public function testAdminCanListEveryRegisteredTemplate()
    {
        $this->beAdmin(['first_name' => 'Ada']);

        $keys = array_keys(app(EmailTemplateRegistry::class)->all());

        $this->getJson('email-templates')
            ->assertSuccessful()
            ->assertJsonCount(count($keys), 'data')
            ->assertJsonPath('data.*.key', $keys)
            ->assertJsonFragment([
                'key' => 'password_reset',
                'name' => 'Password Reset',
                'type' => 'transactional',
                'triggered_by' => 'user.password_reset_requested',
                'subject' => email_subject('Reset Password'),
            ]);
    }

    public function testUserWithTheAbilityCanListTemplates()
    {
        $this->beUserAbleTo(UserAbility::ManageEmailTemplates->value);

        $this->getJson('email-templates')->assertSuccessful();
    }

    /**
     * Renders every template, so a broken view or notification fails here rather than in production.
     */
    public function testEveryTemplateRendersAPreview()
    {
        $this->beAdmin(['first_name' => 'Ada']);

        foreach (array_keys(app(EmailTemplateRegistry::class)->all()) as $key) {
            $html = $this->getJson("email-templates/{$key}")
                ->assertSuccessful()
                ->assertJsonPath('data.key', $key)
                ->json('data.html');

            $this->assertStringContainsString('<html', $html, "Template [{$key}] did not render.");
        }

        $this->getJson('email-templates/password_reset')->assertJsonPath('data.html', fn ($html) => str_contains($html, 'Hi Ada'));
    }

    public function testUnknownTemplateIsNotFound()
    {
        $this->beAdmin();

        $this->getJson('email-templates/nope')->assertNotFound();
        $this->postJson('email-templates/nope/test', ['email' => 'me@example.com'])->assertNotFound();
    }

    public function testAdminCanSendATestEmail()
    {
        Notification::fake();
        $this->beAdmin();

        $this->postJson('email-templates/password_reset/test', ['email' => 'qa@example.com'])
            ->assertSuccessful()
            ->assertJsonPath('message', 'Test email sent to qa@example.com.');

        Notification::assertSentTo(
            new AnonymousNotifiable,
            TestEmailNotification::class,
            function (TestEmailNotification $notification, $channels, $notifiable) {
                $mail = $notification->toMail($notifiable);

                return $notifiable->routes['mail'] === 'qa@example.com'
                    && $mail->subject === '[TEST] '.email_subject('Reset Password')
                    && str_contains($mail->actionUrl ?? (string) $mail->render(), 'sample-token');
            }
        );
    }

    public function testEveryTemplateCanBeSentAsATest()
    {
        Mail::fake();
        $this->beAdmin();

        foreach (array_keys(app(EmailTemplateRegistry::class)->all()) as $key) {
            $this->postJson("email-templates/{$key}/test", ['email' => 'qa@example.com'])->assertSuccessful();
        }
    }

    public function testTestEmailNeedsAValidAddress()
    {
        $this->beAdmin();

        $this->postJson('email-templates/password_reset/test', ['email' => 'not-an-email'])
            ->assertJsonValidationErrors(['email']);
    }
}
