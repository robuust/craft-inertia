<?php

declare(strict_types=1);

namespace Robuust\CraftInertiaPoc;

use CraftCms\Cms\Element\Events\ElementPreviewTargetsResolving;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Plugin\Plugin as CraftPlugin;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Event;
use Override;

final class Plugin extends CraftPlugin
{
    #[Override]
    public bool $config = false;

    /** @var array<string, string> */
    protected array $publishables = [
        __DIR__.'/../public/build' => 'build',
    ];

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inertia-poc.php', 'inertia-poc');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'inertia-poc');

        $this->publishes([
            __DIR__.'/../config/inertia-poc.php' => config_path('inertia-poc.php'),
        ], 'inertia-poc-config');

        $paths = config('inertia.pages.paths', []);
        $paths[] = __DIR__.'/../resources/js/pages';
        config()->set('inertia.pages.paths', array_values(array_unique($paths)));

        Event::listen(ElementPreviewTargetsResolving::class, function (ElementPreviewTargetsResolving $event): void {
            $entry = $event->element;

            if (! $entry instanceof Entry || ! $entry->getCanonicalId()) {
                return;
            }

            if ($entry->getSection()?->handle !== config('inertia-poc.section')) {
                return;
            }

            $event->previewTargets[] = [
                'label' => 'Inertia + Vue proof of concept',
                'url' => 'inertia-poc/entries/'.$entry->getCanonicalId(),
                'refresh' => true,
            ];
        });
    }

    public function vite(): Vite
    {
        return (new Vite())
            ->useHotFile(__DIR__.'/../public/hot')
            ->useBuildDirectory('vendor/robuust/craft-inertia-poc/build');
    }
}
