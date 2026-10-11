<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\UserAbility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function testTheBoardShowsOpenOrdersAndThoseFinishedToday()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00', 'Australia/Adelaide'));
        $oldOpen = Order::factory()->status(OrderStatus::Ready)->create();
        $oldDone = Order::factory()->status(OrderStatus::Completed)->create();

        $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00', 'Australia/Adelaide'));
        $todayDone = Order::factory()->status(OrderStatus::Cancelled)->create();
        $new = Order::factory()->withItem('Kheer', 699, 2)->create();

        $this->beAdmin()->getJson('orders')
            ->assertSuccessful()
            ->assertJsonPath('data.*.id', [$oldOpen->id, $todayDone->id, $new->id])
            ->assertJsonPath('data.2.items.0.name', 'Kheer')
            ->assertJsonPath('data.2.items.0.quantity', 2)
            ->assertJsonPath('data.2.customer_phone', '0412 345 678');

        $this->getJson('orders?view=history')
            ->assertJsonPath('data.*.id', [$new->id, $todayDone->id, $oldDone->id, $oldOpen->id])
            ->assertJsonPath('meta.total', 4);
    }

    public function testOrdersMoveForwardAndAreStamped()
    {
        $order = Order::factory()->create();
        $this->beAdmin();

        $this->putJson("orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.status_label', 'Accepted');
        $this->assertNotNull($order->fresh()->accepted_at);

        $this->putJson("orders/{$order->id}/status", ['status' => 'ready'])->assertSuccessful();
        $this->assertNotNull($order->fresh()->ready_at);

        $this->putJson("orders/{$order->id}/status", ['status' => 'preparing'])->assertJsonValidationErrors('status');

        // Collecting an order marks it paid.
        $this->putJson("orders/{$order->id}/status", ['status' => 'completed'])
            ->assertJsonPath('data.payment_status', 'paid');

        $this->putJson("orders/{$order->id}/status", ['status' => 'cancelled'])->assertJsonValidationErrors('status');
    }

    public function testOrdersCanBeCancelledWithAReasonTheCustomerSees()
    {
        $order = Order::factory()->status(OrderStatus::Accepted)->create();

        $this->beAdmin()->putJson("orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'Ran out of paneer'])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'cancelled');

        $this->getJson("public/orders/{$order->public_token}")
            ->assertJsonPath('data.status_label', 'Cancelled')
            ->assertJsonPath('data.cancel_reason', 'Ran out of paneer');
    }

    public function testOrdersCanBeMarkedPaid()
    {
        $order = Order::factory()->create();

        $this->beAdmin()->putJson("orders/{$order->id}/payment", ['payment_status' => 'paid'])
            ->assertJsonPath('data.payment_status', 'paid');
        $this->putJson("orders/{$order->id}/payment", ['payment_status' => 'free'])->assertJsonValidationErrors('payment_status');
    }

    public function testOrdersNeedTheOrderAbilities()
    {
        $order = Order::factory()->create();

        $this->getJson('orders')->assertUnauthorized();
        $this->beUser()->getJson('orders')->assertForbidden();
        $this->beUser()->getJson("orders/{$order->id}")->assertForbidden();

        $this->beUserAbleTo(UserAbility::ReadOrders->value);
        $this->getJson('orders')->assertSuccessful();
        $this->getJson("orders/{$order->id}")->assertSuccessful();
        $this->putJson("orders/{$order->id}/status", ['status' => 'accepted'])->assertForbidden();
        $this->putJson("orders/{$order->id}/payment", ['payment_status' => 'paid'])->assertForbidden();

        $this->beUserAbleTo(UserAbility::ManageOrders->value)
            ->putJson("orders/{$order->id}/status", ['status' => 'accepted'])
            ->assertSuccessful();
    }
}
