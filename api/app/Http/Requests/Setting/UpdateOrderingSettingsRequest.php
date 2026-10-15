<?php

namespace App\Http\Requests\Setting;

use App\Services\Ordering;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrderingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserAbility::ManageSettings->value);
    }

    public function rules(): array
    {
        $rules = [
            'ordering_enabled' => ['sometimes', 'boolean'],
            'opening_hours' => ['sometimes', 'array:'.implode(',', Ordering::DAYS)],
            'prep_minutes' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'busy_minutes' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'alert_email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'alert_phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9 ()\-]{8,20}$/'],
        ];

        foreach (Ordering::DAYS as $day) {
            $rules["opening_hours.{$day}"] = ['present', 'nullable', 'array:open,close'];
            $rules["opening_hours.{$day}.open"] = ["required_with:opening_hours.{$day}", 'date_format:H:i'];
            $rules["opening_hours.{$day}.close"] = ["required_with:opening_hours.{$day}", 'date_format:H:i'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('opening_hours', []) as $day => $hours) {
                    if (is_array($hours) && isset($hours['open'], $hours['close']) && $hours['close'] <= $hours['open']) {
                        $validator->errors()->add("opening_hours.{$day}.close", 'Closing time must be after opening time.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'alert_phone.regex' => 'Enter a phone number, e.g. 0435 331 122.',
        ];
    }
}
