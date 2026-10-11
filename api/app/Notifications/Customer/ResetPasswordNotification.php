<?php

namespace App\Notifications\Customer;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset for a website customer. The link opens the website's reset page.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Customer $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(email_subject('Reset your password'))
            ->markdown('mail.customer.reset_password', [
                'name' => $notifiable->name,
                'url' => sprintf(
                    '%s/reset-password?token=%s&email=%s',
                    rtrim((string) config('ordering.website_url'), '/'),
                    $this->token,
                    rawurlencode($notifiable->email)
                ),
            ]);
    }
}
