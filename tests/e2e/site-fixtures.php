<?php

declare(strict_types=1);

use CraftCms\Cms\Element\Drafts;
use CraftCms\Cms\Element\Revisions;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Enums\TranslationMethod;
use CraftCms\Cms\Section\Data\SectionSiteSettings;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Models\User;

/** Seed only the isolated database guarded by fixtures.php. */
function seedSiteFixtures(array $fixtures): array
{
    CraftCms\Cms\Cms::config()->baseCpUrl = config('app.url');
    $primary = Sites::getPrimarySite();
    $sites = [];
    foreach ([
        ['dutch', 'Dutch subdirectory', 'http://127.0.0.1:18766/nl/'],
        ['dutchDomain', 'Dutch domain', 'http://nl.craft.test:18766/'],
    ] as [$handle, $name, $baseUrl]) {
        $site = Sites::getSiteByHandle($handle) ?? new Site([
            'groupId' => $primary->groupId, 'name' => $name, 'handle' => $handle,
            'language' => 'nl', 'baseUrl' => $baseUrl,
        ]);
        save($site, Sites::saveSite($site));
        $sites[] = $site;
    }
    foreach (['summary', 'body', 'introduction', 'tagline', 'heading', 'blockCopy', 'richText'] as $handle) {
        $field = Fields::getFieldByHandle($handle);
        $field->translationMethod = TranslationMethod::Site;
        save($field, Fields::saveField($field));
    }
    foreach (['contentBlocks', 'nestedContent'] as $handle) {
        $field = Fields::getFieldByHandle($handle);
        $field->propagationMethod = CraftCms\Cms\Element\Enums\PropagationMethod::None;
        save($field, Fields::saveField($field));
    }
    Fields::refreshFields();
    EntryTypes::refreshEntryTypes();

    foreach (['articles', 'homepage', 'siteSettings', 'topics', 'projects'] as $handle) {
        $section = Sections::getSectionByHandle($handle);
        $settings = $section->getSiteSettings();
        $source = $settings[$primary->id];
        foreach ($sites as $site) {
            $prefix = $site->handle.'.';
            $settings[$site->id] = new SectionSiteSettings([
                'siteId' => $site->id, 'enabledByDefault' => true,
                'hasUrls' => $source->hasUrls,
                'uriFormat' => match ($handle) {
                    'articles' => 'artikelen/{slug}', 'projects' => 'projecten/{slug}',
                    default => $source->uriFormat,
                },
                'route' => match ($handle) {
                    'articles' => $prefix.'site.articles.show', 'homepage' => $prefix.'home',
                    'projects' => $prefix.'site.projects.show', default => null,
                },
            ]);
        }
        $section->setSiteSettings($settings);
        save($section, Sections::saveSection($section));
        foreach (Entry::find()->sectionId($section->id)->siteId($primary->id)->status(null)->all() as $entry) {
            save($entry, Elements::saveElement($entry));
        }
    }

    $primaryArticle = Entry::find()->id($fixtures['content']['article']['id'])->siteId($primary->id)->one();
    $primaryArticle->setFieldValue('contentBlocks', contentBlocks('Article'));
    save($primaryArticle, Elements::saveElement($primaryArticle, propagate: false));

    $config = config('site-content');
    $config['routes'] = [
        'dutchDomain' => ['name' => 'dutchDomain.', 'domain' => 'nl.craft.test', 'prefix' => '', 'articles' => 'artikelen', 'projects' => 'projecten'],
        'dutch' => ['name' => 'dutch.', 'domain' => null, 'prefix' => 'nl', 'articles' => 'artikelen', 'projects' => 'projecten'],
        $primary->handle => ['name' => '', 'domain' => null, 'prefix' => '', 'articles' => 'articles', 'projects' => 'projects'],
    ];
    file_put_contents(config_path('site-content.php'), '<?php return '.var_export($config, true).";\n");

    $result = [];
    $adminId = User::query()->firstOrFail()->getCraftUserId();
    foreach ($sites as $index => $site) {
        Sites::setCurrentSite($site);
        $label = $index === 0 ? 'Pad' : 'Domein';
        $article = Entry::find()->id($fixtures['content']['article']['id'])->siteId($site->id)->one();
        $article->title = $label.' vertaald artikel';
        $article->slug = strtolower($label).'-vertaald-artikel';
        $article->setFieldValues([
            'summary' => $label.' samenvatting', 'body' => $label.' gepubliceerde inhoud',
            'richText' => '## '.$label.' rijke tekst',
            'contentBlocks' => contentBlocks($label),
        ]);
        save($article, Elements::saveElement($article, propagate: false));
        $related = Entry::find()->id($fixtures['content']['related']['id'])->siteId($site->id)->one();
        $related->title = $label.' verwant artikel';
        $related->slug = strtolower($label).'-verwant';
        save($related, Elements::saveElement($related, propagate: false));
        $topic = Entry::find()->id($fixtures['content']['topics'][0]['id'])->siteId($site->id)->one();
        $topic->title = $label.' architectuur';
        $topic->slug = strtolower($label).'-architectuur';
        save($topic, Elements::saveElement($topic, propagate: false));
        $home = Entry::find()->id($fixtures['home']['id'])->siteId($site->id)->one();
        $home->title = $label.' startpagina';
        $home->setFieldValue('introduction', $label.' welkom');
        save($home, Elements::saveElement($home, propagate: false));
        $settings = Entry::find()->section('siteSettings')->siteId($site->id)->one();
        $settings->setFieldValue('tagline', $label.' gedeelde inhoud');
        save($settings, Elements::saveElement($settings, propagate: false));

        $draft = app(Drafts::class)->createDraft($article, $adminId, $label.' named draft');
        $draft->title = $label.' concept geheim';
        $draft->setFieldValue('body', $label.' concept inhoud');
        save($draft, Elements::saveElement($draft, propagate: false));
        $result[$site->handle] = [
            'id' => $site->id, 'uid' => $site->uid, 'handle' => $site->handle,
            'name' => $site->name, 'language' => $site->language, 'baseUrl' => $site->getBaseUrl(),
            'article' => entryData($article), 'related' => entryData($related),
            'home' => entryData($home), 'topic' => entryData($topic),
            'tagline' => $settings->getFieldValue('tagline'), 'label' => $label,
            'draft' => [...entryData($draft), 'token' => previewToken($draft), 'editUrl' => $draft->getCpEditUrl()],
        ];
    }
    Sites::setCurrentSite($primary);
    $type = EntryTypes::getEntryTypeByHandle('pocArticle');
    $section = Sections::getSectionByHandle('articles');
    $revisionBase = createEntry($section, $type, 'Revision history secret');
    $revisionElementId = app(Revisions::class)->createRevision($revisionBase, $adminId, 'STEP03 revision', force: true);
    $revision = Entry::find()->id($revisionElementId)->revisions()->status(null)->one();
    $revisionId = $revision->revisionId;
    $revisionBase->title = 'Published after revision';
    save($revisionBase, Elements::saveElement($revisionBase));
    $revisionToken = app(CraftCms\Cms\RouteToken\RouteTokens::class)->createPreviewToken([
        CraftCms\Cms\Http\Routing\ActionRoute::uriForSegments(['preview', 'preview'], false), [
            'elementType' => Entry::class, 'canonicalId' => $revisionBase->id,
            'siteId' => $primary->id, 'revisionId' => $revisionId,
        ],
    ]);
    $provisionalBase = createEntry($section, $type, 'Provisional published');
    $provisional = app(Drafts::class)->createDraft($provisionalBase, $adminId, provisional: true);
    $provisional->title = 'Provisional secret';
    $provisional->setFieldValue('body', 'Provisional body secret');
    save($provisional, Elements::saveElement($provisional));
    $userToken = app(CraftCms\Cms\RouteToken\RouteTokens::class)->createPreviewToken([
        CraftCms\Cms\Http\Routing\ActionRoute::uriForSegments(['preview', 'preview'], false), [
            'elementType' => Entry::class, 'canonicalId' => $provisionalBase->id,
            'siteId' => $primary->id, 'userId' => $adminId,
        ],
    ]);

    return [
        'sites' => $result, 'primaryId' => $primary->id,
        'primaryArticle' => $fixtures['content']['article'],
        'revision' => [...entryData($revisionBase), 'secret' => $revision->title, 'token' => $revisionToken],
        'provisional' => [...entryData($provisionalBase), 'secret' => $provisional->title, 'token' => $userToken],
    ];
}
