@component('mail::message')
# Reset your password

Hi {{ $name }},

We received a request to reset the password for your {{ config('app.name') }} account. Click the button below to choose a new one. The link works for 60 minutes.

@component('mail::button', ['url' => $url])
Reset password
@endcomponent

If you didn't ask for this, you can ignore this email.
@endcomponent
