<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AutoCrudPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $modelsDir = app_path('Models');
        if (! is_dir($modelsDir)) return;

        foreach (File::allFiles($modelsDir) as $file) {
            $fqcn = $this->fqcnFromFile($file->getPathname());
            if (! $fqcn || ! class_exists($fqcn)) continue;

            $traits = class_uses_recursive($fqcn);
            if (! in_array(\App\Models\Concerns\AutoCrud::class, $traits ?? [], true)) continue;

            $model = new $fqcn;
            if (! Schema::hasTable($model->getTable())) continue;

            $route = $this->routeNameFromModel($fqcn);

            foreach (['read','create','update','delete'] as $act) {
                Permission::findOrCreate("$route.$act", 'sanctum');
            }

            $this->command?->info("Permissions for {$route} ensured.");
        }
    }

    protected function fqcnFromFile(string $path): ?string
    {
        $code = file_get_contents($path);
        if (!preg_match('/^namespace\s+([^;]+);/m', $code, $ns)) return null;
        if (!preg_match('/class\s+([^\s{]+)/m', $code, $cl)) return null;
        return trim($ns[1]).'\\'.trim($cl[1]);
    }

    protected function routeNameFromModel(string $fqcn): string
    {
        // Boleh override dari crudOptions()['route']
        $opts  = method_exists($fqcn, 'crudOptions') ? $fqcn::crudOptions() : [];
        return $opts['route'] ?? Str::kebab(Str::pluralStudly(class_basename($fqcn)));
    }
}
