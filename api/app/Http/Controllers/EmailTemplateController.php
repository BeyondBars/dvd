<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailTemplate\SendTestEmailRequest;
use App\Http\Resources\EmailTemplateResource;
use App\Models\User;
use App\Notifications\TestEmailNotification;
use App\Support\EmailTemplates\EmailTemplate;
use App\Support\EmailTemplates\EmailTemplateRegistry;
use App\Support\Enums\UserAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Lists the emails registered in App\Providers\EmailTemplateServiceProvider, previews them with
 * sample data and sends test copies.
 */
class EmailTemplateController extends Controller
{
    public function __construct(
        protected EmailTemplateRegistry $templates,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize(UserAbility::ManageEmailTemplates->value);

        $recipient = $this->sampleRecipient($request->user());

        return EmailTemplateResource::collection(
            collect($this->templates->all())
                ->map(fn (EmailTemplate $template) => new EmailTemplateResource($template, [
                    'subject' => $template->mail($recipient)->subject,
                ]))
                ->values()
        );
    }

    public function show(Request $request, string $template): EmailTemplateResource
    {
        $this->authorize(UserAbility::ManageEmailTemplates->value);

        $template = $this->findOrFail($template);
        $mail = $template->mail($this->sampleRecipient($request->user()));

        return new EmailTemplateResource($template, [
            'subject' => $mail->subject,
            'html' => (string) $mail->render(),
        ]);
    }

    /**
     * Send the template with sample data to the given address. Sent straight away (not queued)
     * so the admin sees a mail server error instead of a silent failure.
     */
    public function sendTest(SendTestEmailRequest $request, string $template): JsonResponse
    {
        $template = $this->findOrFail($template);
        $email = $request->input('email');
        $mail = $template->mail($this->sampleRecipient($request->user(), $email));

        try {
            Notification::route('mail', $email)->notifyNow(new TestEmailNotification($mail));
        } catch (TransportExceptionInterface $e) {
            report($e);

            return response()->json(['message' => 'The mail server rejected the test email. Check the MAIL_* settings.'], 502);
        }

        return response()->json(['message' => "Test email sent to {$email}."]);
    }

    protected function findOrFail(string $key): EmailTemplate
    {
        return $this->templates->find($key) ?? abort(404, 'Email template not found.');
    }

    /**
     * An unsaved copy of the admin, so samples show their name without using their real account
     * (no real verification links or tokens end up in test emails).
     */
    protected function sampleRecipient(User $user, ?string $email = null): User
    {
        return new User([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $email ?? $user->email,
        ]);
    }
}
