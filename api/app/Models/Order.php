<?php

namespace App\Models;

use App\Support\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An online order for pickup from the truck. Paid at pickup for now.
 */
class Order extends Model
{
    use HasFactory;

    public const PAY_AT_PICKUP = 'pay_at_pickup';
    public const UNPAID = 'unpaid';
    public const PAID = 'paid';

    /** The first order number; later ones count up from here. */
    public const FIRST_NUMBER = 1001;

    protected $fillable = [
        'number', 'public_token', 'status', 'customer_name', 'customer_phone', 'customer_email', 'pickup_at',
        'notes', 'subtotal_cents', 'total_cents', 'payment_method', 'payment_status', 'accepted_at', 'ready_at',
        'completed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => OrderStatus::class,
            'pickup_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'total_cents' => 'integer',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /** DVD-1001 */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => 'DVD-'.$this->number);
    }

    public static function nextNumber(): int
    {
        return max((int) static::max('number') + 1, static::FIRST_NUMBER);
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    /**
     * Move the order to a new status, stamping when it happened. Collecting an order marks it paid.
     */
    public function moveTo(OrderStatus $status, ?string $reason = null): void
    {
        $this->status = $status;

        match ($status) {
            OrderStatus::Accepted => $this->accepted_at = now(),
            OrderStatus::Ready => $this->ready_at = now(),
            OrderStatus::Completed => [$this->completed_at = now(), $this->payment_status = static::PAID],
            OrderStatus::Cancelled => [$this->cancelled_at = now(), $this->cancel_reason = $reason],
            default => null,
        };

        $this->save();
    }
}
