<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Support\Enums\OrderStatus;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample orders for today, so the admin's order list and the website's live kitchen numbers have something
 * to show while testing. Never on production: php artisan db:seed --class=DemoOrdersSeeder
 */
class DemoOrdersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo orders are for testing only.');
        }

        $items = [['Aloo Pyaaj Paranthas', 1299], ['Samosa (2 pcs)', 799], ['Masala Chai', 450], ['Butter Chicken Burger', 1599]];
        $make = fn (OrderStatus $status, int $minutesAgo, array $times = []) => Order::factory()
            ->status($status)
            ->withItem(...$items[array_rand($items)])
            ->create(['created_at' => now()->subMinutes($minutesAgo), ...$times]);

        // Collected earlier today, 10 to 16 minutes from accepted to ready (this is what the prep time is learned from).
        foreach (range(1, 12) as $i) {
            $placed = 300 - $i * 20;
            $make(OrderStatus::Completed, $placed, [
                'accepted_at' => now()->subMinutes($placed - 1),
                'ready_at' => now()->subMinutes($placed - 1 - rand(10, 16)),
                'completed_at' => now()->subMinutes($placed - 20),
                'payment_status' => Order::PAID,
            ]);
        }

        $make(OrderStatus::Ready, 18, ['accepted_at' => now()->subMinutes(17), 'ready_at' => now()->subMinutes(2)]);
        $make(OrderStatus::Preparing, 12, ['accepted_at' => now()->subMinutes(11)]);
        $make(OrderStatus::Preparing, 8, ['accepted_at' => now()->subMinutes(7)]);
        $make(OrderStatus::Accepted, 5, ['accepted_at' => now()->subMinutes(4)]);
        $make(OrderStatus::New, 1);
    }
}
