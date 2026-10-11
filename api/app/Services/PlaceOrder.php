<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Notifications\Orders\NewOrderNotification;
use App\Support\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns a validated order from the website into an Order: checks the truck is taking orders, the pickup
 * time and every item and choice against the menu, and works out the prices itself.
 */
class PlaceOrder
{
    public function __construct(private Ordering $ordering)
    {
    }

    public function __invoke(array $data, ?Customer $customer = null): Order
    {
        $slots = $this->ordering->pickupSlots();

        if ($slots === []) {
            throw ValidationException::withMessages(['order' => "Sorry, we're not taking online orders right now."]);
        }

        $pickupAt = $this->pickupTime($data['pickup_at'] ?? null, $slots);
        $lines = $this->lines($data['items']);
        $subtotal = array_sum(array_column($lines, 'line_total_cents'));

        $order = $this->create([
            'status' => OrderStatus::New,
            'customer_id' => $customer?->id,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?? null,
            'pickup_at' => $pickupAt,
            'notes' => $data['notes'] ?? null,
            'subtotal_cents' => $subtotal,
            'total_cents' => $subtotal,
            'payment_method' => Order::PAY_AT_PICKUP,
            'payment_status' => Order::UNPAID,
        ], $lines);

        $this->alert($order);

        return $order;
    }

    /**
     * Null for ASAP; otherwise it has to be one of the times on offer.
     *
     * @param CarbonImmutable[] $slots
     */
    private function pickupTime(?string $requested, array $slots): ?CarbonImmutable
    {
        if ($requested === null || $requested === '') {
            return null;
        }

        $requested = CarbonImmutable::parse($requested)->startOfMinute();

        foreach ($slots as $slot) {
            if ($slot->equalTo($requested)) {
                return $slot->utc();
            }
        }

        throw ValidationException::withMessages(['pickup_at' => 'That pickup time is no longer available. Please choose another.']);
    }

    private function lines(array $requested): array
    {
        $items = MenuItem::with(['category', 'optionGroups.options'])
            ->whereIn('id', array_column($requested, 'menu_item_id'))
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($requested as $index => $line) {
            $item = $items->get($line['menu_item_id']);

            if (!$item || !$item->category->is_visible) {
                throw ValidationException::withMessages(["items.{$index}" => 'An item in your order is no longer on the menu.']);
            }

            if (!$item->is_available) {
                throw ValidationException::withMessages(["items.{$index}" => "Sorry, {$item->name} is sold out."]);
            }

            $options = $this->options($item, $line['option_ids'] ?? [], $index);
            $unitPrice = $item->price_cents + array_sum(array_column($options, 'price_cents'));

            $lines[] = [
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'unit_price_cents' => $unitPrice,
                'quantity' => $line['quantity'],
                'line_total_cents' => $unitPrice * $line['quantity'],
                'options' => $options ?: null,
                'notes' => $line['notes'] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * The chosen options as copies (group, name, price), checked against the item's choices.
     */
    private function options(MenuItem $item, array $optionIds, int $index): array
    {
        $chosen = [];
        $known = [];

        foreach ($item->optionGroups as $group) {
            $picked = $group->options->whereIn('id', $optionIds);
            $known = [...$known, ...$group->options->pluck('id')->all()];

            if ($picked->count() < $group->min_choices || $picked->count() > $group->max_choices) {
                $message = $group->min_choices === $group->max_choices
                    ? "Choose {$group->min_choices} {$group->name} for {$item->name}."
                    : "Choose between {$group->min_choices} and {$group->max_choices} {$group->name} for {$item->name}.";

                throw ValidationException::withMessages(["items.{$index}.option_ids" => $message]);
            }

            foreach ($picked as $option) {
                $chosen[] = ['group' => $group->name, 'name' => $option->name, 'price_cents' => $option->price_cents];
            }
        }

        if (array_diff($optionIds, $known)) {
            throw ValidationException::withMessages(["items.{$index}.option_ids" => "A choice for {$item->name} is no longer available."]);
        }

        return $chosen;
    }

    private function create(array $attributes, array $lines, int $attempt = 1): Order
    {
        try {
            return DB::transaction(function () use ($attributes, $lines) {
                $order = Order::create([
                    ...$attributes,
                    'number' => Order::nextNumber(),
                    'public_token' => Order::newToken(),
                ]);

                $order->items()->createMany($lines);

                return $order->load('items');
            });
        } catch (QueryException $exception) {
            // Two orders took the same number at the same moment: try again with the next one.
            if ($attempt < 3 && str_contains(strtolower($exception->getMessage()), 'unique')) {
                return $this->create($attributes, $lines, $attempt + 1);
            }

            throw $exception;
        }
    }

    /**
     * Tell staff by email and/or SMS. A failed alert never loses the order (it shows on the board anyway).
     */
    private function alert(Order $order): void
    {
        $settings = $this->ordering->settings();
        $routes = array_filter(['mail' => $settings['alert_email'], 'vonage' => $settings['alert_phone']]);

        foreach ($routes as $channel => $route) {
            try {
                Notification::route($channel, $route)->notify(new NewOrderNotification($order, $channel));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
