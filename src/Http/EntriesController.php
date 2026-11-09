<?php

declare(strict_types=1);

namespace Robuust\CraftInertiaPoc\Http;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class EntriesController
{
    public function index(): InertiaResponse
    {
        $section = Sections::getSectionByHandle(config('inertia-poc.section'));
        $entries = $section === null ? [] : Entry::find()
            ->sectionId($section->id)
            ->siteId(Sites::getCurrentSite()->id)
            ->ignorePlaceholders()
            ->orderBy('postDate', 'desc')
            ->orderBy('elements.id', 'desc')
            ->limit(20)
            ->all();

        return Inertia::render('CraftInertiaPoc/Entries/Index', [
            ...$this->pageProps(),
            'entries' => array_map($this->entrySummary(...), $entries),
            'section' => [
                'handle' => config('inertia-poc.section'),
                'exists' => $section !== null,
            ],
        ]);
    }

    public function show(int $entryId): InertiaResponse|Response
    {
        $site = Sites::getCurrentSite();
        $placeholder = Elements::getPlaceholderElement($entryId, $site->id);

        if ($placeholder instanceof Entry && $placeholder->previewing) {
            $entry = $placeholder;
        } else {
            $entry = Entry::find()
                ->id($entryId)
                ->siteId($site->id)
                ->ignorePlaceholders()
                ->one();
        }

        if (! $entry instanceof Entry || $entry->getSection()?->handle !== config('inertia-poc.section')) {
            return Inertia::render('CraftInertiaPoc/NotFound', $this->pageProps())
                ->toResponse(request())
                ->setStatusCode(404);
        }

        return Inertia::render('CraftInertiaPoc/Entries/Show', [
            ...$this->pageProps(),
            'entry' => [
                ...$this->entrySummary($entry),
                'body' => $this->textField($entry, config('inertia-poc.bodyField')),
            ],
            'isPreview' => $entry->previewing,
        ]);
    }

    /** @return array{indexUrl: string, site: array{name: string, language: string}} */
    private function pageProps(): array
    {
        $site = Sites::getCurrentSite();

        return [
            'indexUrl' => route('inertia-poc.index'),
            'site' => [
                'name' => $site->name,
                'language' => $site->language,
            ],
        ];
    }

    /** @return array{id: int, title: string, url: string, postDate: ?string, summary: string} */
    private function entrySummary(Entry $entry): array
    {
        return [
            'id' => $entry->getCanonicalId(),
            'title' => $entry->title ?? '',
            'url' => route('inertia-poc.entries.show', ['entryId' => $entry->getCanonicalId()]),
            'postDate' => $entry->postDate?->format('Y-m-d'),
            'summary' => $this->textField($entry, config('inertia-poc.summaryField')),
        ];
    }

    private function textField(Entry $entry, ?string $handle): string
    {
        if (! $handle || ! $entry->getFieldLayout()?->getFieldByHandle($handle)) {
            return '';
        }

        $value = $entry->getFieldValue($handle);

        return is_string($value) ? $value : '';
    }
}
