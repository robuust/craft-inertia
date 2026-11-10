<?php

namespace App\Http\Controllers;

use App\Http\Middleware\PublicFrontend;
use CraftCms\Cms\Plugin\Plugins;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

/** Project presentation only: the plugin's native action owns all submissions. */
class ContactController
{
    public function __invoke(Request $request): Response
    {
        abort_unless(PublicFrontend::enabled() && app(Plugins::class)->isPluginEnabled('contact-form'), 404);

        if ($request->routeIs('contact.thanks')) {
            return Inertia::render('ContactThanks', ['contactUrl' => route('contact')]);
        }

        $initial = [];
        foreach (['fromName', 'fromEmail', 'subject', 'message'] as $field) {
            $value = $request->old($field, '');
            $initial[$field] = is_string($value) ? $value : '';
        }

        return Inertia::render('Contact', [
            'action' => url('/actions/contact-form/send'),
            'redirect' => Crypt::encrypt(route('contact.thanks')),
            'initial' => $initial,
        ]);
    }
}
