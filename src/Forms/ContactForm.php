<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Forms;

use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Validation\Events\ValidationRulesResolving;
use CraftCms\ContactForm\Validation\SubmissionRuleset;
use Illuminate\Support\Facades\Crypt;

/** Composes optional native Contact Form configuration without replacing submission handling. */
final class ContactForm
{
    /**
     * Prepare request-local form props inside the project's private rendering boundary.
     *
     * @param string $redirect Server-selected success URL, never an untrusted request value.
     * @return array{action: string, csrfToken: string, redirect: string} Native POST action and hidden inputs.
     */
    public function form(string $redirect): array
    {
        return [
            'action' => Url::actionUrl('contact-form/send'),
            'csrfToken' => csrf_token(),
            'redirect' => Crypt::encrypt($redirect),
        ];
    }

    /**
     * Append project rules while preserving native validation and unknown-field rejection.
     *
     * @param ValidationRulesResolving $event Native validation rules being resolved.
     */
    public function __invoke(ValidationRulesResolving $event): void
    {
        if (!$event->ruleset instanceof SubmissionRuleset) {
            return;
        }

        foreach (config('craft-inertia.contactForm.rules', []) as $attribute => $rules) {
            $event->addRules($attribute, $rules);
        }
    }
}
