<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\PublicOrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The signed in customer's details and their orders.
 */
class AccountController extends Controller
{
    public function show(Request $request): CustomerResource
    {
        return new CustomerResource($request->user('customer'));
    }

    public function update(Request $request): CustomerResource
    {
        $customer = $request->user('customer');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'email' => ['sometimes', 'required', 'email', 'max:120', Rule::unique('customers')->ignore($customer)],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^\+?[0-9 ()\-]{8,20}$/'],
            // Changing the password needs the current one.
            'current_password' => ['required_with:password', 'current_password:customer'],
            'password' => ['sometimes', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'phone.regex' => 'Enter a phone number we can call you on.',
        ]);

        $customer->update(collect($data)->except('current_password')->all());

        return new CustomerResource($customer);
    }

    public function orders(Request $request): AnonymousResourceCollection
    {
        return PublicOrderResource::collection(
            $request->user('customer')->orders()->with('items')->latest('id')->paginate(10)
        );
    }
}
