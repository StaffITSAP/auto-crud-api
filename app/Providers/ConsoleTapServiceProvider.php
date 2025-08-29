<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Console\Events\CommandFinished;

class ConsoleTapServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            // Hanya tanggap make:model yang sukses
            if ($event->command !== 'make:model' || $event->exitCode !== 0) return;

            try {
                // Argumen 'name' dari command
                $input = $event->input; // Symfony\Component\Console\Input\InputInterface
                $name  = $input->getArgument('name'); // e.g. Product
                if (!$name) return;

                \App\Support\CrudPermissionSync::forModelName($name);
            } catch (\Throwable $e) {
                // Jangan hentikan proses artisan meski gagal sinkron
                logger()->warning('Auto permission sync failed: '.$e->getMessage());
            }
        });
    }
}
