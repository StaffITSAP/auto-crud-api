<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Urutan penting:
        // 1) pastikan permission CRUD terbentuk dari semua Model AutoCrud
        $this->call(AutoCrudPermissionsSeeder::class);

        // 2) mapping permission ke roles (admin=all, user=read-only)
        $this->call(RolesAndPermissionsSeeder::class);

        // 3) bikin users dan assign role
        $this->call(UsersSeeder::class);
    }
}
