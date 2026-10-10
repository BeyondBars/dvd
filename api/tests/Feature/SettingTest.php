<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Enums\UserAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestsCanReadSettingsWithDefaults()
    {
        $this->getJson('settings')
            ->assertSuccessful()
            ->assertExactJson(['data' => Setting::DEFAULTS]);
    }

    public function testAdminCanUpdateSettings()
    {
        $this->beAdmin()->putJson('settings', ['primary_color' => '#16a34a', 'font_family' => 'serif'])
            ->assertSuccessful()
            ->assertJsonPath('data.primary_color', '#16a34a')
            ->assertJsonPath('data.font_family', 'serif')
            ->assertJsonPath('data.app_name', Setting::DEFAULTS['app_name']);

        $this->assertDatabaseHas('settings', ['key' => 'primary_color']);
        $this->getJson('settings')->assertJsonPath('data.primary_color', '#16a34a');
    }

    public function testSettingsAreValidated()
    {
        $this->beAdmin()->putJson('settings', [
            'primary_color' => 'red',
            'font_family' => 'comic-sans',
            'border_radius' => 'huge',
            'app_name' => '',
        ])->assertJsonValidationErrors(['primary_color', 'font_family', 'border_radius', 'app_name']);
    }

    public function testUnknownKeysAreIgnored()
    {
        $this->beAdmin()->putJson('settings', ['not_a_setting' => 'x'])->assertSuccessful();

        $this->assertDatabaseMissing('settings', ['key' => 'not_a_setting']);
    }

    public function testGuestsCannotChangeSettings()
    {
        $this->putJson('settings', ['primary_color' => '#16a34a'])->assertUnauthorized();
        $this->deleteJson('settings')->assertUnauthorized();
    }

    public function testUsersWithoutTheAbilityCannotChangeSettings()
    {
        $this->beUser()->putJson('settings', ['primary_color' => '#16a34a'])->assertForbidden();
        $this->beUser()->deleteJson('settings')->assertForbidden();
        $this->beUserAbleTo(UserAbility::ManageSettings->value)
            ->putJson('settings', ['primary_color' => '#16a34a'])
            ->assertSuccessful();
    }

    public function testAdminCanResetSettingsToDefaults()
    {
        Setting::store(['primary_color' => '#16a34a']);

        $this->beAdmin()->deleteJson('settings')
            ->assertSuccessful()
            ->assertJsonPath('data.primary_color', Setting::DEFAULTS['primary_color']);

        $this->assertDatabaseCount('settings', 0);
    }
}
