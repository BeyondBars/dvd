<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function shouldTwoFactorBeVerified(): bool
    {
        return false;
    }

    public function testTooManyLoginAttemptsLocksUserOutOnNextAttemptForThatCombination()
    {
        $user = $this->createStandardUser(['email' => 'john@email.com']);

        // Try 5 times with bad information.
        $maxAttempts = 5;

        while ($maxAttempts > 0) {
            $this->postJson('auth', ['email' => 'john@email.com', 'password' => 'Incorrect*12345'])->assertStatus(422);

            $maxAttempts--;
        }

        // 6th time is with correct information but denied due to already being locked out.
        $response = $this->postJson('auth', ['email' => 'john@email.com', 'password' => 'Secret*12345']);
        $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
        $response->assertJsonFragment(['email' => ['Due to 5 failed login attempts your IP and email address has been locked out for 30 minutes']]);
    }

    public function testCannotLoginWithInvalidEmail()
    {
        $response = $this->postJson('auth', ['email' => 'john@email.com', 'password' => 'Secret*12345']);
        $response->assertStatus(422);
        $response->assertJsonFragment(['email' => ['The email and password combination were incorrect']]);
    }

    public function testCannotLoginWithInvalidPassword()
    {
        $this->createStandardUser(['email' => 'john@email.com']);

        $response = $this->postJson('auth', ['email' => 'john@email.com', 'password' => 'Secret*32145']);
        $response->assertStatus(422);
        $response->assertJsonFragment(['email' => ['The email and password combination were incorrect']]);
    }

    public function testLoggedInUserCanLogoutAndHaveSessionDestroyed()
    {
        $this->withTwoFactorVerified();

        $user = $this->createStandardUser();

        $response = $this->be($user)->deleteJson('auth');
        $response->assertSuccessful();
        $response->assertSessionMissing('two_factor_verified');
    }

    public function testCanLoginWithCorrectEmailAndPassword()
    {
        User::factory()
            ->superAdminUser()
            ->enabled()
            ->create([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'mobile' => '0404040404',
                'email' => 'admin@example.com',
                'password' => 'Secret*12345'
            ]);

        $response = $this->postJson('auth', ['email' => 'admin@example.com', 'password' => 'Secret*12345']);
        $response->assertSuccessful();
    }

    public function testDisabledUsersCannotLogIn()
    {
        User::factory()->disabled()->create(['email' => 'john@email.com', 'password' => 'Secret*12345']);

        $this->postJson('auth', ['email' => 'john@email.com', 'password' => 'Secret*12345'])
            ->assertStatus(422)
            ->assertJsonFragment(['email' => ['This user account has been disabled']]);

        $this->assertGuest('web');
    }

    public function testLoginReturnsTheUserWithAbilities()
    {
        User::factory()->superAdminUser()->create(['email' => 'admin@example.com', 'password' => 'Secret*12345']);

        $this->postJson('auth', ['email' => 'admin@example.com', 'password' => 'Secret*12345'])
            ->assertSuccessful()
            ->assertJsonPath('data.role.name', 'super_admin')
            ->assertJsonCount(count(UserAbility::cases()), 'data.abilities');
    }
}
