<?php

declare(strict_types=1);

use CraftCms\Cms\Element\Drafts;
use CraftCms\Cms\Entry\Data\EntryType;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\PlainText;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\FieldLayoutTab;
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\FieldLayout\LayoutElements\Entries\EntryTitleField;
use CraftCms\Cms\Http\Routing\ActionRoute;
use CraftCms\Cms\RouteToken\RouteTokens;
use CraftCms\Cms\Section\Data\Section;
use CraftCms\Cms\Section\Data\SectionSiteSettings;
use CraftCms\Cms\Section\Enums\SectionType;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$runtime = __DIR__.'/.runtime';
require $runtime.'/playground/vendor/autoload.php';
$app = require $runtime.'/playground/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, (string) $exception."\n");
    exit(1);
});

if (! $app->environment('e2e') || DB::selectOne('select current_database() as name')->name !== 'inertia_poc_e2e') {
    throw new RuntimeException('Fixtures may only run against the isolated E2E database.');
}

function save(object $model, bool $saved): void
{
    if (! $saved) {
        throw new RuntimeException($model::class.': '.json_encode($model->errors()->getMessages(), JSON_THROW_ON_ERROR));
    }
}

function createType(string $handle, bool $withFields): EntryType
{
    $type = EntryTypes::getEntryTypeByHandle($handle);
    if ($type !== null) {
        return $type;
    }

    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => 'Content']);
    $tab->setLayout($layout);
    $elements = [new EntryTitleField(['required' => true])];
    if ($withFields) {
        foreach (['summary', 'body'] as $handleName) {
            $field = Fields::getFieldByHandle($handleName);
            if ($field === null) {
                $field = new PlainText([
                    'name' => ucfirst($handleName),
                    'handle' => $handleName,
                    'multiline' => true,
                ]);
                save($field, Fields::saveField($field));
            }
            $elements[] = new CustomField($field);
        }
    }
    $tab->setElements($elements);
    $layout->setTabs([$tab]);
    $type = new EntryType(['name' => ucfirst($handle), 'handle' => $handle]);
    $type->setFieldLayout($layout);
    save($type, EntryTypes::saveEntryType($type));

    return $type;
}

/** @param list<EntryType> $types */
function createSection(string $handle, array $types): Section
{
    $section = Sections::getSectionByHandle($handle);
    if ($section !== null) {
        return $section;
    }

    $section = new Section([
        'name' => ucfirst($handle),
        'handle' => $handle,
        'type' => SectionType::Channel,
    ]);
    $section->setEntryTypes($types);
    $section->setSiteSettings([new SectionSiteSettings([
        'siteId' => Sites::getCurrentSite()->id,
        'hasUrls' => true,
        'uriFormat' => $handle.'/{slug}',
        'template' => 'e2e/unused-canonical-template',
    ])]);
    save($section, Sections::saveSection($section));

    return $section;
}

/** @param array<string, mixed> $attributes */
function createEntry(Section $section, EntryType $type, string $title, array $attributes = []): Entry
{
    $entry = new Entry([
        'sectionId' => $section->id,
        'typeId' => $type->id,
        'siteId' => Sites::getCurrentSite()->id,
        'title' => $title,
        'slug' => Str::slug($title) ?: 'unsafe-title-fixture',
        'enabled' => true,
        'postDate' => Carbon::parse('2020-01-01T12:00:00Z'),
        ...$attributes,
    ]);
    $entry->setAuthorIds([User::query()->firstOrFail()->getCraftUserId()]);
    if ($type->handle === 'pocArticle') {
        $entry->setFieldValues([
            'summary' => 'Summary for '.$title,
            'body' => "Published body for {$title}\nSecond paragraph.",
        ]);
    }
    save($entry, Elements::saveElement($entry));

    return $entry;
}

function previewToken(Entry $entry, bool $expired = false): string
{
    $route = [ActionRoute::uriForSegments(['preview', 'preview'], false), [
        'elementType' => Entry::class,
        'canonicalId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'draftId' => $entry->draftId,
    ]];
    $tokens = app(RouteTokens::class);
    $token = $expired
        ? $tokens->createToken($route, expiryDate: now()->subMinute())
        : $tokens->createPreviewToken($route);

    if ($token === false) {
        throw new RuntimeException('Could not mint a real Craft preview token.');
    }

    return $token;
}

$mode = $argv[1] ?? 'seed';

if ($mode === 'publish') {
    $draft = Entry::find()->draftId((int) $argv[2])->status(null)->one();
    app(Drafts::class)->applyDraft($draft);
    $app->terminate();
    echo "POC_FIXTURES_JSON=".json_encode(['published' => true], JSON_THROW_ON_ERROR)."\n";
    exit;
}

if ($mode === 'create-publish-case' || $mode === 'create-preview-case') {
    $suffix = Str::random(8);
    $entry = createEntry(Sections::getSectionByHandle('articles'), EntryTypes::getEntryTypeByHandle('pocArticle'), 'E2E before '.$suffix, [
        'postDate' => Carbon::parse('2010-01-01T12:00:00Z'),
    ]);
    $data = ['id' => $entry->id, 'before' => $entry->title, 'after' => 'E2E after '.$suffix, 'editUrl' => $entry->getCpEditUrl()];
    if ($mode === 'create-publish-case') {
        $draft = app(Drafts::class)->createDraft($entry, User::query()->firstOrFail()->getCraftUserId(), 'E2E publish case');
        $draft->title = $data['after'];
        $draft->setFieldValues(['body' => 'Published from named draft']);
        save($draft, Elements::saveElement($draft));
        $data['draftId'] = $draft->draftId;
        $data['draftEditUrl'] = $draft->getCpEditUrl();
    }
    $app->terminate();
    echo 'POC_FIXTURES_JSON='.json_encode($data, JSON_THROW_ON_ERROR)."\n";
    exit;
}

if (is_file($runtime.'/fixtures.json')) {
    echo "POC_FIXTURES_JSON=".file_get_contents($runtime.'/fixtures.json')."\n";
    exit;
}

$type = createType('pocArticle', true);
$minimalType = createType('pocMinimal', false);
$section = createSection('articles', [$type, $minimalType]);
$otherSection = createSection('pocOther', [$type]);
createSection('pocEmpty', [$type]);
$published = [];

for ($number = 1; $number <= 22; $number++) {
    $title = sprintf('E2E article %02d', $number);
    $entry = createEntry($section, $type, $title, [
        'postDate' => Carbon::parse('2020-01-01T12:00:00Z')->addDays($number),
    ]);
    $published[] = ['id' => $entry->id, 'title' => $title];
}

$disabled = createEntry($section, $type, 'E2E disabled secret', ['enabled' => false]);
$future = createEntry($section, $type, 'E2E future secret', ['postDate' => Carbon::parse('2099-01-01T12:00:00Z')]);
$expired = createEntry($section, $type, 'E2E expired secret', ['expiryDate' => Carbon::parse('2021-01-01T12:00:00Z')]);
$other = createEntry($otherSection, $type, 'E2E other section secret');
$minimal = createEntry($section, $minimalType, 'E2E minimal entry', ['postDate' => Carbon::parse('2010-01-01T12:00:00Z')]);
$xss = createEntry($section, $type, '<img src=x onerror="window.__pocXss=1">', ['postDate' => Carbon::parse('2010-01-01T12:00:00Z')]);
$xss->setFieldValues([
    'summary' => '<script>window.__pocXss=2</script>',
    'body' => '<img src=x onerror="window.__pocXss=3">',
]);
save($xss, Elements::saveElement($xss));

$livePreview = createEntry($section, $type, 'E2E Live Preview entry', ['postDate' => Carbon::parse('2011-01-01T12:00:00Z')]);
$namedBase = createEntry($section, $type, 'E2E named draft published', ['postDate' => Carbon::parse('2011-01-01T12:00:00Z')]);
$adminId = User::query()->firstOrFail()->getCraftUserId();
$namedDraft = app(Drafts::class)->createDraft($namedBase, $adminId, 'E2E named draft');
$namedDraft->title = 'E2E named draft secret';
$namedDraft->setFieldValues(['summary' => 'Named draft summary secret', 'body' => 'Named draft body secret']);
save($namedDraft, Elements::saveElement($namedDraft));

$unpublished = new Entry([
    'sectionId' => $section->id,
    'typeId' => $type->id,
    'siteId' => Sites::getCurrentSite()->id,
    'title' => 'E2E unpublished draft secret',
    'enabled' => true,
]);
$unpublished->setAuthorIds([$adminId]);
$unpublished->setFieldValues(['summary' => 'Unpublished summary secret', 'body' => 'Unpublished body secret']);
save($unpublished, app(Drafts::class)->saveElementAsDraft($unpublished, $adminId, 'E2E unpublished draft'));

$fixtures = [
    'published' => $published,
    'disabled' => $disabled->id,
    'future' => $future->id,
    'expired' => $expired->id,
    'otherSection' => $other->id,
    'minimal' => $minimal->id,
    'xss' => $xss->id,
    'livePreview' => ['id' => $livePreview->id, 'editUrl' => $livePreview->getCpEditUrl()],
    'namedDraft' => [
        'id' => $namedDraft->getCanonicalId(),
        'draftId' => $namedDraft->draftId,
        'token' => previewToken($namedDraft),
        'expiredToken' => previewToken($namedDraft, true),
    ],
    'unpublished' => ['id' => $unpublished->getCanonicalId(), 'token' => previewToken($unpublished)],
];

file_put_contents($runtime.'/fixtures.json', json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$app->terminate();
echo 'POC_FIXTURES_JSON='.json_encode($fixtures, JSON_THROW_ON_ERROR)."\n";
