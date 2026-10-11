<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An order from the customer website. Only ids and quantities are sent: prices come from the menu.
 */
class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:80'],
            // Any phone number customers can be called back on: digits, spaces, +, - and brackets.
            'customer_phone' => ['required', 'string', 'regex:/^\+?[0-9 ()\-]{8,20}$/'],
            'customer_email' => ['nullable', 'email', 'max:120'],
            // Null or missing means as soon as possible.
            'pickup_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:'.config('ordering.max_lines')],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('ordering.max_quantity')],
            'items.*.option_ids' => ['sometimes', 'array', 'max:40'],
            'items.*.option_ids.*' => ['integer', 'distinct'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_phone.regex' => 'Enter a phone number we can call you on.',
            'items.required' => 'Your order is empty.',
            'items.min' => 'Your order is empty.',
        ];
    }
}
