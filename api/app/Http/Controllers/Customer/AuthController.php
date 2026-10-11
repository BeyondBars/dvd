<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RegisterRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\Traits\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sign up, sign in and sign out for website customers (session cookie, "customer" guard).
 * Signing out only ends the customer login, so a staff login in the same browser is kept.
 */
class AuthController extends Controller
{
    use ThrottlesLogins;

    protected int $maxAttempts = 5;

    protected int $decayMinutes = 15;

    public function register(RegisterRequest $request): CustomerResource
    {
        $customer = Customer::create([...$request->validated(), 'last_login_at' => now()]);

        Auth::guard('customer')->login($customer, true);
        $request->session()->regenerate();

        return new CustomerResource($customer);
    }

    public function login(Request $request): CustomerResource
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        $guard = Auth::guard('customer');

        if (!$guard->attempt($request->only('email', 'password'), (bool) ($credentials['remember'] ?? true))) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages(['email' => ['That email and password don\'t match an account.']]);
        }

        $request->session()->regenerate();
        $this->clearLoginAttempts($request);

        /** @var Customer $customer */
        $customer = $guard->user();
        $customer->update(['last_login_at' => now()]);

        return new CustomerResource($customer);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
