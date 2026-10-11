<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Services\Ordering;
use Illuminate\Http\JsonResponse;

/**
 * Whether the truck is taking online orders right now, its hours and today's pickup times.
 */
class StoreController extends Controller
{
    public function __invoke(Ordering $ordering): JsonResponse
    {
        return response()->json(['data' => $ordering->status()]);
    }
}
