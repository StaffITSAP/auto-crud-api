<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CrudPermissionSync
{
    public static function forModelName(string $modelName): void
    {
        // Tentukan FQCN model (diasumsikan di App\Models)
        $fqcn = '\\App\\Models\\'.Str::studly($modelName);
        if (!class_exists($fqcn)) return;

        // Pastikan tabelnya ada → bila belum migrate, tunda (biar tidak error)
        $table = (new $fqcn)->getTable();
        if (!Schema::hasTable($table)) return;

        // Nama resource = plural-kebab dari nama class
        $resource = Str::kebab(Str::pluralStudly(class_basename($fqcn)));

        // Buat permission dasar CRUD
        foreach (['read','create','update','delete'] as $act) {
            Permission::findOrCreate("$resource.$act", 'sanctum');
        }

        // Pastikan role admin punya semua permission
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $perms = Permission::where('guard_name','sanctum')->pluck('name')->all();
        $admin->syncPermissions($perms);
    }
}
