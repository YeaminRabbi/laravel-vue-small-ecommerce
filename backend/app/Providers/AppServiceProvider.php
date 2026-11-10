<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // `php artisan serve` strips non-allowlisted environment variables on Windows,
            // which breaks PHP ini loading (and extensions such as pdo_sqlite) in the server process.
            ServeCommand::$passthroughVariables = [
                ...ServeCommand::$passthroughVariables,
                'PHPRC',
                'PHP_INI_SCAN_DIR',
            ];
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
