<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\Ordering;
use App\Support\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 12 Oct 2026, 10:00 truck time.
        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'Australia/Adelaide'));
    }

    private function order(OrderStatus $status, array $attributes = []): Order
    {
        return Order::factory()->status($status)->create($attributes);
    }

    public function testAQuietKitchenWaitsJustThePrepTime()
    {
        $this->getJson('public/kitchen')
            ->assertSuccessful()
            ->assertJsonPath('data.open_now', true)
            ->assertJsonPath('data.accepting_orders', true)
            ->assertJsonPath('data.cooking', 0)
            ->assertJsonPath('data.ready_for_pickup', 0)
            ->assertJsonPath('data.served_today', 0)
            ->assertJsonPath('data.wait_minutes', 15)
            ->assertJsonPath('data.busy_level', 'quiet')
            ->assertJsonPath('data.earliest_pickup', '2026-10-12T10:15:00+10:30');
    }

    public function testEachOrderInTheQueueAddsToTheWait()
    {
        $this->order(OrderStatus::New);
        $this->order(OrderStatus::Accepted);
        $this->order(OrderStatus::Preparing);
        // Not holding anyone up: for later, already cooked, done, cancelled or from yesterday.
        $this->order(OrderStatus::New, ['pickup_at' => now()->addHours(3)]);
        $this->order(OrderStatus::Ready);
        $this->order(OrderStatus::Completed);
        $this->order(OrderStatus::Completed);
        $this->order(OrderStatus::Cancelled);
        $this->order(OrderStatus::New, ['created_at' => now()->subDay()]);

        // 15 + 3 orders x 3 minutes = 24, rounded up to 25.
        $this->getJson('public/kitchen')
            ->assertJsonPath('data.cooking', 3)
            ->assertJsonPath('data.ready_for_pickup', 1)
            ->assertJsonPath('data.served_today', 2)
            ->assertJsonPath('data.wait_minutes', 25)
            ->assertJsonPath('data.busy_level', 'steady')
            // 10:25 rounded up to the next pickup time.
            ->assertJsonPath('data.earliest_pickup', '2026-10-12T10:30:00+10:30');

        $this->getJson('public/store')
            ->assertJsonPath('data.wait_minutes', 25)
            ->assertJsonPath('data.pickup_slots.0', '2026-10-12T10:30:00+10:30');
    }

    public function testABigQueueIsSlammed()
    {
        foreach (range(1, 6) as $i) {
            $this->order(OrderStatus::Preparing);
        }

        $this->getJson('public/kitchen')->assertJsonPath('data.busy_level', 'slammed')->assertJsonPath('data.wait_minutes', 35);
    }

    public function testPrepTimeIsLearnedFromRecentOrders()
    {
        foreach (range(1, 5) as $i) {
            $this->order(OrderStatus::Completed, ['accepted_at' => now()->subMinutes(60 * $i + 20), 'ready_at' => now()->subMinutes(60 * $i)]);
        }

        $this->assertSame(20, app(Ordering::class)->prepMinutes());
        $this->getJson('public/kitchen')->assertJsonPath('data.wait_minutes', 20);
    }

    public function testTooFewOrdersOrOddOnesDontSkewThePrepTime()
    {
        foreach (range(1, 4) as $i) {
            $this->order(OrderStatus::Completed, ['accepted_at' => now()->subMinutes(60 * $i + 40), 'ready_at' => now()->subMinutes(60 * $i)]);
        }
        $this->assertSame(15, app(Ordering::class)->prepMinutes());

        // A "Ready" tapped hours late is capped.
        $this->order(OrderStatus::Completed, ['accepted_at' => now()->subHours(9), 'ready_at' => now()->subHour()]);
        $this->assertSame(60, app(Ordering::class)->prepMinutes());
    }

    public function testStaffCanAddRushTime()
    {
        // The admin always sends the whole form.
        $settings = ['opening_hours' => Ordering::DEFAULTS['opening_hours']];

        $this->beAdmin()->putJson('ordering-settings', [...$settings, 'busy_minutes' => 10])
            ->assertSuccessful()
            ->assertJsonPath('data.busy_minutes', 10);

        $this->getJson('public/kitchen')->assertJsonPath('data.wait_minutes', 25);

        $this->putJson('ordering-settings', [...$settings, 'busy_minutes' => 90])->assertJsonValidationErrors('busy_minutes');
    }

    public function testNoCustomerDetailsAreShown()
    {
        $this->order(OrderStatus::Preparing, ['customer_name' => 'Priya Sharma', 'customer_phone' => '0499 111 222']);

        $content = $this->getJson('public/kitchen')->getContent();
        $this->assertStringNotContainsString('Priya', $content);
        $this->assertStringNotContainsString('0499', $content);
    }

    public function testWhenClosedNothingIsCookingOrOnOffer()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-11 10:00', 'Australia/Adelaide')); // Sunday

        $this->getJson('public/kitchen')
            ->assertJsonPath('data.open_now', false)
            ->assertJsonPath('data.accepting_orders', false)
            ->assertJsonPath('data.earliest_pickup', null);
    }

    public function testCustomersSeeHowManyOrdersAreAheadOfTheirs()
    {
        $this->order(OrderStatus::Ready);
        $this->order(OrderStatus::Preparing);
        $this->order(OrderStatus::New);
        $mine = $this->order(OrderStatus::New);
        $this->order(OrderStatus::New);

        $this->getJson("public/orders/{$mine->public_token}")->assertJsonPath('data.orders_ahead', 2);

        $mine->moveTo(OrderStatus::Ready);
        $this->getJson("public/orders/{$mine->public_token}")->assertJsonPath('data.orders_ahead', null);
    }
}
