<?php

namespace App\Services;

use App\Models\Setting;
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
        $earliest = $now->addMinutes((int) $settings['prep_minutes'])->max($open);

        // Round up to the next step, e.g. 10:07 -> 10:15.
        $slot = $earliest->setTime($earliest->hour, 0)->addMinutes((int) ceil($earliest->minute / $step) * $step);

        $slots = [];
        for (; $slot->lte($close); $slot = $slot->addMinutes($step)) {
            $slots[] = $slot;
        }

        return $slots;
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
            'pickup_slots' => array_map(fn ($slot) => $slot->toIso8601String(), $slots),
        ];
    }
}
