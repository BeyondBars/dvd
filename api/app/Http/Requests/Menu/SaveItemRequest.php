<?php

namespace App\Http\Requests\Menu;

use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    public function messages(): array
    {
        return [
            'price_cents.*' => 'Enter a price between $0.00 and $10,000.00.',
            'menu_category_id.*' => 'Choose a category.',
        ];
    }
}
