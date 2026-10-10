<?php

namespace Tests\Feature;

use App\Support\Enums\UserAbility;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function testSuperAdminCanSeeAUsersPermissionsByModule()
    {
        $staff = $this->createUserWithRole(UserRole::Staff);

        $this->beAdmin()->getJson("users/{$staff->id}/permissions")
            ->assertSuccessful()
            ->assertJsonPath('data.role', 'Staff')
            ->assertJsonPath('data.modules.0.key', 'users')
            ->assertJsonPath('data.modules.0.view', 'read users')
            ->assertJsonPath('data.modules.0.abilities.0', [
                'name' => 'read users',
                'label' => 'View',
                'description' => UserAbility::ReadUsers->description(),
                'requires' => null,
                'role' => true,
                'override' => null,
                'granted' => true,
            ])
            ->assertJsonPath('data.modules.0.abilities.1.granted', false)
            ->assertJsonPath('data.modules.0.abilities.1.requires', 'read users');
    }

    public function testOnlySuperAdminsCanManagePermissions()
    {
        $user = $this->createStandardUser();
        $other = $this->createStandardUser();
        $payload = ['permissions' => ['read users' => true]];

        $this->be($user)->getJson("users/{$other->id}/permissions")->assertForbidden();
        $this->be($user)->putJson("users/{$other->id}/permissions", $payload)->assertForbidden();

        $writer = $this->createStandardUser();
        $writer->allow(UserAbility::WriteUsers->value);
        $this->be($writer)->putJson("users/{$other->id}/permissions", $payload)->assertForbidden();
    }

    public function testSuperAdminCannotChangeTheirOwnPermissions()
    {
        $admin = $this->createAdminUser();

        $this->be($admin)->putJson("users/{$admin->id}/permissions", ['permissions' => ['read users' => false]])
            ->assertForbidden();
    }

    public function testGrantingAnAbilityTheRoleLacks()
    {
        $user = $this->createStandardUser();

        $this->beAdmin()->putJson("users/{$user->id}/permissions", ['permissions' => ['manage settings' => true]])
            ->assertSuccessful()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where('data.modules.1.abilities.1.override', 'allow')
                ->where('data.modules.1.abilities.1.granted', true)
                // Managing settings implies viewing them.
                ->where('data.modules.1.abilities.0.granted', true)
                ->etc());

        $this->assertTrue($user->fresh()->can('manage settings'));
        $this->assertTrue($user->fresh()->can('read settings'));
    }

    public function testRemovingAnAbilityTheRoleGrantsHidesItFromTheUser()
    {
        $staff = $this->createUserWithRole(UserRole::Staff);

        $this->beAdmin()->putJson("users/{$staff->id}/permissions", ['permissions' => ['read users' => false]])
            ->assertSuccessful()
            ->assertJsonPath('data.modules.0.abilities.0.override', 'forbid')
            ->assertJsonPath('data.modules.0.abilities.0.granted', false);

        $this->assertFalse($staff->fresh()->can('read users'));

        // The forbidden ability is no longer sent to the SPA, so the sidebar hides the module.
        $abilities = $this->be($staff->fresh())->getJson('user')->json('data.abilities.*.name');
        $this->assertSame(['read settings'], $abilities);
        $this->getJson('users')->assertForbidden();
    }

    public function testMatchingTheRoleDefaultRemovesTheOverride()
    {
        $staff = $this->createUserWithRole(UserRole::Staff);
        $staff->forbid('read users');

        $this->beAdmin()->putJson("users/{$staff->id}/permissions", ['permissions' => ['read users' => true]])
            ->assertJsonPath('data.modules.0.abilities.0.override', null)
            ->assertJsonPath('data.modules.0.abilities.0.granted', true);

        $this->assertDatabaseCount('permissions', $this->rolePermissionCount());
    }

    public function testResettingRestoresTheRoleDefaults()
    {
        $staff = $this->createUserWithRole(UserRole::Staff);
        $staff->forbid('read users');
        $staff->allow('write users');

        $this->beAdmin()->deleteJson("users/{$staff->id}/permissions")
            ->assertSuccessful()
            ->assertJsonPath('data.modules.0.abilities.0.granted', true)
            ->assertJsonPath('data.modules.0.abilities.1.granted', false);

        $this->assertTrue($staff->fresh()->can('read users'));
        $this->assertFalse($staff->fresh()->can('write users'));
    }

    public function testUnknownAbilitiesAreRejected()
    {
        $user = $this->createStandardUser();

        $this->beAdmin()->putJson("users/{$user->id}/permissions", ['permissions' => ['launch rockets' => true]])
            ->assertJsonValidationErrors(['permissions']);
        $this->beAdmin()->putJson("users/{$user->id}/permissions", ['permissions' => ['read users' => 'maybe']])
            ->assertJsonValidationErrors(['permissions.read users']);
    }

    private function rolePermissionCount(): int
    {
        return collect(UserRole::cases())->sum(fn (UserRole $role) => count($role->defaultPermissions()));
    }
}
