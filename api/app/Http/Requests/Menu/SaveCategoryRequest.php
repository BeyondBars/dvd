<?php

namespace App\Http\Requests\Menu;

use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create (POST, name required) or update (PUT, any subset of fields) a menu category.
 */
class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserAbility::ManageMenu->value);
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_visible' => ['sometimes', 'boolean'],
        ];
    }
}
