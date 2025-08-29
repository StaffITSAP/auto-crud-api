<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Administrator',
                'username' => 'admin',
                'password' => bcrypt('secret'), // ganti di produksi
            ]
        );
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // User biasa (read-only)
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name'     => 'Regular User',
                'username' => 'user',
                'password' => bcrypt('secret'), // ganti di produksi
            ]
        );
        if (! $user->hasRole('user')) {
            $user->assignRole('user');
        }
    }
}
