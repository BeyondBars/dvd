<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Ordering;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\UserAbility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * The orders board: every order still being worked on, plus those finished today (truck time).
     * `?view=history` lists all orders, newest first, 25 a page.
     */
    public function index(Request $request, Ordering $ordering): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(UserAbility::ReadOrders->value), 403);

        if ($request->query('view') === 'history') {
            return OrderResource::collection(Order::with('items')->latest('id')->paginate(25));
        }

        $startOfToday = $ordering->now()->startOfDay()->utc();

        return OrderResource::collection(
            Order::with('items')
                ->where(fn ($query) => $query
                    ->whereIn('status', OrderStatus::open())
                    ->orWhere('created_at', '>=', $startOfToday))
                ->oldest('id')
                ->get()
        );
    }

    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($request->user()->can(UserAbility::ReadOrders->value), 403);

        return new OrderResource($order->load('items'));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $order->moveTo(OrderStatus::from($request->validated('status')), $request->validated('reason'));

        return new OrderResource($order->load('items'));
    }

    public function updatePayment(Request $request, Order $order): OrderResource
    {
        abort_unless($request->user()->can(UserAbility::ManageOrders->value), 403);

        $validated = $request->validate(['payment_status' => ['required', Rule::in([Order::PAID, Order::UNPAID])]]);
        $order->update($validated);

        return new OrderResource($order->load('items'));
    }
}
