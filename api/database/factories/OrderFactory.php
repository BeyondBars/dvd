<?php

namespace Database\Factories;

use App\Models\Order;
use App\Support\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fn () => Order::nextNumber(),
            'public_token' => fn () => Order::newToken(),
            'status' => OrderStatus::New,
            'customer_name' => $this->faker->name(),
            'customer_phone' => '0412 345 678',
            'customer_email' => null,
            'pickup_at' => null,
            'notes' => null,
            'subtotal_cents' => 1299,
            'total_cents' => 1299,
            'payment_method' => Order::PAY_AT_PICKUP,
            'payment_status' => Order::UNPAID,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function withItem(string $name = 'Samosa (2 pcs)', int $price = 799, int $quantity = 1): static
    {
        return $this->afterCreating(fn (Order $order) => $order->items()->create([
            'name' => $name,
            'unit_price_cents' => $price,
            'quantity' => $quantity,
            'line_total_cents' => $price * $quantity,
        ]));
    }
}
