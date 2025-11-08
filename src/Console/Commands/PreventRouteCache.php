<?php

declare(strict_types=1);

namespace TomatoPHP\FilamentTenancy\Console\Commands;

use Illuminate\Console\Command;

class PreventRouteCache extends Command
{
    protected $signature = 'route:cache';

    protected $description = 'Route caching is disabled for multi-tenant applications';

    public function handle(): int
    {
        $this->components->error('⚠️  Route caching is DISABLED in multi-tenant applications.');
        $this->components->warn('');
        $this->components->warn('Reason: Route caching breaks domain-based tenant identification.');
        $this->components->warn('Routes must be registered dynamically based on the current tenant.');
        $this->components->warn('');
        $this->components->info('The application performs excellently without route caching.');
        $this->components->info('To clear routes: php artisan route:clear');

        return self::FAILURE;
    }
}
