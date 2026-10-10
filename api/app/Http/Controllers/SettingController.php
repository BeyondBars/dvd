<?php

namespace App\Http\Controllers;

use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Models\Setting;
use App\Support\Enums\UserAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App-wide UI settings. Anyone can read them (the sign-in screens are themed too);
 * only users with the "manage settings" ability can change them.
 */
class SettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => Setting::values()]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        Setting::store($request->validated());

        return $this->show();
    }

    public function destroy(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(UserAbility::ManageSettings->value), 403);

        Setting::reset();

        return $this->show();
    }
}
