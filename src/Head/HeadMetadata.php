<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Head;

use CraftCms\Cms\Support\Url;
use InvalidArgumentException;
use Robuust\CraftInertia\Content\PublicValue;
use Robuust\CraftInertia\Http\PublicPageContext;

/** Supplies one metadata contract for Inertia Head and its server-rendered fallback. */
final class HeadMetadata
{
    /**
     * @param PublicPageContext $context Matched entry, current site/request and renderer policy.
     * @return array<string, mixed> Default entry metadata plus selected project metadata.
     */
    public function resolve(PublicPageContext $context): array
    {
        $provider = config('craft-inertia.head');
        if ($provider !== null && !is_callable($provider)) {
            throw new InvalidArgumentException('craft-inertia.head must be callable or null.');
        }
        $values = $provider === null ? [] : PublicValue::normalize($provider($context), 'craft-inertia.head');
        if (!is_array($values) || array_diff(array_keys($values), ['title', 'meta', 'link', 'jsonLd']) !== []) {
            throw new InvalidArgumentException('craft-inertia.head must return an array containing only title, meta, link or jsonLd.');
        }
        if (array_key_exists('title', $values) && !is_string($values['title'])) {
            throw new InvalidArgumentException('craft-inertia.head.title must be a string.');
        }
        foreach (['meta', 'link', 'jsonLd'] as $key) {
            if (array_key_exists($key, $values) && !is_array($values[$key])) {
                throw new InvalidArgumentException("craft-inertia.head.$key must be an array.");
            }
        }
        $url = $context->entry->getUrl();
        return PublicValue::normalize(self::normalize(
            $values['title'] ?? $context->entry->title ?? '',
            $values['meta'] ?? [],
            $values['link'] ?? ($url === null ? [] : [['rel' => 'canonical', 'href' => Url::removeAllParams(explode('#', $url, 2)[0])]]),
            $values['jsonLd'] ?? [],
            $context->robots,
        ), 'head');
    }

    /**
     * @param string $title Computed title or native entry title.
     * @param array<array-key, mixed> $meta Selected metadata attribute maps.
     * @param array<array-key, mixed> $links Selected link attribute maps.
     * @param array<array-key, mixed> $jsonLd Selected JSON-LD objects.
     * @param string $robots Renderer's maximum indexing permission.
     * @return array<string, mixed> Finite public metadata with consistent robots and safe JSON-LD text.
     */
    public static function normalize(string $title, array $meta, array $links, array $jsonLd, string $robots): array
    {
        $tags = [];
        foreach (self::attributes($meta) as $tag) {
            if (!isset($tag['content'])) {
                continue;
            }
            $name = strtolower((string) ($tag['name'] ?? $tag['http-equiv'] ?? ''));
            if (in_array($name, ['robots', 'x-robots-tag'], true)) {
                $robots = self::restrictRobots($robots, (string) $tag['content']);
                continue;
            }
            $key = $tag['name'] ?? $tag['property'] ?? $tag['http-equiv'] ?? null;
            if ($key !== null) {
                // Repeated Open Graph image properties are meaningful; only policy tags are collapsed.
                $tags[] = $tag;
            }
        }
        $tags[] = ['name' => 'robots', 'content' => $robots];
        $canonical = false;
        $selectedLinks = [];
        foreach (self::attributes($links) as $link) {
            if (!isset($link['rel'], $link['href'])) {
                continue;
            }
            if (strtolower((string) $link['rel']) === 'canonical') {
                if ($canonical) {
                    continue;
                }
                $canonical = true;
            }
            $selectedLinks[] = $link;
        }
        $scripts = [];
        foreach ($jsonLd as $key => $data) {
            $scripts[] = [
                'key' => (string) $key,
                'content' => json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            ];
        }
        return ['title' => $title, 'meta' => $tags, 'link' => $selectedLinks, 'jsonLd' => $scripts, 'robots' => $robots];
    }

    /**
     * @param string $ceiling Renderer or previously restricted robots directives.
     * @param string $editorial Additional editorial directives.
     * @return string Combined directives that cannot broaden indexing or following permission.
     */
    public static function restrictRobots(string $ceiling, string $editorial): string
    {
        $tokens = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower($ceiling.','.$editorial))))));
        $none = in_array('none', $tokens, true);
        $noindex = $none || in_array('noindex', $tokens, true);
        $nofollow = $none || in_array('nofollow', $tokens, true);
        $extra = array_values(array_diff($tokens, ['all', 'none', 'index', 'follow', 'noindex', 'nofollow']));
        if ($noindex) {
            $extra = array_values(array_diff($extra, ['indexifembedded']));
        }
        return implode(', ', [$noindex ? 'noindex' : 'index', $nofollow ? 'nofollow' : 'follow', ...$extra]);
    }

    /**
     * @param array<array-key, mixed> $items Project-selected tag attribute maps.
     * @return list<array<string, string>> Ordered attribute maps with consistent HTML boolean presence.
     */
    private static function attributes(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item) || array_is_list($item)) {
                throw new InvalidArgumentException('Head tags must be attribute maps.');
            }
            foreach ($item as $name => $value) {
                if (!is_string($name) || !preg_match('/^[A-Za-z_:][A-Za-z0-9_.:-]*$/D', $name)
                    || (!is_string($value) && !is_bool($value))) {
                    throw new InvalidArgumentException('Head attributes must have valid HTML names and string or boolean values.');
                }
                if ($value === false) {
                    unset($item[$name]);
                } elseif ($value === true) {
                    $item[$name] = '';
                }
            }
            $result[] = $item;
        }
        return $result;
    }
}
