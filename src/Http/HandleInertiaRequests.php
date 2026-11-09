<?php

declare(strict_types=1);

namespace Robuust\CraftInertiaPoc\Http;

use Closure;
use CraftCms\Cms\Http\ResponseHeaders;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Override;
use Robuust\CraftInertiaPoc\Plugin;
use Symfony\Component\HttpFoundation\Response;

final class HandleInertiaRequests extends Middleware
{
    #[Override]
    protected $rootView = 'inertia-poc::app';

    #[Override]
    protected $withoutSsr = ['inertia-poc', 'inertia-poc/*'];

    #[Override]
    public function version(Request $request): ?string
    {
        return Plugin::getInstance()->vite()->manifestHash();
    }

    /** @return array<string, mixed> */
    #[Override]
    public function share(Request $request): array
    {
        return [];
    }

    #[Override]
    public function handle(Request $request, Closure $next): Response
    {
        /** The host may already have shared application data through its web middleware. */
        Inertia::flushShared();
        app(ResponseHeaders::class)->noCache();

        $response = parent::handle($request, $next);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
