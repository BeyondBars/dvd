<?php

namespace App\Http\Controllers;

use App\Http\Requests\Setting\UpdateOrderingSettingsRequest;
use App\Services\Ordering;
use App\Support\Enums\UserAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Online ordering settings: on/off, opening hours, prep time and who hears about new orders.
 */
class OrderingSettingController extends Controller
{
    public function show(Request $request, Ordering $ordering): JsonResponse
    {
        abort_unless($request->user()->can(UserAbility::ReadSettings->value), 403);

        return response()->json(['data' => $ordering->settings()]);
    }

    public function update(UpdateOrderingSettingsRequest $request, Ordering $ordering): JsonResponse
    {
        return response()->json(['data' => $ordering->update($request->validated())]);
    }
}
