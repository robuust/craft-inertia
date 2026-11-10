<?php

declare(strict_types=1);

namespace Robuust\CraftInertiaPoc\Http;

use Closure;
use CraftCms\Cms\Http\ResponseHeaders;
use CraftCms\Cms\Support\Flash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Support\Header;
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

    /** {@inheritdoc} */
    #[Override]
    public function resolveValidationErrors(Request $request): object
    {
        $errors = parent::resolveValidationErrors($request);
        $message = Flash::getError();

        if ($message === null || $message === '') {
            return $errors;
        }

        $bag = $request->header(Header::ERROR_BAG);
        if ($bag) {
            if (!isset($errors->{$bag}) || (array) $errors->{$bag} === []) {
                $errors->{$bag} = (object) ['message' => $message];
            }

            return $errors;
        }

        return (array) $errors === [] ? (object) ['message' => $message] : $errors;
    }

    #[Override]
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isSiteRequest() || $request->isActionRequest()) {
            return $next($request);
        }

        app(ResponseHeaders::class)->noCache();

        $messages = array_filter([
            'notice' => Flash::getNotice(),
            'success' => Flash::getSuccess(),
            'error' => Flash::getError(),
        ], static fn (?string $message): bool => $message !== null);
        if ($messages !== []) {
            Inertia::flash(Inertia::getFlashed($request) + $messages);
        }

        $response = parent::handle($request, $next);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
