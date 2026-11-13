<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Http;

use CraftCms\Cms\Support\Url;
use InvalidArgumentException;
use Robuust\CraftInertia\Content\EntryProjection;
use Robuust\CraftInertia\Content\PublicValue;

/** Resolves opt-in native page extensions inside the public rendering boundary. */
final class NativePageProps
{
    /** @var list<string> Canonical props and adapter-owned protocol names cannot be shadowed. */
    private const RESERVED = [
        'entry', 'site', 'preview', 'seo', 'errors', 'flash', 'component', 'props', 'url',
        'version', 'clearHistory', 'encryptHistory', 'deferredProps', 'mergeProps',
        'prependProps', 'deepMergeProps', 'matchPropsOn', 'scrollProps', 'onceProps',
        'preserveFragment', 'preserveBigIntegers', 'rememberedState',
        'sharedProps', 'rescuedProps', 'liveProps',
    ];

    /**
     * @param PublicPageContext $context Matched entry, site and request without canonical requery.
     * @return array<string, mixed> Canonical native props plus validated project additions.
     */
    public function resolve(PublicPageContext $context): array
    {
        $entry = $context->entry;
        $site = $context->site;
        $extra = config('craft-inertia.publicProps');
        $extra = $extra === null ? [] : $this->provide($extra, $context);
        foreach ($extra as $key => $value) {
            $root = explode('.', $key)[0];
            if (in_array($root, self::RESERVED, true)) {
                throw new InvalidArgumentException("craft-inertia.publicProps.$key collides with a reserved native or protocol prop; use a project-owned name.");
            }
        }
        return [
            'entry' => app(EntryProjection::class)->entry($entry),
            'site' => [
                'id' => $site->id, 'uid' => $site->uid, 'handle' => $site->handle,
                'name' => $site->name, 'language' => $site->language, 'baseUrl' => $site->getBaseUrl(),
            ],
            'preview' => $entry->previewing,
            'seo' => [
                'title' => $entry->title,
                'canonical' => $entry->getUrl() === null ? null : Url::removeAllParams($entry->getUrl()),
                'robots' => $context->robots,
            ],
        ] + $extra;
    }

    /**
     * @param mixed $provider Configured project callback.
     * @param PublicPageContext $context Current rendering context.
     * @return array<string, mixed> Validated public extension values.
     */
    private function provide(mixed $provider, PublicPageContext $context): array
    {
        if (!is_callable($provider)) {
            throw new InvalidArgumentException('craft-inertia.publicProps must be callable or null.');
        }
        $values = $provider($context);
        if (!is_array($values)) {
            throw new InvalidArgumentException('craft-inertia.publicProps must return an array with string keys.');
        }
        foreach ($values as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException('craft-inertia.publicProps must return an array with non-empty string keys.');
            }
        }
        return PublicValue::normalize($values, 'craft-inertia.publicProps');
    }
}
