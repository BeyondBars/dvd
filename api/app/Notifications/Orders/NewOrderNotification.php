<?php

namespace App\Notifications\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the alert email or phone in the ordering settings when an online order comes in.
 */
class NewOrderNotification extends Notification
{
    use Queueable;

    /**
     * @param string $channel "mail" or "vonage".
     */
    public function __construct(public Order $order, protected string $channel)
    {
    }

    public function via($notifiable): array
    {
        return [$this->channel];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(email_subject("New order {$this->order->reference}"))
            ->markdown('mail.orders.new', [
                'order' => $this->order,
                'pickup' => $this->pickup(),
                'url' => rtrim((string) config('app.js_app_url'), '/').'/orders',
            ]);
    }

    public function toVonage($notifiable): VonageMessage
    {
        $count = $this->order->items->sum('quantity');

        return (new VonageMessage)->content(sprintf(
            'New order %s from %s: %d item%s, $%s, pickup %s.',
            $this->order->reference,
            $this->order->customer_name,
            $count,
            $count === 1 ? '' : 's',
            number_format($this->order->total_cents / 100, 2),
            $this->pickup()
        ));
    }

    private function pickup(): string
    {
        return $this->order->pickup_at
            ? $this->order->pickup_at->setTimezone(config('ordering.timezone'))->format('g:i a')
            : 'ASAP';
    }
}
