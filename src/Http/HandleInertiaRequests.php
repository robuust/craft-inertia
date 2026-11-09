<?php

declare(strict_types=1);

namespace Robuust\CraftInertiaPoc\Http;

use Closure;
use CraftCms\Cms\Http\ResponseHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Inertia\Middleware;
use Override;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    #[Override]
    public function rootView(Request $request): string
    {
        return config('inertia-poc.rootView', 'app');
    }

    #[Override]
    public function version(Request $request): ?string
    {
        return Vite::manifestHash();
    }

    #[Override]
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isSiteRequest() || $request->isActionRequest()) {
            return $next($request);
        }

        app(ResponseHeaders::class)->noCache();

        $response = parent::handle($request, $next);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
