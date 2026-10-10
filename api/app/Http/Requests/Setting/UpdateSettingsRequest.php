<?php

namespace App\Http\Requests\Setting;

use App\Models\Setting;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(UserAbility::ManageSettings->value);
    }

    public function rules(): array
    {
        $color = ['sometimes', 'required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'app_name' => ['sometimes', 'required', 'string', 'max:60'],
            'primary_color' => $color,
            'sidebar_background' => $color,
            'sidebar_text' => $color,
            'page_background' => $color,
            'font_family' => ['sometimes', 'required', Rule::in(Setting::FONTS)],
            'border_radius' => ['sometimes', 'required', Rule::in(Setting::RADII)],
        ];
    }

    public function messages(): array
    {
        return [
            'regex' => 'The :attribute must be a hex colour like #4f46e5.',
        ];
    }
}
