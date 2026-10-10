<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Support\Enums\UserRole;
use App\Support\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function testStandardUserCannotListOrViewOtherUsers()
    {
        $user = $this->createStandardUser();
        $other = $this->createStandardUser();

        $this->be($user)->getJson('users')->assertForbidden();
        $this->be($user)->getJson("users/{$other->id}")->assertForbidden();
        $this->be($user)->getJson("users/{$user->id}")->assertSuccessful();
    }

    public function testStandardUserCannotUpdateAnotherUser()
    {
        Notification::fake();
        $user = $this->createStandardUser();
        $victim = $this->createStandardUser(['email' => 'victim@example.com']);

        $this->be($user)->putJson("users/{$victim->id}", ['email' => 'attacker@example.com'])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $victim->id, 'email' => 'victim@example.com', 'new_email' => null]);
        Notification::assertNothingSent();
    }

    public function testUsersWhoCanOnlyViewUsersCannotEditThem()
    {
        $viewer = $this->createStandardUser();
        $viewer->allow('read users');
        $admin = $this->createAdminUser(['first_name' => 'Admin']);

        $this->be($viewer)->getJson("users/{$admin->id}")->assertSuccessful();
        $this->be($viewer)->putJson("users/{$admin->id}", ['first_name' => 'Hacked'])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'first_name' => 'Admin']);
    }

    public function testUserCanUpdateTheirOwnProfile()
    {
        $user = $this->createStandardUser();

        $this->be($user)->putJson("users/{$user->id}", ['first_name' => 'Jane', 'mobile' => '0400 000 000'])
            ->assertSuccessful()
            ->assertJsonPath('data.first_name', 'Jane');
    }

    public function testChangingEmailRequiresVerificationOfTheNewAddress()
    {
        Notification::fake();
        $user = $this->createStandardUser(['email' => 'old@example.com']);

        $this->be($user)->putJson("users/{$user->id}", ['email' => 'new@example.com'])
            ->assertSuccessful()
            ->assertJsonPath('data.email', 'old@example.com')
            ->assertJsonPath('data.new_email', 'new@example.com');

        Notification::assertSentTo(
            new AnonymousNotifiable,
            VerifyEmailNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.com'
        );
    }

    public function testUsersCannotChangeTheirOwnRoleOrStatus()
    {
        $user = $this->createStandardUser();

        $this->be($user)->putJson("users/{$user->id}", ['role' => UserRole::SuperAdmin->value])
            ->assertJsonValidationErrors(['role']);
        $this->be($user)->putJson("users/{$user->id}", ['status' => 'enabled'])
            ->assertJsonValidationErrors(['status']);
    }

    public function testAdminCanInviteAUser()
    {
        Notification::fake();

        $this->beAdmin()->postJson('users', [
            'first_name' => 'New',
            'last_name' => 'Person',
            'email' => 'new@example.com',
            'role' => UserRole::Staff->value,
        ])->assertCreated();

        $user = User::where('email', 'new@example.com')->first();

        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertTrue($user->isA(UserRole::Staff->value));
        Notification::assertSentTo($user, ResetPasswordNotification::class, fn ($n) => $n->invite === true);
    }

    public function testAdminCanChangeAnotherUsersRoleAndStatus()
    {
        $user = $this->createStandardUser();

        $this->beAdmin()->putJson("users/{$user->id}", ['role' => UserRole::Staff->value, 'status' => 'disabled'])
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->isA(UserRole::Staff->value));
        $this->assertFalse($user->isA(UserRole::User->value));
        $this->assertSame(UserStatus::Disabled, $user->status);
    }

    public function testAdminCanDeleteOtherUsersButNotThemselves()
    {
        $admin = $this->createAdminUser();
        $user = $this->createStandardUser();

        $this->be($admin)->deleteJson("users/{$admin->id}")->assertForbidden();
        $this->be($admin)->deleteJson("users/{$user->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function testOnlyAdminsCanDisableAnotherUsersTwoFactor()
    {
        $user = $this->createStandardUser();
        $victim = User::factory()->standardUser()->withTwoFactor()->create();

        $this->be($user)->putJson("users/{$victim->id}/disable-two-factor")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $victim->id, 'two_factor_required' => true]);

        $this->beAdmin()->putJson("users/{$victim->id}/disable-two-factor")->assertSuccessful();
        $this->assertDatabaseHas('users', ['id' => $victim->id, 'two_factor_required' => false]);
    }

    public function testUsersCanOnlyChangeTheirOwnPassword()
    {
        $user = $this->createStandardUser();
        $other = $this->createStandardUser();
        $payload = ['current_password' => 'password', 'password' => 'NewSecret*123', 'password_confirmation' => 'NewSecret*123'];

        $this->be($user)->putJson("users/{$other->id}/password", $payload)->assertForbidden();
        $this->be($user)->putJson("users/{$user->id}/password", $payload)->assertNoContent();
    }

    public function testRolesCanBeListed()
    {
        $this->beUser()->getJson('roles')
            ->assertSuccessful()
            ->assertJsonCount(count(UserRole::cases()), 'data');
    }
}
