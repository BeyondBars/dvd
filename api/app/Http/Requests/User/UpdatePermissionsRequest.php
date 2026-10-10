<?php

namespace App\Http\Requests\User;

use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('managePermissions', $this->route('user'));
    }

    public function rules(): array
    {
        $abilities = array_column(UserAbility::cases(), 'value');

        return [
            // Keyed by ability name, e.g. {"read users": true, "write users": false}.
            'permissions' => ['required', 'array:'.implode(',', $abilities)],
            'permissions.*' => ['boolean'],
        ];
    }
}
