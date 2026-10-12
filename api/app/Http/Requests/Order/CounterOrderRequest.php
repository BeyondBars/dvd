<?php

namespace App\Http\Requests\Order;

use App\Support\Enums\UserAbility;

/**
 * An order staff take at the truck. Same items as a website order; the customer's name and phone are optional.
 */
class CounterOrderRequest extends PlaceOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserAbility::ManageOrders->value);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['customer_email'], $rules['pickup_at']);

        return [
            ...$rules,
            'customer_name' => ['nullable', 'string', 'max:80'],
            'customer_phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ()\-]{8,20}$/'],
            // Already paid on EFTPOS Air before the order was entered.
            'paid' => ['sometimes', 'boolean'],
        ];
    }
}
