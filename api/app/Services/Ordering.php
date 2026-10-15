<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Online ordering settings (stored in the settings table, but never sent to the public settings endpoint,
 * since they hold the alert contacts) and the truck's opening hours and pickup times.
 */
class Ordering
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const DEFAULTS = [
        'ordering_enabled' => true,
        // Per day: ['open' => 'HH:MM', 'close' => 'HH:MM'], or null when closed.
        'opening_hours' => [
            'mon' => ['open' => '07:00', 'close' => '15:00'],
            'tue' => ['open' => '07:00', 'close' => '15:00'],
            'wed' => ['open' => '07:00', 'close' => '15:00'],
            'thu' => ['open' => '07:00', 'close' => '15:00'],
            'fri' => ['open' => '07:00', 'close' => '15:00'],
            'sat' => null,
            'sun' => null,
        ],
        // Earliest pickup is this many minutes after ordering.
        'prep_minutes' => 15,
        // Extra minutes staff add to the wait during a rush (0 = normal).
        'busy_minutes' => 0,
        // Who hears about new orders. Either can be empty.
        'alert_email' => null,
        'alert_phone' => null,
    ];

    public function settings(): array
    {
        return array_merge(
            static::DEFAULTS,
            Setting::query()->whereIn('key', array_keys(static::DEFAULTS))->pluck('value', 'key')->all()
        );
    }

    public function update(array $values): array
    {
        foreach (array_intersect_key($values, static::DEFAULTS) as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return $this->settings();
    }

    public function timezone(): string
    {
        return config('ordering.timezone');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /**
     * Today's opening and closing time, or null when closed today.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function hoursOn(CarbonInterface $day): ?array
    {
        $day = CarbonImmutable::instance($day)->setTimezone($this->timezone());
        $hours = $this->settings()['opening_hours'][strtolower($day->format('D'))] ?? null;

        if (!$hours) {
            return null;
        }

        return [$day->setTimeFromTimeString($hours['open']), $day->setTimeFromTimeString($hours['close'])];
    }

    /**
     * Pickup times still on offer today, earliest first. Empty when ordering is off or the truck is
     * closed for the rest of the day. Orders can be placed before opening for pickup after it.
     *
     * @return CarbonImmutable[]
     */
    public function pickupSlots(?CarbonImmutable $now = null): array
    {
        $now ??= $this->now();
        $settings = $this->settings();
        $hours = $this->hoursOn($now);

        if (!$settings['ordering_enabled'] || !$hours) {
            return [];
        }

        [$open, $close] = $hours;
        $step = config('ordering.slot_minutes');
        $earliest = $now->addMinutes($this->waitMinutes($now))->max($open);

        // Round up to the next step, e.g. 10:07 -> 10:15.
        $slot = $earliest->setTime($earliest->hour, 0)->addMinutes((int) ceil($earliest->minute / $step) * $step);

        $slots = [];
        for (; $slot->lte($close); $slot = $slot->addMinutes($step)) {
            $slots[] = $slot;
        }

        return $slots;
    }

    /**
     * Orders still to be cooked: new, accepted or preparing, and wanted ASAP or before an order placed
     * now could be ready. Orders for a later pickup don't hold anyone up yet.
     */
    public function queue(?CarbonImmutable $now = null)
    {
        $now ??= $this->now();

        return Order::query()
            ->whereIn('status', [OrderStatus::New->value, OrderStatus::Accepted->value, OrderStatus::Preparing->value])
            ->where('created_at', '>=', $now->startOfDay()->utc())
            ->where(fn ($query) => $query->whereNull('pickup_at')
                ->orWhere('pickup_at', '<=', $now->addMinutes($this->prepMinutes())->utc()));
    }

    /**
     * How long one order takes, from accepted to ready. Learned from the last orders once there are enough
     * of them, otherwise the prep time from the settings.
     */
    public function prepMinutes(): int
    {
        $config = config('ordering.wait');

        $minutes = Order::query()
            ->whereNotNull('accepted_at')
            ->whereNotNull('ready_at')
            ->where('ready_at', '>=', now()->subDays($config['learn_days']))
            ->latest('ready_at')
            ->limit($config['learn_orders'])
            ->get(['accepted_at', 'ready_at'])
            ->map(fn (Order $order) => $order->accepted_at->diffInSeconds($order->ready_at) / 60);

        if ($minutes->count() < $config['learn_min_orders']) {
            return (int) $this->settings()['prep_minutes'];
        }

        return (int) round(min(max($minutes->avg(), $config['min_prep_minutes']), $config['max_prep_minutes']));
    }

    /**
     * About how long an order placed now would take: one order's prep time, plus a few minutes for each order
     * ahead of it, plus any rush time staff added. Rounded up to 5 minutes, so it doesn't jump around.
     */
    public function waitMinutes(?CarbonImmutable $now = null, ?int $queued = null): int
    {
        $queued ??= $this->queue($now)->count();
        $wait = $this->prepMinutes()
            + $queued * config('ordering.wait.minutes_per_order')
            + (int) $this->settings()['busy_minutes'];

        return (int) ceil($wait / 5) * 5;
    }

    /**
     * The live kitchen numbers for the website: counts only, never who ordered what.
     */
    public function kitchen(): array
    {
        $now = $this->now();
        $queued = $this->queue($now)->count();
        $slots = $this->pickupSlots($now);
        $today = Order::query()->where('created_at', '>=', $now->startOfDay()->utc());
        $levels = config('ordering.wait.busy_levels');

        return [
            'open_now' => ($hours = $this->hoursOn($now)) !== null && $now->between($hours[0], $hours[1]),
            'accepting_orders' => $slots !== [],
            'cooking' => $queued,
            'ready_for_pickup' => (clone $today)->where('status', OrderStatus::Ready->value)->count(),
            'served_today' => (clone $today)->where('status', OrderStatus::Completed->value)->count(),
            'wait_minutes' => $this->waitMinutes($now, $queued),
            'busy_level' => match (true) {
                $queued <= $levels['quiet'] => 'quiet',
                $queued <= $levels['steady'] => 'steady',
                default => 'slammed',
            },
            'earliest_pickup' => isset($slots[0]) ? $slots[0]->toIso8601String() : null,
            'updated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * What the website needs to show the ordering state.
     */
    public function status(): array
    {
        $now = $this->now();
        $settings = $this->settings();
        $hours = $this->hoursOn($now);
        $slots = $this->pickupSlots($now);

        return [
            'ordering_enabled' => (bool) $settings['ordering_enabled'],
            'open_now' => $hours !== null && $now->between($hours[0], $hours[1]),
            'accepting_orders' => $slots !== [],
            'timezone' => $this->timezone(),
            'today' => $hours ? ['open' => $hours[0]->format('H:i'), 'close' => $hours[1]->format('H:i')] : null,
            'opening_hours' => $settings['opening_hours'],
            'prep_minutes' => (int) $settings['prep_minutes'],
            'wait_minutes' => $this->waitMinutes($now),
            'pickup_slots' => array_map(fn ($slot) => $slot->toIso8601String(), $slots),
        ];
    }
}
