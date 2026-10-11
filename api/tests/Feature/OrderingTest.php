<?php

namespace Tests\Feature;

use App\Services\Ordering;
use App\Support\Enums\UserAbility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderingTest extends TestCase
{
    use RefreshDatabase;

    /** Monday 12 Oct 2026, truck time. */
    private function at(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse("2026-10-12 {$time}", 'Australia/Adelaide'));
    }

    public function testTheWebsiteSeesTodaysHoursAndPickupTimes()
    {
        $this->at('14:07');

        $this->getJson('public/store')
            ->assertSuccessful()
            ->assertJsonPath('data.ordering_enabled', true)
            ->assertJsonPath('data.open_now', true)
            ->assertJsonPath('data.accepting_orders', true)
            ->assertJsonPath('data.today', ['open' => '07:00', 'close' => '15:00'])
            ->assertJsonPath('data.prep_minutes', 15)
            // 14:07 + 15 minutes = 14:22, rounded up to 14:30.
            ->assertJsonPath('data.pickup_slots', [
                '2026-10-12T14:30:00+10:30',
                '2026-10-12T14:45:00+10:30',
                '2026-10-12T15:00:00+10:30',
            ]);
    }

    public function testBeforeOpeningTheFirstPickupIsAtOpeningTime()
    {
        $this->at('06:00');

        $this->getJson('public/store')
            ->assertJsonPath('data.open_now', false)
            ->assertJsonPath('data.accepting_orders', true)
            ->assertJsonPath('data.pickup_slots.0', '2026-10-12T07:00:00+10:30');
    }

    public function testNoOrdersNearClosingOrOnClosedDays()
    {
        $this->at('14:50');
        $this->getJson('public/store')->assertJsonPath('data.accepting_orders', false)->assertJsonPath('data.pickup_slots', []);

        $this->travelTo(CarbonImmutable::parse('2026-10-11 10:00', 'Australia/Adelaide')); // Sunday
        $this->getJson('public/store')
            ->assertJsonPath('data.today', null)
            ->assertJsonPath('data.accepting_orders', false);
    }

    public function testOrderingCanBeSwitchedOff()
    {
        $this->at('10:00');
        app(Ordering::class)->update(['ordering_enabled' => false]);

        $this->getJson('public/store')
            ->assertJsonPath('data.ordering_enabled', false)
            ->assertJsonPath('data.open_now', true)
            ->assertJsonPath('data.accepting_orders', false);
    }

    public function testAlertContactsAreNotPublic()
    {
        app(Ordering::class)->update(['alert_email' => 'owner@example.com', 'alert_phone' => '0435331122']);

        $this->assertStringNotContainsString('owner@example.com', $this->getJson('public/store')->getContent());
        $this->assertStringNotContainsString('owner@example.com', $this->getJson('settings')->getContent());
    }

    public function testAdminsCanReadAndChangeOrderingSettings()
    {
        $hours = array_fill_keys(Ordering::DAYS, ['open' => '08:00', 'close' => '14:00']);
        $hours['sun'] = null;

        $this->beAdmin()->putJson('ordering-settings', [
            'ordering_enabled' => false,
            'opening_hours' => $hours,
            'prep_minutes' => 20,
            'alert_email' => 'owner@example.com',
            'alert_phone' => '0435 331 122',
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.ordering_enabled', false)
            ->assertJsonPath('data.opening_hours.mon', ['open' => '08:00', 'close' => '14:00'])
            ->assertJsonPath('data.opening_hours.sun', null)
            ->assertJsonPath('data.prep_minutes', 20);

        $this->getJson('ordering-settings')->assertJsonPath('data.alert_phone', '0435 331 122');
    }

    public function testOrderingSettingsAreValidated()
    {
        $hours = array_fill_keys(Ordering::DAYS, null);
        $hours['mon'] = ['open' => '15:00', 'close' => '07:00'];
        $hours['tue'] = ['open' => '7am', 'close' => '15:00'];

        $this->beAdmin()->putJson('ordering-settings', [
            'opening_hours' => $hours,
            'prep_minutes' => -5,
            'alert_email' => 'nope',
            'alert_phone' => 'call me',
        ])->assertJsonValidationErrors([
            'opening_hours.mon.close', 'opening_hours.tue.open', 'prep_minutes', 'alert_email', 'alert_phone',
        ]);
    }

    public function testOrderingSettingsNeedTheSettingsAbilities()
    {
        $this->getJson('ordering-settings')->assertUnauthorized();
        $this->beUser()->getJson('ordering-settings')->assertForbidden();
        $this->beUserAbleTo(UserAbility::ReadSettings->value)->getJson('ordering-settings')->assertSuccessful();
        $this->putJson('ordering-settings', ['prep_minutes' => 5])->assertForbidden();
    }
}
