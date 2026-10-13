<?php

namespace App\Support\EmailTemplates;

use App\Models\User;
use App\Support\Enums\EmailTemplateType;
use Closure;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An email the app sends, as listed on the Email Templates admin page.
 *
 * `sample` builds the real notification with sample data for a recipient, so previews and test
 * sends render exactly what users receive. It must not create valid tokens or signed links.
 */
class EmailTemplate
{
    /**
     * @param string $key Unique id used in the API (e.g. "password_reset").
     * @param string $triggeredBy The event that sends it (e.g. "user.password_reset").
     * @param Closure(User): Notification $sample
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly EmailTemplateType $type,
        public readonly string $triggeredBy,
        public readonly string $description,
        protected Closure $sample,
    ) {
    }

    public function notification(User $recipient): Notification
    {
        return ($this->sample)($recipient);
    }

    public function mail(User $recipient): MailMessage
    {
        return $this->notification($recipient)->toMail($recipient);
    }
}
