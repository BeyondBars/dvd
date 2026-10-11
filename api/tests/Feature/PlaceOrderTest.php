<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Notifications\Orders\NewOrderNotification;
use App\Services\Ordering;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $patty;
    private MenuItem $rice;
    private MenuItem $chai;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 10:00 truck time: open 07:00-15:00.
        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'Australia/Adelaide'));

        $category = MenuCategory::factory()->create();
        $this->patty = MenuItem::factory()->for($category, 'category')->create(['name' => 'Desi Patty', 'price_cents' => 599]);
        $this->patty->syncOptionGroups([[
            'name' => 'Filling', 'min_choices' => 1, 'max_choices' => 1,
            'options' => [['name' => 'Aloo', 'price_cents' => 0], ['name' => 'Paneer', 'price_cents' => 0]],
        ]]);
        $this->rice = MenuItem::factory()->for($category, 'category')->create(['name' => 'Rajma & Rice', 'price_cents' => 899]);
        $this->rice->syncOptionGroups([[
            'name' => 'Size', 'min_choices' => 0, 'max_choices' => 1,
            'options' => [['name' => 'Large Combo', 'price_cents' => 299]],
        ]]);
        $this->chai = MenuItem::factory()->for($category, 'category')->create(['name' => 'Desi Chai', 'price_cents' => 499]);
    }

    private function option(MenuItem $item, string $name): int
    {
        return $item->optionGroups()->first()->options()->where('name', $name)->value('id');
    }

    private function order(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Priya',
            'customer_phone' => '0412 345 678',
            'items' => [['menu_item_id' => $this->chai->id, 'quantity' => 1]],
        ], $overrides);
    }

    public function testAnOrderIsPricedFromTheMenu()
    {
        $response = $this->postJson('public/orders', $this->order([
            'customer_email' => 'priya@example.com',
            'notes' => 'Extra napkins',
            'items' => [
                ['menu_item_id' => $this->chai->id, 'quantity' => 2],
                ['menu_item_id' => $this->rice->id, 'quantity' => 1, 'option_ids' => [$this->option($this->rice, 'Large Combo')], 'notes' => 'Mild'],
                ['menu_item_id' => $this->patty->id, 'quantity' => 3, 'option_ids' => [$this->option($this->patty, 'Paneer')]],
            ],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.reference', 'DVD-1001')
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.pickup_at', null)
            ->assertJsonPath('data.payment_method', 'pay_at_pickup')
            ->assertJsonPath('data.payment_status', 'unpaid')
            // 2 x 4.99 + (8.99 + 2.99) + 3 x 5.99 = 9.98 + 11.98 + 17.97
            ->assertJsonPath('data.total_cents', 3993)
            ->assertJsonPath('data.items.1.options', [['group' => 'Size', 'name' => 'Large Combo', 'price_cents' => 299]])
            ->assertJsonPath('data.items.1.notes', 'Mild')
            ->assertJsonMissingPath('data.customer_phone');

        $order = Order::firstOrFail();
        $this->assertSame($response->json('data.token'), $order->public_token);
        $this->assertSame('priya@example.com', $order->customer_email);
        $this->assertSame(1198, $order->items[1]->unit_price_cents);

        $this->postJson('public/orders', $this->order())->assertJsonPath('data.reference', 'DVD-1002');
    }

    public function testPricesSentByTheBrowserAreIgnored()
    {
        $this->postJson('public/orders', $this->order([
            'total_cents' => 1,
            'items' => [['menu_item_id' => $this->chai->id, 'quantity' => 1, 'price_cents' => 1]],
        ]))->assertJsonPath('data.total_cents', 499);
    }

    public function testAPickupTimeMustBeOneOnOffer()
    {
        $this->postJson('public/orders', $this->order(['pickup_at' => '2026-10-12T10:30:00+10:30']))
            ->assertCreated()
            ->assertJsonPath('data.pickup_at', '2026-10-12T00:00:00+00:00');

        // Too soon (prep time), after closing, and not on a 15 minute step.
        foreach (['2026-10-12T10:05:00+10:30', '2026-10-12T16:00:00+10:30', '2026-10-12T10:40:00+10:30'] as $time) {
            $this->postJson('public/orders', $this->order(['pickup_at' => $time]))->assertJsonValidationErrors('pickup_at');
        }
    }

    public function testNoOrdersWhileOrderingIsOffOrClosed()
    {
        app(Ordering::class)->update(['ordering_enabled' => false]);
        $this->postJson('public/orders', $this->order())->assertJsonValidationErrors('order');

        app(Ordering::class)->update(['ordering_enabled' => true]);
        $this->travelTo(CarbonImmutable::parse('2026-10-12 16:00', 'Australia/Adelaide'));
        $this->postJson('public/orders', $this->order())->assertJsonValidationErrors('order');

        $this->assertDatabaseCount('orders', 0);
    }

    public function testSoldOutHiddenAndUnknownItemsAreRefused()
    {
        $this->chai->update(['is_available' => false]);
        $this->postJson('public/orders', $this->order())
            ->assertJsonValidationErrors(['items.0' => 'Sorry, Desi Chai is sold out.']);

        $hidden = MenuItem::factory()->for(MenuCategory::factory()->hidden(), 'category')->create();
        $this->postJson('public/orders', $this->order(['items' => [['menu_item_id' => $hidden->id, 'quantity' => 1]]]))
            ->assertJsonValidationErrors('items.0');

        $this->postJson('public/orders', $this->order(['items' => [['menu_item_id' => 999, 'quantity' => 1]]]))
            ->assertJsonValidationErrors('items.0');

        $this->assertDatabaseCount('orders', 0);
    }

    public function testRequiredChoicesMustBeMadeAndOnlyTheItemsOwnChoicesCount()
    {
        $paneer = $this->option($this->patty, 'Paneer');
        $aloo = $this->option($this->patty, 'Aloo');
        $large = $this->option($this->rice, 'Large Combo');

        foreach ([[], [$paneer, $aloo], [$paneer, $large]] as $optionIds) {
            $this->postJson('public/orders', $this->order([
                'items' => [['menu_item_id' => $this->patty->id, 'quantity' => 1, 'option_ids' => $optionIds]],
            ]))->assertJsonValidationErrors('items.0.option_ids');
        }

        $this->assertDatabaseCount('orders', 0);
    }

    public function testOrdersAreValidated()
    {
        $this->postJson('public/orders', [
            'customer_phone' => 'call me',
            'customer_email' => 'nope',
            'items' => [['menu_item_id' => $this->chai->id, 'quantity' => 0]],
        ])->assertJsonValidationErrors(['customer_name', 'customer_phone', 'customer_email', 'items.0.quantity']);

        $this->postJson('public/orders', $this->order(['items' => []]))->assertJsonValidationErrors('items');
    }

    public function testStaffAreAlertedByEmailAndSms()
    {
        Notification::fake();
        app(Ordering::class)->update(['alert_email' => 'owner@example.com', 'alert_phone' => '0435331122']);

        $this->postJson('public/orders', $this->order())->assertCreated();

        Notification::assertSentOnDemand(NewOrderNotification::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) =>
            $channels === ['mail'] && $notifiable->routes['mail'] === 'owner@example.com');
        Notification::assertSentOnDemand(NewOrderNotification::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) =>
            $channels === ['vonage'] && $notifiable->routes['vonage'] === '0435331122');
    }

    public function testTheAlertEmailRendersAndNoAlertsGoOutWithoutContacts()
    {
        Notification::fake();
        $this->postJson('public/orders', $this->order())->assertCreated();
        Notification::assertNothingSent();

        $order = Order::with('items')->firstOrFail();
        $mail = (new NewOrderNotification($order, 'mail'))->toMail(new AnonymousNotifiable)->render();
        $this->assertStringContainsString('DVD-1001', (string) $mail);
        $this->assertStringContainsString('Desi Chai', (string) $mail);

        $sms = (new NewOrderNotification($order, 'vonage'))->toVonage(new AnonymousNotifiable);
        $this->assertSame('New order DVD-1001 from Priya: 1 item, $4.99, pickup ASAP.', $sms->content);
    }

    public function testCustomersFollowTheirOrderByToken()
    {
        $token = $this->postJson('public/orders', $this->order())->json('data.token');

        $this->getJson("public/orders/{$token}")
            ->assertSuccessful()
            ->assertJsonPath('data.reference', 'DVD-1001')
            ->assertJsonPath('data.status_label', 'New')
            ->assertJsonMissingPath('data.customer_phone');

        $this->getJson('public/orders/not-a-real-token')->assertNotFound();
    }
}
