<?php

declare(strict_types=1);

namespace Robuust\CraftInertia;

use CraftCms\Cms\Element\Events\SetRoute;
use CraftCms\Cms\Plugin\Plugin as CraftPlugin;
use Illuminate\Support\Facades\Event;
use Override;
use Robuust\CraftInertia\Routing\EntryTemplates;

/** Registers opt-in native entry rendering and public adapter configuration. */
final class Plugin extends CraftPlugin
{
    #[Override]
    public bool $config = false;

    /** {@inheritdoc} */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/craft-inertia.php', 'craft-inertia');
    }

    /** Register native routing and publish project configuration. */
    public function boot(): void
    {
        Event::listen(SetRoute::class, EntryTemplates::class);

        $this->publishes([
            __DIR__.'/../config/craft-inertia.php' => config_path('craft-inertia.php'),
        ], 'craft-inertia-config');
    }
}
