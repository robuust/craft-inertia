<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Robuust\CraftInertiaPoc\Http\HandleInertiaRequests;
use Symfony\Component\HttpFoundation\Response;

/** Applies project privacy headers without adapting native action responses. */
class ContactPrivacy
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::protect($request, $next($request));
    }

    public static function protect(Request $request, Response $response): Response
    {
        return $request->is('contact', 'contact/*', 'actions/contact-form/send')
            ? app(HandleInertiaRequests::class)->protectResponse($response)
            : $response;
    }
}
