<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower((string) env('ADMIN_EMAIL', 'admin@finnskerrisdaleladies.com'));
        $password = env('ADMIN_PASSWORD');
        $generated = false;
        if (! $password) {
            $password = Str::password(16, symbols: false);
            $generated = true;
        }

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        if (! $user->exists) {
            $user->fill(['name' => env('ADMIN_NAME', 'Site Admin'), 'password' => $password, 'role' => 'admin', 'is_active' => true])->save();
            $this->command?->warn("Admin user: {$email}".($generated ? "  password: {$password}  (change it after first login)" : ''));
        }

        // Author account for imported/seeded blog posts (cannot log in until a password is set by an admin)
        User::firstOrCreate(['email' => 'finnsadmin@finnskerrisdaleladies.com'], [
            'name' => 'finnsadmin', 'password' => Str::random(40), 'role' => 'editor', 'is_active' => false,
        ]);
    }
}
