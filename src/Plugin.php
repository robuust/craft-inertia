<?php

declare(strict_types=1);

namespace Robuust\CraftInertia;

use CraftCms\Cms\Element\Events\SetRoute;
use CraftCms\Cms\Plugin\Plugin as CraftPlugin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Override;
use Robuust\CraftInertia\Console\DoctorCommand;
use Robuust\CraftInertia\Console\ScaffoldCommand;
use Robuust\CraftInertia\Routing\EntryTemplates;

/** Registers native entry rendering, public adapter configuration and setup commands. */
final class Plugin extends CraftPlugin
{
    #[Override]
    public bool $config = false;

    /** @var list<class-string<Command>> */
    #[Override]
    protected array $commands = [
        DoctorCommand::class,
        ScaffoldCommand::class,
    ];

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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'craft-inertia');

        $this->publishes([
            __DIR__.'/../config/craft-inertia.php' => config_path('craft-inertia.php'),
        ], 'craft-inertia-config');
    }
}
