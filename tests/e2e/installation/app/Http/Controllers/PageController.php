<?php

namespace App\Http\Controllers;

use App\Http\Middleware\PublicFrontend;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Projects explicit static page props through the selected frontend. */
class PageController
{
    /**
     * @param Request $request The current named project page request.
     * @return Response|View The project-owned Inertia page or native fallback view.
     */
    public function __invoke(Request $request): Response|View
    {
        $props = [
            'title' => $request->routeIs('home') ? 'Welcome' : 'About this site',
            'links' => ['home' => route('home'), 'about' => route('about')],
        ];

        return PublicFrontend::enabled()
            ? Inertia::render('Page', $props)
            : view('frontend::native', $props);
    }
}
