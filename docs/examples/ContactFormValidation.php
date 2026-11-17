<?php

declare(strict_types=1);

namespace App\ContactForms;

use CraftCms\Cms\Validation\Events\ValidationRulesResolving;
use CraftCms\ContactForm\Validation\SubmissionRuleset;

/** Project example: append contact field requirements through native Craft validation. */
final class ContactFormValidation
{
    /**
     * Preserve native validation while adding the project's contact field requirements.
     *
     * @param ValidationRulesResolving $event Native validation rules being resolved.
     */
    public function __invoke(ValidationRulesResolving $event): void
    {
        if (!$event->ruleset instanceof SubmissionRuleset) {
            return;
        }

        $event->addRules('fromName', ['required', 'string', 'max:255']);
        $event->addRules('fromEmail', ['string', 'max:255']);
        $event->addRule('message', 'array');
        $event->addRule('message.body', 'max:10000');
    }
}
