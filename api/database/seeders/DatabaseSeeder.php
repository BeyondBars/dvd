<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends BaseSeeder
{
    public function run(): void
    {
        // Roles and abilities are defined in code (UserRole / UserAbility enums).
        Artisan::call('abilities:refresh');

        static::superAdminUser();
        static::standardUser();

        $this->call(MenuSeeder::class);
    }
}
