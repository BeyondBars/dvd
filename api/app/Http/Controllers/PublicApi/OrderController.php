<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PlaceOrderRequest;
use App\Http\Resources\PublicOrderResource;
use App\Models\Order;
use App\Services\PlaceOrder;
use Illuminate\Http\JsonResponse;

/**
 * Orders from the customer website: place one, then follow its status by token.
 */
class OrderController extends Controller
{
    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): JsonResponse
    {
        // Orders from a signed in customer show up in their account.
        $order = $placeOrder($request->validated(), $request->user('customer'));

        return (new PublicOrderResource($order))->response()->setStatusCode(201);
    }

    public function show(string $token): PublicOrderResource
    {
        return new PublicOrderResource(Order::with('items')->where('public_token', $token)->firstOrFail());
    }
}
