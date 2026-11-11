<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Routing;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Element\Events\SetRoute;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\ControllerRoute;
use InvalidArgumentException;
use Robuust\CraftInertia\Http\Controllers\EntryController;

/** Selects Vue pages only after matching native entry eligibility and destinations. */
final class EntryTemplates
{
    /** @param SetRoute $event Native route selection, including prior listener decisions. */
    public function __invoke(SetRoute $event): void
    {
        $entry = $event->element;
        $request = request();
        if ($event->handled || $event->route !== null || !$entry instanceof Entry
            || !$request->isSiteRequest() || $request->isActionRequest()
            || !$request->isMethodSafe() || Cms::config()->headlessMode
            || (!$entry->previewing && $entry->getStatus() !== Entry::STATUS_LIVE)) {
            return;
        }

        $settings = $entry->getSection()?->getSiteSettings()[$entry->siteId] ?? null;
        if (!$settings?->hasUrls || $entry->getRouteDestination() !== null
            || !str_starts_with((string) $settings->template, 'inertia:')) {
            return;
        }

        $component = substr($settings->template, strlen('inertia:'));
        if (!preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$~D', $component)) {
            throw new InvalidArgumentException('An inertia: entry template must name a page component.');
        }

        $event->route = new ControllerRoute(EntryController::class, ['component' => $component]);
    }
}
