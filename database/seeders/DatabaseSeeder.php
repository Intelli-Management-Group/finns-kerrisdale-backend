<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            SettingsSeeder::class,
            MenuSeeder::class,
            BrandSeeder::class,
            RedirectSeeder::class,
            ContentSeeder::class,
        ]);
    }
}
