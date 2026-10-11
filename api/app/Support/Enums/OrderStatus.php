<?php

namespace App\Support\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Accepted => 'Accepted',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready for pickup',
            self::Completed => 'Collected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /**
     * Orders only move forward (steps may be skipped, e.g. straight to Ready), and can be cancelled until
     * they are collected.
     */
    public function canMoveTo(self $status): bool
    {
        if ($this->isFinal()) {
            return false;
        }

        if ($status === self::Cancelled) {
            return true;
        }

        $steps = [self::New, self::Accepted, self::Preparing, self::Ready, self::Completed];

        return array_search($status, $steps, true) > array_search($this, $steps, true);
    }

    /** Statuses still being worked on. */
    public static function open(): array
    {
        return [self::New->value, self::Accepted->value, self::Preparing->value, self::Ready->value];
    }
}
