<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Forms;

use CraftCms\Cms\Support\Url;
use Illuminate\Support\Facades\Crypt;

/** Prepares native action form props without replacing submission handling. */
final class NativeForm
{
    /**
     * Prepare request-local form props inside the project's private rendering boundary.
     *
     * @param string $action Server-selected native action path without the action trigger.
     * @param string $redirect Server-selected success URL, never an untrusted request value.
     * @return array{action: string, csrfToken: string, redirect: string} Native action URL and hidden inputs.
     */
    public function props(string $action, string $redirect): array
    {
        return [
            'action' => Url::actionUrl($action),
            'csrfToken' => csrf_token(),
            'redirect' => Crypt::encrypt($redirect),
        ];
    }
}
