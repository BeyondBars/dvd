<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\Ordering;
use App\Support\Enums\UserAbility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CounterOrderTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $patty;
    private MenuItem $chai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'Australia/Adelaide'));

        $category = MenuCategory::factory()->create();
        $this->patty = MenuItem::factory()->for($category, 'category')->create(['name' => 'Desi Patty', 'price_cents' => 599]);
        $this->patty->syncOptionGroups([[
            'name' => 'Filling', 'min_choices' => 1, 'max_choices' => 1,
            'options' => [['name' => 'Aloo', 'price_cents' => 0], ['name' => 'Paneer', 'price_cents' => 100]],
        ]]);
        $this->chai = MenuItem::factory()->for($category, 'category')->create(['name' => 'Desi Chai', 'price_cents' => 499]);
    }

    public function testStaffTakeAnOrderAtTheCounter()
    {
        Notification::fake();
        $paneer = $this->patty->optionGroups()->first()->options()->where('name', 'Paneer')->value('id');

        $this->beAdmin()->postJson('orders', [
            'items' => [
                ['menu_item_id' => $this->patty->id, 'quantity' => 2, 'option_ids' => [$paneer]],
                ['menu_item_id' => $this->chai->id, 'quantity' => 1, 'notes' => 'No sugar'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.customer_name', 'Walk-in')
            ->assertJsonPath('data.customer_phone', '')
            ->assertJsonPath('data.payment_method', 'at_counter')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.items.0.unit_price_cents', 699)
            ->assertJsonPath('data.items.1.notes', 'No sugar')
            ->assertJsonPath('data.total_cents', 699 * 2 + 499)
            ->assertJsonPath('data.reference', 'DVD-1001');

        // Staff entered it themselves, so nobody is alerted.
        Notification::assertNothingSent();

        $this->getJson('orders')->assertJsonPath('data.0.reference', 'DVD-1001');
    }

    public function testACounterOrderCanBeTakenAlreadyPaidAndWithACustomer()
    {
        $this->beAdmin()->postJson('orders', [
            'customer_name' => 'Priya',
            'customer_phone' => '0412 345 678',
            'paid' => true,
            'items' => [['menu_item_id' => $this->chai->id, 'quantity' => 1]],
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer_name', 'Priya')
            ->assertJsonPath('data.customer_phone', '0412 345 678')
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function testCounterOrdersWorkWhenOnlineOrderingIsOff()
    {
        app(Ordering::class)->update(['ordering_enabled' => false]);

        $this->beAdmin()->postJson('orders', ['items' => [['menu_item_id' => $this->chai->id, 'quantity' => 1]]])
            ->assertCreated();
    }

    public function testCounterOrdersAreCheckedAgainstTheMenu()
    {
        $this->beAdmin();

        $this->postJson('orders', ['items' => []])->assertJsonValidationErrors('items');
        $this->postJson('orders', ['items' => [['menu_item_id' => $this->patty->id, 'quantity' => 1]]])
            ->assertJsonValidationErrors('items.0.option_ids');

        $this->chai->update(['is_available' => false]);
        $this->postJson('orders', ['items' => [['menu_item_id' => $this->chai->id, 'quantity' => 1]]])
            ->assertJsonValidationErrors('items.0');
    }

    public function testCounterOrdersNeedTheManageOrdersAbility()
    {
        $order = ['items' => [['menu_item_id' => $this->chai->id, 'quantity' => 1]]];

        $this->postJson('orders', $order)->assertUnauthorized();
        $this->beUserAbleTo(UserAbility::ReadOrders->value)->postJson('orders', $order)->assertForbidden();
        $this->beUserAbleTo(UserAbility::ManageOrders->value)->postJson('orders', $order)->assertCreated();
    }
}
