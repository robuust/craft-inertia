<?php

declare(strict_types=1);

namespace Robuust\CraftInertia;

use CraftCms\Cms\Plugin\Plugin as CraftPlugin;
use Override;

final class Plugin extends CraftPlugin
{
    #[Override]
    public bool $config = false;

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/craft-inertia.php', 'craft-inertia');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/craft-inertia.php' => config_path('craft-inertia.php'),
        ], 'craft-inertia-config');
    }
}
