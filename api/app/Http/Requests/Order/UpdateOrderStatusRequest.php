<?php

namespace App\Http\Requests\Order;

use App\Support\Enums\OrderStatus;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserAbility::ManageOrders->value);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $order = $this->route('order');
                $status = OrderStatus::tryFrom((string) $this->input('status'));

                if ($status && !$order->status->canMoveTo($status)) {
                    $validator->errors()->add('status', "A {$order->status->label()} order can't be marked {$status->label()}.");
                }
            },
        ];
    }
}
