<?php

namespace App\Http\Requests\Menu;

use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new order for a list of categories or items: `ids` from first to last.
 */
class SortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserAbility::ManageMenu->value);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
