<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AutoCrudServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api','auth:sanctum'])->prefix('api')->group(function () {
            $modelPath = app_path('Models');
            if (!is_dir($modelPath)) return;

            foreach (File::allFiles($modelPath) as $file) {
                $fqcn = $this->fqcnFromFile($file->getPathname());
                if (!$fqcn || !class_exists($fqcn)) continue;

                $traits = class_uses_recursive($fqcn);
                if (!in_array(\App\Models\Concerns\AutoCrud::class, $traits ?? [], true)) continue;

                $model = new $fqcn;
                $table = $model->getTable();
                if (!Schema::hasTable($table)) continue; // migrate dulu

                $opts  = method_exists($fqcn,'crudOptions') ? $fqcn::crudOptions() : [];
                $route = $opts['route'] ?? Str::kebab(Str::pluralStudly(class_basename($fqcn)));

                Route::get("$route", [\App\Http\Controllers\AutoCrudController::class,'index'])
                    ->name("$route.index")->middleware("permission:$route.read")
                    ->defaults('modelClass', $fqcn);

                Route::get("$route/{id}", [\App\Http\Controllers\AutoCrudController::class,'show'])
                    ->name("$route.show")->middleware("permission:$route.read")
                    ->defaults('modelClass', $fqcn);

                Route::post("$route", [\App\Http\Controllers\AutoCrudController::class,'store'])
                    ->name("$route.store")->middleware("permission:$route.create")
                    ->defaults('modelClass', $fqcn);

                Route::match(['put','patch'], "$route/{id}", [\App\Http\Controllers\AutoCrudController::class,'update'])
                    ->name("$route.update")->middleware("permission:$route.update")
                    ->defaults('modelClass', $fqcn);

                Route::delete("$route/{id}", [\App\Http\Controllers\AutoCrudController::class,'destroy'])
                    ->name("$route.destroy")->middleware("permission:$route.delete")
                    ->defaults('modelClass', $fqcn);
            }
        });
    }

    protected function fqcnFromFile(string $path): ?string
    {
        $code = file_get_contents($path);
        if (!preg_match('/^namespace\s+([^;]+);/m', $code, $ns)) return null;
        if (!preg_match('/class\s+([^\s{]+)/m', $code, $cl)) return null;
        return trim($ns[1]).'\\'.trim($cl[1]);
    }
}
