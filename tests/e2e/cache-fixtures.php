<?php

declare(strict_types=1);

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Sections;

/**
 * Native mutations in the fixture script's guarded, disposable database only.
 *
 * @param string $mode
 *
 * @return array
 */
function contentCacheFixture(string $mode): array
{
    $path = __DIR__.'/.runtime/cache-entry.json';
    if ($mode === 'cache-create') {
        $entry = createEntry(Sections::getSectionByHandle('articles'), EntryTypes::getEntryTypeByHandle('pocArticle'),
            'CacheRuntimePublication before save', ['enabled' => false]);
        $data = entryData($entry);
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));

        return $data;
    }

    $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $entry = Entry::find()->id($data['id'])->status(null)->one();
    if ($mode === 'cache-delete') {
        save($entry, Elements::deleteElement($entry));
    } else {
        $entry->enabled = $mode !== 'cache-unpublish';
        if ($mode === 'cache-update') {
            $entry->title = 'CacheRuntimePublication after native save';
            $entry->setFieldValue('body', 'Updated through the native Craft save API.');
        }
        save($entry, Elements::saveElement($entry));
    }

    return $data;
}
