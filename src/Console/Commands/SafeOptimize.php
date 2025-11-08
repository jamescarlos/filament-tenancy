<?php

declare(strict_types=1);

namespace TomatoPHP\FilamentTenancy\Console\Commands;

use Illuminate\Console\Command;

class SafeOptimize extends Command
{
    protected $signature = 'optimize';

    protected $description = 'Optimize the application for better performance (multi-tenant safe)';

    public function handle(): int
    {
        $this->components->info('🚀 Optimizing multi-tenant application...');
        $this->newLine();

        $this->components->task('Caching configuration', function (): bool {
            $this->callSilent('config:cache');

            return true;
        });

        $this->components->task('Caching events', function (): bool {
            $this->callSilent('event:cache');

            return true;
        });

        $this->components->task('Caching views', function (): bool {
            $this->callSilent('view:cache');

            return true;
        });

        $this->components->warn('⚠️  Skipping route cache (incompatible with multi-tenancy)');

        $this->newLine();
        $this->components->info('✅ Application optimized successfully!');
        $this->components->info('💡 Routes are registered dynamically per tenant for optimal flexibility.');

        return self::SUCCESS;
    }
}
