<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\Enums\OrderStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as its customer sees it on the website (by its unguessable token). Leaves out contact details.
 *
 * @mixin \App\Models\Order
 */
class PublicOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'token' => $this->public_token,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer_name' => $this->customer_name,
            'pickup_at' => $this->pickup_at?->toIso8601String(),
            'total_cents' => $this->total_cents,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'items' => $this->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'line_total_cents' => $item->line_total_cents,
                'options' => $item->options ?? [],
                'notes' => $item->notes,
            ])->values(),
            'orders_ahead' => $this->ordersAhead(),
            'created_at' => $this->created_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
        ];
    }

    /**
     * Orders placed earlier today that are still to be cooked, while this one is too. Null once it's ready.
     */
    private function ordersAhead(): ?int
    {
        $cooking = [OrderStatus::New, OrderStatus::Accepted, OrderStatus::Preparing];

        if (!in_array($this->status, $cooking, true)) {
            return null;
        }

        return Order::query()
            ->whereIn('status', array_map(fn ($status) => $status->value, $cooking))
            ->where('id', '<', $this->id)
            ->where('created_at', '>=', $this->created_at->copy()->setTimezone(config('ordering.timezone'))->startOfDay()->utc())
            ->count();
    }
}
