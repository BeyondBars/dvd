<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Forgotten passwords for website customers. The emailed link opens the website's reset page.
 */
class PasswordController extends Controller
{
    /**
     * Always 204, so the form can't be used to find out who has an account.
     */
    public function forgot(Request $request): Response
    {
        $request->validate(['email' => ['required', 'email']]);

        PasswordBroker::broker('customers')->sendResetLink($request->only('email'));

        return response()->noContent();
    }

    public function reset(Request $request): Response
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $status = PasswordBroker::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            fn ($customer, string $password) => $customer->forceFill(['password' => $password])->save()
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => ['This reset link is invalid or has expired. Please ask for a new one.']]);
        }

        return response()->noContent();
    }
}
