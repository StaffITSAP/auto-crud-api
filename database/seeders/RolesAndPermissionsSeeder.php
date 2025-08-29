<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan permissions sudah ada (boleh dipanggil dulu)
        $this->call(AutoCrudPermissionsSeeder::class);

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $user  = Role::firstOrCreate(['name'  => 'user',  'guard_name' => 'sanctum']);

        // Admin: semua permission
        $allPerms = Permission::where('guard_name','sanctum')->pluck('name')->all();
        $admin->syncPermissions($allPerms);

        // User: read-only
        $readPerms = Permission::where('guard_name','sanctum')
            ->where('name', 'like', '%.read')
            ->pluck('name')->all();
        $user->syncPermissions($readPerms);
    }
}
