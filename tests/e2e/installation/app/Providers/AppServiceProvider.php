<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/** Registers the project's public view namespace and static-page SSR boundary. */
class AppServiceProvider extends ServiceProvider
{
    /** {@inheritdoc} */
    public function boot(): void
    {
        View::addNamespace('frontend', resource_path('views'));
        Inertia::disableSsr(fn (Request $request): bool => !config('inertia.ssr.enabled')
            || !$request->routeIs('home', 'about')
            || !$request->isMethodSafe() || $request->isCpRequest() || $request->isActionRequest());
    }
}
