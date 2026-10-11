<?php

namespace App\Http\Requests\Menu;

use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Create (POST, category, name and price required) or update (PUT, any subset of fields) a menu item.
 */
class SaveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserAbility::ManageMenu->value);
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'menu_category_id' => [$required, 'required', 'integer', 'exists:menu_categories,id'],
            'name' => [$required, 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Up to $10,000.00, in cents.
            'price_cents' => [$required, 'required', 'integer', 'min:0', 'max:1000000'],
            'is_available' => ['sometimes', 'boolean'],
            // Replaces all of the item's choices when sent.
            'option_groups' => ['sometimes', 'array', 'max:10'],
            'option_groups.*.name' => ['required', 'string', 'max:60'],
            'option_groups.*.min_choices' => ['required', 'integer', 'min:0', 'max:20'],
            'option_groups.*.max_choices' => ['required', 'integer', 'min:1', 'max:20'],
            'option_groups.*.options' => ['required', 'array', 'min:1', 'max:20'],
            'option_groups.*.options.*.name' => ['required', 'string', 'max:60'],
            'option_groups.*.options.*.price_cents' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('option_groups', []) as $index => $group) {
                    $min = (int) ($group['min_choices'] ?? 0);
                    $max = (int) ($group['max_choices'] ?? 1);
                    $count = count((array) ($group['options'] ?? []));

                    if ($max < $min) {
                        $validator->errors()->add("option_groups.{$index}.max_choices", 'The most choices can\'t be fewer than the fewest.');
                    }

                    if ($count && $min > $count) {
                        $validator->errors()->add("option_groups.{$index}.min_choices", 'This needs more options than it has.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'price_cents.*' => 'Enter a price between $0.00 and $10,000.00.',
            'menu_category_id.*' => 'Choose a category.',
            'option_groups.*.options.required' => 'Add at least one option.',
            'option_groups.*.options.min' => 'Add at least one option.',
            'option_groups.*.options.*.price_cents.*' => 'Enter an extra price between $0.00 and $1,000.00.',
        ];
    }
}
