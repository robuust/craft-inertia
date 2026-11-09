<?php

declare(strict_types=1);

namespace CraftInertiaE2E;

use Closure;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\CurrentElement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

if (!class_exists(NativeDestinationController::class)) {
    final class NativeDestinationController
    {
        public function named(#[CurrentElement] Entry $entry): JsonResponse
        {
            return response()->json(['title' => $entry->title]);
        }

        public function controller(#[CurrentElement] Entry $entry): JsonResponse
        {
            return response()->json(['title' => $entry->title]);
        }

        public function wrongUri(#[CurrentElement] Entry $entry): JsonResponse
        {
            return response()->json(['title' => $entry->title]);
        }

        public function wrongDomain(#[CurrentElement] Entry $entry): JsonResponse
        {
            return response()->json(['title' => $entry->title]);
        }

        public function wrongMethod(#[CurrentElement] Entry $entry): JsonResponse
        {
            return response()->json(['title' => $entry->title]);
        }
    }
}

if (!class_exists(DestinationMiddleware::class)) {
    final class DestinationMiddleware
    {
        public function handle(Request $request, Closure $next): Response
        {
            $response = $next($request);
            $response->headers->set('X-E2E-Route-Middleware', 'applied');

            return $response;
        }
    }
}

Route::middleware(['craft', DestinationMiddleware::class])->group(function (): void {
    Route::get('/route-tests/named/{slug}', [NativeDestinationController::class, 'named'])->name('e2e.named');
    Route::get('/route-tests/controller/{slug}', [NativeDestinationController::class, 'controller']);
    Route::get('/registered/named-uri/{slug}', [NativeDestinationController::class, 'named'])->name('e2e.named.uri');
    Route::domain('destination.invalid')->get('/route-tests/named-domain/{slug}', [NativeDestinationController::class, 'named'])->name('e2e.named.domain');
    Route::post('/route-tests/named-method/{slug}', [NativeDestinationController::class, 'named'])->name('e2e.named.method');
    Route::get('/registered/controller-uri/{slug}', [NativeDestinationController::class, 'wrongUri']);
    Route::domain('destination.invalid')->get('/route-tests/controller-domain/{slug}', [NativeDestinationController::class, 'wrongDomain']);
    Route::post('/route-tests/controller-method/{slug}', [NativeDestinationController::class, 'wrongMethod']);
});

if (!class_exists(ObserveSiteRequest::class)) {
    final class ObserveSiteRequest
    {
        public function handle(Request $request, Closure $next): Response
        {
            $response = $next($request);
            $response->headers->set('X-E2E-Preview-Markers', json_encode([
                'token' => $request->query('token') !== null,
                'marker' => $request->query('x-craft-preview') !== null,
                'requestIsPreview' => $request->isPreview(),
            ], JSON_THROW_ON_ERROR));

            return $response;
        }
    }
}

foreach (Route::getRoutes() as $route) {
    if (in_array(\Robuust\CraftInertiaPoc\Http\HandleInertiaRequests::class, (array) $route->getAction('middleware'), true)) {
        $route->middleware(ObserveSiteRequest::class);
    }
}
