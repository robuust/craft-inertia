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

// A real native trait failure with no field errors, scoped to this test runtime.
if (app()->environment('e2e')) {
    Route::post('/e2e/native-failure', function (): Response {
        $action = new class {
            use \CraftCms\Cms\Http\RespondsWithFlash;
        };

        return $action->asFailure('Native action refused.', ['errorCode' => 'fixture-refusal']);
    });
}

if (app()->environment('e2e')) {
    foreach (['', '/nl'] as $prefix) {
        Route::middleware(['craft', \Robuust\CraftInertiaPoc\Http\HandleInertiaRequests::class, 'auth:craft'])
            ->get($prefix.'/e2e/native-failure-page', function () {
                return app(\App\Http\Controllers\FileController::class)->index()->with('nativeEntry', [
                    'entryId' => 1, 'siteId' => \CraftCms\Cms\Support\Facades\Sites::getCurrentSite()->id,
                    'title' => 'Native failure fixture', 'redirect' => '', 'action' => '/e2e/native-failure',
                ]);
            });
    }
}

if (app()->environment('e2e')) {
    Route::middleware(['craft', \Robuust\CraftInertiaPoc\Http\HandleInertiaRequests::class])
        ->prefix('e2e/rendering')->group(function (): void {
            Route::get('error/{code}', function (int $code, Request $request): never {
                config(['app.debug' => $request->boolean('debug')]);
                if ($code === 500) {
                    throw new \RuntimeException('Synthetic exception detail must not be projected');
                }
                abort($code, 'Synthetic exception detail must not be projected', ['Retry-After' => '120']);
            });
            Route::get('redirect', fn () => redirect('/articles', 307));
            Route::get('page', function (Request $request) {
                if ($request->boolean('fallback')) {
                    config(['inertia.ssr.url' => 'http://127.0.0.1:1', 'inertia.ssr.throw_on_error' => false]);
                }

                return \Inertia\Inertia::render('Site/Home', [
                    'entry' => ['title' => 'Public rendering fixture', 'introduction' => 'Published synthetic content'],
                    'site' => ['name' => 'Fixture', 'language' => 'en'], 'homeUrl' => '/',
                    'indexUrl' => '/e2e/rendering/error/404', 'isPreview' => false,
                    'seo' => ['title' => 'Public rendering fixture', 'description' => 'Fixture description',
                        'canonical' => url('/e2e/rendering/page'), 'alternates' => []],
                ]);
            })->metadata(['public.ssr' => true]);
        });
}
