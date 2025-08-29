<?php

namespace App\Models\Concerns;

trait AutoCrud
{
    public static function crudOptions(): array
    {
        return [
            'except'      => ['password','remember_token'],
            'only'        => null,   // e.g. ['name','email']
            'route'       => null,   // default: plural-kebab dari nama model
            'softDeletes' => false,  // set true bila tabel pakai soft deletes
        ];
    }
}
