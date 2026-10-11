<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Notifications\Customer\ResetPasswordNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('public/customer/register', array_merge([
            'name' => 'Priya Sharma',
            'email' => 'priya@example.com',
            'phone' => '0412 345 678',
            'password' => 'masala123',
            'password_confirmation' => 'masala123',
        ], $overrides));
    }

    public function testCustomersCanSignUpAndAreSignedIn()
    {
        $this->register()
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'Priya Sharma')
            ->assertJsonPath('data.email', 'priya@example.com')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs(Customer::firstOrFail(), 'customer');
        $this->getJson('public/customer')->assertJsonPath('data.phone', '0412 345 678');
    }

    public function testSignUpIsValidated()
    {
        Customer::factory()->create(['email' => 'taken@example.com']);

        $this->register([
            'name' => '',
            'email' => 'taken@example.com',
            'phone' => 'nope',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertJsonValidationErrors(['name', 'email', 'phone', 'password']);
    }

    public function testCustomersCanSignInAndOut()
    {
        $customer = Customer::factory()->create(['email' => 'priya@example.com', 'password' => 'masala123']);

        $this->postJson('public/customer/login', ['email' => 'priya@example.com', 'password' => 'wrong'])
            ->assertJsonValidationErrors('email');
        $this->assertGuest('customer');

        $this->postJson('public/customer/login', ['email' => 'priya@example.com', 'password' => 'masala123'])
            ->assertSuccessful()
            ->assertJsonPath('data.id', $customer->id);
        $this->assertAuthenticatedAs($customer, 'customer');

        $this->postJson('public/customer/logout')->assertNoContent();
        $this->assertGuest('customer');
    }

    public function testAccountRoutesNeedACustomerLogin()
    {
        $this->getJson('public/customer')->assertUnauthorized();
        $this->getJson('public/customer/orders')->assertUnauthorized();

        // A staff login is not a customer login.
        $this->beAdmin()->getJson('public/customer')->assertUnauthorized();
    }

    public function testACustomerLoginDoesNotOpenTheAdmin()
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $this->getJson('user')->assertUnauthorized();
        $this->getJson('orders')->assertUnauthorized();
        $this->getJson('menu/categories')->assertUnauthorized();
    }

    public function testCustomersCanUpdateTheirDetails()
    {
        $customer = Customer::factory()->create(['password' => 'masala123']);
        $this->actingAs($customer, 'customer');

        $this->putJson('public/customer', ['name' => 'Priya S', 'phone' => '0400 000 000'])
            ->assertJsonPath('data.name', 'Priya S')
            ->assertJsonPath('data.phone', '0400 000 000');

        $this->putJson('public/customer', ['password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertJsonValidationErrors('current_password');

        $this->putJson('public/customer', [
            'current_password' => 'masala123',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('newpass123', $customer->fresh()->password));
    }

    public function testOrdersPlacedWhileSignedInShowInTheAccount()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'Australia/Adelaide'));
        $item = MenuItem::factory()->create(['price_cents' => 499]);
        $customer = Customer::factory()->create();
        Order::factory()->create(); // someone else's

        $this->actingAs($customer, 'customer');
        $this->postJson('public/orders', [
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->assertSame($customer->id, Order::latest('id')->first()->customer_id);

        $this->getJson('public/customer/orders')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total_cents', 998);
    }

    public function testGuestOrdersHaveNoAccount()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'Australia/Adelaide'));
        $item = MenuItem::factory()->create();

        $this->postJson('public/orders', [
            'customer_name' => 'Guest',
            'customer_phone' => '0412 345 678',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertNull(Order::firstOrFail()->customer_id);
    }

    public function testCustomersCanResetAForgottenPassword()
    {
        Notification::fake();
        $customer = Customer::factory()->create(['email' => 'priya@example.com']);

        // Unknown emails get the same answer, so accounts can't be discovered.
        $this->postJson('public/customer/password/forgot', ['email' => 'nobody@example.com'])->assertNoContent();
        $this->postJson('public/customer/password/forgot', ['email' => 'priya@example.com'])->assertNoContent();

        $token = null;
        Notification::assertSentTo($customer, ResetPasswordNotification::class, function ($notification) use (&$token, $customer) {
            $token = $notification->token;
            $url = $notification->toMail($customer)->viewData['url'];

            return str_starts_with($url, 'http://localhost:3000/reset-password?token=') && str_contains($url, 'email=priya%40example.com');
        });

        $this->postJson('public/customer/password/reset', [
            'token' => 'wrong', 'email' => 'priya@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertJsonValidationErrors('email');

        $this->postJson('public/customer/password/reset', [
            'token' => $token, 'email' => 'priya@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('newpass123', $customer->fresh()->password));
    }

    public function testTheResetEmailRenders()
    {
        $customer = Customer::factory()->create();
        $mail = (new ResetPasswordNotification('abc'))->toMail($customer)->render();

        $this->assertStringContainsString('reset-password?token=abc', (string) $mail);
    }
}
