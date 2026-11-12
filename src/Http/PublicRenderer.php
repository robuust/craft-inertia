<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Http;

use Closure;
use CraftCms\Cms\Entry\Elements\Entry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Inertia\Ssr\DisablesSsr;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\SsrState;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/** Shares the isolated public rendering boundary across native and project controllers. */
final class PublicRenderer
{
    /**
     * @param Closure(string): array<string, mixed> $props Project props, resolved with the effective robots directive.
     * @param Entry|null $entry Craft's actual matched live or authorized preview entry.
     * @param array<string, string|list<string>> $headers Original headers to retain unless the adapter or response policy supplies them.
     */
    public function render(
        Request $request,
        string $component,
        Closure $props,
        ?Entry $entry = null,
        int $statusCode = 200,
        array $headers = [],
    ): Response {
        if (!$request->isSiteRequest() || $request->isActionRequest()) {
            throw new InvalidArgumentException('Public rendering requires a site page request.');
        }

        $original = app(ResponseFactory::class);
        $public = clone $original;
        $public->flushShared();
        $gateway = app(Gateway::class);
        $ssrState = app(SsrState::class);

        try {
            Inertia::swap($public);
            $isolated = clone $gateway;
            app()->instance(Gateway::class, $isolated);
            // SSR caches identical page data even when the gateway's eligibility changes.
            app()->instance(SsrState::class, new SsrState());

            $middleware = app(HandleEntryRequests::class);
            $guest = $statusCode === 200 && $middleware->isPublicGuest($request, $entry);
            if (!$guest || !config('inertia.ssr.enabled')) {
                if (!$isolated instanceof DisablesSsr) {
                    throw new LogicException('Public rendering requires an SSR gateway supporting Inertia\\Ssr\\DisablesSsr.');
                }
                $isolated->disable(true);
            }

            $robots = $guest ? $middleware->robots($request, $entry) : 'noindex, nofollow';

            $response = $middleware->handleWithRobots($request, function () use ($request, $component, $props, $robots, $statusCode): Response {
                return Inertia::render($component, $props($robots))->toResponse($request)->setStatusCode($statusCode);
            }, $robots);

            foreach ($headers as $name => $value) {
                if (!$response->headers->has($name)) {
                    $response->headers->set($name, $value);
                }
            }

            return $response;
        } finally {
            Inertia::swap($original);
            app()->instance(Gateway::class, $gateway);
            app()->instance(SsrState::class, $ssrState);
        }
    }
}
