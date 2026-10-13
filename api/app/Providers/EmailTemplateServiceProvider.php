<?php

namespace App\Providers;

use App\Models\User;
use App\Models\UserVerificationCode;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\SuccessfulPasswordResetNotification;
use App\Notifications\Auth\TwoFactorVerificationNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Notifications\SignupVerifyEmailNotification;
use App\Support\EmailTemplates\EmailTemplate;
use App\Support\EmailTemplates\EmailTemplateRegistry;
use App\Support\Enums\EmailTemplateType;
use Illuminate\Support\ServiceProvider;

/**
 * Lists the emails shown on the Email Templates admin page. When an app adds an email, register
 * it here with sample data so admins can preview it and send a test.
 *
 * Sample notifications are sent to real inboxes, so they use placeholder tokens and links that
 * don't work, never real ones.
 */
class EmailTemplateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailTemplateRegistry::class);
    }

    public function boot(EmailTemplateRegistry $templates): void
    {
        $templates->register(new EmailTemplate(
            key: 'signup_verification',
            name: 'Sign-up Verification',
            type: EmailTemplateType::Transactional,
            triggeredBy: 'user.signup',
            description: 'Link sent to a new email address to start creating an account.',
            sample: fn () => new SignupVerifyEmailNotification(url()->app('/verify/sample-signature')),
        ));

        $templates->register(new EmailTemplate(
            key: 'email_verification',
            name: 'Verify Email Address',
            type: EmailTemplateType::Transactional,
            triggeredBy: 'user.email_changed',
            description: 'Link to confirm an email address when it changes or a user asks to resend it.',
            sample: fn () => new VerifyEmailNotification(['id' => 'sample', 'hash' => 'sample']),
        ));

        $templates->register(new EmailTemplate(
            key: 'user_invitation',
            name: 'User Invitation',
            type: EmailTemplateType::Transactional,
            triggeredBy: 'user.invited',
            description: 'Sent when an admin adds a user, with a link to choose their password.',
            sample: fn () => new ResetPasswordNotification('sample-token', invite: true),
        ));

        $templates->register(new EmailTemplate(
            key: 'password_reset',
            name: 'Password Reset',
            type: EmailTemplateType::Transactional,
            triggeredBy: 'user.password_reset_requested',
            description: 'Link to set a new password after "Forgot password".',
            sample: fn () => new ResetPasswordNotification('sample-token'),
        ));

        $templates->register(new EmailTemplate(
            key: 'password_reset_success',
            name: 'Password Changed',
            type: EmailTemplateType::Transactional,
            triggeredBy: 'user.password_reset',
            description: 'Confirms that a password was reset.',
            sample: fn () => new SuccessfulPasswordResetNotification(),
        ));

        $templates->register(new EmailTemplate(
            key: 'two_factor_code',
            name: 'Two-Factor Code',
            type: EmailTemplateType::Transactional,
            triggeredBy: 'user.two_factor_challenge',
            description: 'Sign-in code for users who use email two-factor verification.',
            sample: fn (User $user) => new TwoFactorVerificationNotification(
                new UserVerificationCode(['code' => '123456', 'method' => 'email']),
                'email',
            ),
        ));
    }
}
