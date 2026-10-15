<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Services\Ordering;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Live kitchen numbers for the website: orders cooking, the wait if you order now and how busy it is.
 * Cached briefly, since every visitor's page asks for it every 30 seconds.
 */
class KitchenController extends Controller
{
    public function __invoke(Ordering $ordering): JsonResponse
    {
        $data = Cache::remember('public-kitchen', config('ordering.wait.cache_seconds'), fn () => $ordering->kitchen());

        return response()->json(['data' => $data]);
    }
}
