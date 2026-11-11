<?php

namespace App\Http\Middleware;

use Closure;
use CraftCms\Cms\Plugin\Plugins;
use Illuminate\Http\Request;
use Robuust\CraftInertia\Http\HandleInertiaRequests;
use Symfony\Component\HttpFoundation\Response;

/** Selects the project integration using configuration and native plugin state. */
class PublicFrontend
{
    /** @return bool Whether this project currently opts in to the enabled plugin. */
    public static function enabled(): bool
    {
        return config('frontend.enabled') && app(Plugins::class)->isPluginEnabled('inertia');
    }

    /**
     * @param Request $request The current project page request.
     * @param Closure $next The route's remaining middleware and controller.
     * @return Response The selected native or Inertia response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return self::enabled()
            ? app(HandleInertiaRequests::class)->handle($request, $next)
            : $next($request);
    }
}
