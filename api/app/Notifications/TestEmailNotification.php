<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends an already rendered email template as a test, with "[TEST]" in front of its subject.
 */
class TestEmailNotification extends Notification
{
    public function __construct(
        protected MailMessage $mail,
    ) {
        $this->mail->subject('[TEST] '.$mail->subject);
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->mail;
    }
}
