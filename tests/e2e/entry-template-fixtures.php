<?php

declare(strict_types=1);

use CraftCms\Cms\Element\Drafts;
use CraftCms\Cms\Element\Revisions;
use CraftCms\Cms\Entry\Data\EntryType;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\FieldLayoutTab;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\FieldLayout\LayoutElements\Entries\EntryTitleField;
use CraftCms\Cms\Http\Routing\ActionRoute;
use CraftCms\Cms\RouteToken\RouteTokens;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Data\SectionSiteSettings;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$application = getenv('POC_ENTRY_APPLICATION') ?: __DIR__.'/.runtime/entry-templates';
require $application.'/vendor/autoload.php';
$app = require $application.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, (string) $exception."\n");
    exit(1);
});
if (DB::selectOne('select current_database() as name')->name !== 'clean_installation'
    || config('app.url') !== 'http://127.0.0.1:18779') {
    throw new RuntimeException('Entry fixtures require the isolated entry-template consumer.');
}
function save(object $model, bool $saved): void
{
    if (!$saved) {
        throw new RuntimeException($model::class.': '.json_encode($model->errors()->getMessages(), JSON_THROW_ON_ERROR));
    }
}
function createType(string $handle, array $handles = []): EntryType
{
    if ($type = EntryTypes::getEntryTypeByHandle($handle)) {
        return $type;
    }
    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => 'Content']);
    $tab->setLayout($layout);
    $items = [new EntryTitleField(['required' => true])];
    foreach ($handles as $name) {
        $field = Fields::getFieldByHandle($name);
        if (!$field) {
            $field = new Field\PlainText(['name' => ucfirst($name), 'handle' => $name]);
            save($field, Fields::saveField($field));
        }
        $items[] = new CustomField($field);
    }
    $tab->setElements($items);
    $layout->setTabs([$tab]);
    $type = new EntryType(['name' => ucfirst($handle), 'handle' => $handle]);
    $type->setFieldLayout($layout);
    save($type, EntryTypes::saveEntryType($type));
    return EntryTypes::getEntryTypeByHandle($handle);
}
function section(string $handle, EntryType $type, string $template, ?string $uri = null, SectionType $kind = SectionType::Channel): Section
{
    if ($section = Sections::getSectionByHandle($handle)) {
        return $section;
    }
    $section = new Section(['name' => ucfirst($handle), 'handle' => $handle, 'type' => $kind]);
    $section->setEntryTypes([$type]);
    $section->setSiteSettings([new SectionSiteSettings([
        'siteId' => Sites::getCurrentSite()->id, 'hasUrls' => true,
        'uriFormat' => $uri ?? $handle.'/{slug}', 'template' => $template,
    ])]);
    save($section, Sections::saveSection($section));
    return Sections::getSectionByHandle($handle);
}
function createEntry(Section $section, EntryType $type, string $title, array $attributes = [], array $fields = []): Entry
{
    $entry = new Entry([
        'sectionId' => $section->id, 'typeId' => $type->id, 'siteId' => Sites::getCurrentSite()->id,
        'title' => $title, 'slug' => strtolower(str_replace(' ', '-', $title)),
        'enabled' => true, 'postDate' => now()->subYear(), ...$attributes,
    ]);
    $entry->setAuthorIds([User::query()->firstOrFail()->getCraftUserId()]);
    $entry->setFieldValues($fields);
    save($entry, Elements::saveElement($entry));
    return $entry;
}
function data(Entry $entry): array
{
    return ['id' => $entry->id, 'title' => $entry->title, 'url' => $entry->getUrl()];
}
function token(Entry $entry, bool $expired = false): string
{
    $route = [ActionRoute::uriForSegments(['preview', 'preview'], false), [
        'elementType' => Entry::class, 'canonicalId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId, 'draftId' => $entry->draftId, 'revisionId' => $entry->revisionId,
    ]];
    return $expired ? app(RouteTokens::class)->createToken($route, expiryDate: now()->subMinute())
        : app(RouteTokens::class)->createPreviewToken($route);
}
require_once __DIR__.'/content-fixtures.php';
if (($argv[1] ?? 'seed') === 'editor') {
    $section = Sections::getSectionByHandle('articles');
    $type = EntryTypes::getEntryTypeByHandle('article');
    $editor = createEntry($section, $type, 'Editor preview '.bin2hex(random_bytes(3)), [], ['summary' => 'Editor public summary', 'body' => 'Editor public body']);
    echo json_encode([...data($editor), 'editUrl' => $editor->getCpEditUrl()], JSON_THROW_ON_ERROR);
    exit;
}
if (is_file($application.'/entries.json')) {
    throw new RuntimeException('Entry fixture already seeded; preserve it and use a fresh runtime.');
}
foreach ([
    new Field\Markdown(['name' => 'Rich text', 'handle' => 'richText']),
    new Field\Markdown(['name' => 'Block copy', 'handle' => 'blockCopy']),
    new Field\Assets(['name' => 'Hero image', 'handle' => 'heroImage']),
    new Field\Entries(['name' => 'Related articles', 'handle' => 'relatedArticles']),
    new Field\Date(['name' => 'Published at', 'handle' => 'publishedAt']),
    new Field\Lightswitch(['name' => 'Featured', 'handle' => 'featured']),
    new Field\Number(['name' => 'Rating', 'handle' => 'rating', 'decimals' => 2]),
    new Field\Dropdown(['name' => 'Tone', 'handle' => 'tone', 'options' => [['label' => 'Calm', 'value' => 'calm']]]),
    new Field\Checkboxes(['name' => 'Tags', 'handle' => 'tags', 'options' => [['label' => 'One', 'value' => 'one'], ['label' => 'Two', 'value' => 'two']]]),
    new Field\Users(['name' => 'Editors', 'handle' => 'editors']),
] as $field) {
    if (!Fields::getFieldByHandle($field->handle)) {
        save($field, Fields::saveField($field));
    }
}
$text = createType('textBlock', ['heading', 'blockCopy', 'internalNote']);
$nested = new Field\Matrix(['name' => 'Nested content', 'handle' => 'nestedContent']);
$nested->setEntryTypes([$text]);
if (!Fields::getFieldByHandle($nested->handle)) { save($nested, Fields::saveField($nested)); }
$group = createType('groupBlock', ['heading', 'nestedContent']);
$blocks = new Field\Matrix(['name' => 'Content blocks', 'handle' => 'contentBlocks']);
$blocks->setEntryTypes([$text, $group]);
if (!Fields::getFieldByHandle($blocks->handle)) { save($blocks, Fields::saveField($blocks)); }
$articleType = createType('article', ['summary', 'body', 'richText', 'heroImage', 'relatedArticles', 'contentBlocks', 'internalNote', 'publishedAt', 'featured', 'rating', 'tone', 'tags', 'editors']);
$articles = section('articles', $articleType, 'inertia:Articles/Show');
$hidden = createEntry($articles, $articleType, 'Disabled secret', ['enabled' => false]);
$related = createEntry($articles, $articleType, 'Related', [], ['summary' => 'Related summary', 'internalNote' => 'Related secret']);
$asset = contentImage();
$article = createEntry($articles, $articleType, 'First article', ['slug' => 'first'], [
    'summary' => 'Public summary', 'body' => 'Public body', 'internalNote' => 'Internal secret',
    'richText' => "## Native rich text\n\nA **safe** paragraph.\n\n<script>window.__entryXss=1</script>",
    'heroImage' => [$asset->id], 'relatedArticles' => [$related->id, $hidden->id],
    'publishedAt' => '2026-11-01T12:00:00+00:00', 'featured' => true, 'rating' => 3.25,
    'tone' => 'calm', 'tags' => ['two', 'one'],
    'contentBlocks' => [
        'new1' => ['type' => 'textBlock', 'title' => 'First block', 'enabled' => true, 'fields' => ['heading' => 'First heading', 'blockCopy' => 'Native **block**', 'internalNote' => 'Block secret']],
        'new2' => ['type' => 'groupBlock', 'title' => 'Group', 'enabled' => true, 'fields' => ['heading' => 'Group heading', 'nestedContent' => [
            'new1' => ['type' => 'textBlock', 'title' => 'Nested', 'enabled' => true, 'fields' => ['heading' => 'Nested heading', 'blockCopy' => 'Nested **copy**', 'internalNote' => 'Nested secret']],
        ]]],
    ],
]);
$homeType = createType('home', ['introduction', 'internalNote']);
$homeSection = section('homepage', $homeType, 'inertia:Home', '__home__', SectionType::Single);
$home = Entry::find()->sectionId($homeSection->id)->one();
$home->title = 'Native home'; $home->setFieldValues(['introduction' => 'Welcome from Craft', 'internalNote' => 'Home secret']);
save($home, Elements::saveElement($home));
$journalType = createType('journal', ['teaser', 'copy', 'internalNote']);
$journal = createEntry(section('journal', $journalType, 'inertia:Journal'), $journalType, 'Journal story', ['slug' => 'first'], ['teaser' => 'Different schema teaser', 'copy' => 'Different schema copy', 'internalNote' => 'Journal secret']);
$twig = createEntry(section('twig', $journalType, 'plain'), $journalType, 'Twig entry', ['slug' => 'first']);
$placeType = createType('place', ['location']);
$places = section('places', $placeType, 'inertia:Structure', '{parent.uri ?? "places"}/{slug}', SectionType::Structure);
$parent = createEntry($places, $placeType, 'Parent', [], ['location' => 'Parent place']);
$child = createEntry($places, $placeType, 'Child', ['parentId' => $parent->id], ['location' => 'Nested place']);
$future = createEntry($articles, $articleType, 'Future secret', ['postDate' => now()->addDay()]);
$expired = createEntry($articles, $articleType, 'Expired secret', ['expiryDate' => now()->subDay()]);
$unpublished = new Entry(['sectionId' => $articles->id, 'typeId' => $articleType->id, 'siteId' => Sites::getCurrentSite()->id, 'title' => 'Unpublished secret', 'slug' => 'unpublished-secret', 'enabled' => true]);
$unpublished->setAuthorIds([User::query()->firstOrFail()->getCraftUserId()]);
save($unpublished, app(Drafts::class)->saveElementAsDraft($unpublished, User::query()->firstOrFail()->getCraftUserId(), 'Unpublished entry'));
$admin = User::query()->firstOrFail()->getCraftUserId();
$draft = app(Drafts::class)->createDraft($article, $admin, 'Entry preview');
$draft->title = 'Draft title'; $draft->setFieldValue('body', 'Draft body'); save($draft, Elements::saveElement($draft));
$revisionElementId = app(Revisions::class)->createRevision($article, $admin, 'Entry revision', force: true);
$revision = Entry::find()->id($revisionElementId)->revisions()->status(null)->one();
$article->title = 'Published current'; save($article, Elements::saveElement($article));
$primary = Sites::getPrimarySite();
$dutch = new Site(['groupId' => $primary->groupId, 'name' => 'Dutch', 'handle' => 'dutch', 'language' => 'nl', 'baseUrl' => 'http://127.0.0.1:18779/nl/']); save($dutch, Sites::saveSite($dutch));
$settings = $articles->getSiteSettings(); $settings[$dutch->id] = new SectionSiteSettings(['siteId' => $dutch->id, 'hasUrls' => true, 'uriFormat' => 'artikelen/{slug}', 'template' => 'inertia:Articles/Show']); $articles->setSiteSettings($settings); save($articles, Sections::saveSection($articles)); save($article, Elements::saveElement($article));
$nl = Entry::find()->id($article->id)->siteId($dutch->id)->one(); $nl->title = 'Nederlands artikel'; $nl->slug = 'vertaald'; save($nl, Elements::saveElement($nl, propagate: false));
$editor = createEntry($articles, $articleType, 'Editor preview', [], ['summary' => 'Editor public summary', 'body' => 'Editor public body']);
$result = [
    'editor' => [...data($editor), 'editUrl' => $editor->getCpEditUrl()],
    'home' => data($home), 'article' => data($article), 'journal' => data($journal), 'child' => data($child), 'dutch' => data($nl),
    'disabled' => data($hidden), 'future' => data($future), 'expired' => data($expired), 'unpublished' => data($unpublished),
    'draft' => [...data($draft), 'token' => token($draft), 'expiredToken' => token($draft, true)],
    'revision' => [...data($revision), 'token' => token($revision)],
];
file_put_contents($application.'/entries.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
