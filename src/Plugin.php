<?php

declare(strict_types=1);

namespace Robuust\CraftInertiaPoc;

use CraftCms\Cms\Plugin\Plugin as CraftPlugin;
use Override;

final class Plugin extends CraftPlugin
{
    #[Override]
    public bool $config = false;

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inertia-poc.php', 'inertia-poc');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/inertia-poc.php' => config_path('inertia-poc.php'),
        ], 'inertia-poc-config');
    }
}
