<?php

namespace App\Providers;

use CraftCms\Cms\Cms;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/** Registers the project root and excludes native CP/actions from its SSR bundle. */
class AppServiceProvider extends ServiceProvider
{
    /** Configure one-time frontend assets; entry-page privacy stays in the plugin. */
    public function boot(): void
    {
        View::addNamespace('frontend', resource_path('views'));
        Inertia::withoutSsr([Cms::config()->cpTrigger.'/*', Cms::config()->actionTrigger.'/*']);
    }
}
