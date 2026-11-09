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
use CraftCms\Cms\Route\Data\Route as DynamicRoute;
use CraftCms\Cms\Route\Routes;
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
use Illuminate\Support\Facades\Vite;
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

/** @param list<string> $handles */
function createType(string $handle, array $handles = []): EntryType
{
    $type = EntryTypes::getEntryTypeByHandle($handle);
    if ($type !== null) {
        return $type;
    }

    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => 'Content']);
    $tab->setLayout($layout);
    $elements = [new EntryTitleField(['required' => true])];
    foreach ($handles as $handleName) {
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
    $tab->setElements($elements);
    $layout->setTabs([$tab]);
    $type = new EntryType(['name' => ucfirst($handle), 'handle' => $handle]);
    $type->setFieldLayout($layout);
    save($type, EntryTypes::saveEntryType($type));

    return $type;
}

/** @param list<EntryType> $types */
function createSection(string $handle, array $types, ?string $route = 'site.articles.show', ?string $uriFormat = null, SectionType $sectionType = SectionType::Channel): Section
{
    $section = Sections::getSectionByHandle($handle);
    if ($section !== null) {
        return $section;
    }

    $section = new Section([
        'name' => ucfirst($handle),
        'handle' => $handle,
        'type' => $sectionType,
    ]);
    $section->setEntryTypes($types);
    $section->setSiteSettings([new SectionSiteSettings([
        'siteId' => Sites::getCurrentSite()->id,
        'hasUrls' => true,
        'uriFormat' => $uriFormat ?? $handle.'/{slug}',
        'route' => $route,
        'template' => $route === null ? 'e2e/canonical' : null,
    ])]);
    save($section, Sections::saveSection($section));

    return $section;
}

/** @param array<string, mixed> $attributes
 *  @param array<string, string> $fields
 */
function createEntry(Section $section, EntryType $type, string $title, array $attributes = [], array $fields = []): Entry
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
        $fields = [
            'summary' => 'Summary for '.$title,
            'body' => "Published body for {$title}\nSecond paragraph.",
            ...$fields,
        ];
    }
    $entry->setFieldValues($fields);
    save($entry, Elements::saveElement($entry));

    return $entry;
}

/** @return array<string, mixed> */
function nativeRoutingFixtures(): array
{
    $type = createType('pocMinimal');
    $nativeNamed = routingEntry(createSection('nativeNamed', [$type], 'e2e.named', 'route-tests/named/{slug}'), $type, 'Native named destination');
    $nativeController = routingEntry(createSection('nativeController', [$type], 'CraftInertiaE2E\\NativeDestinationController@controller', 'route-tests/controller/{slug}'), $type, 'Native controller destination');
    $routingMismatches = [];
    foreach ([
        'namedUri' => ['e2e.named.uri', 'route-tests/named-uri/{slug}'],
        'namedDomain' => ['e2e.named.domain', 'route-tests/named-domain/{slug}'],
        'namedMethod' => ['e2e.named.method', 'route-tests/named-method/{slug}'],
        'controllerUri' => ['CraftInertiaE2E\\NativeDestinationController@wrongUri', 'route-tests/controller-uri/{slug}'],
        'controllerDomain' => ['CraftInertiaE2E\\NativeDestinationController@wrongDomain', 'route-tests/controller-domain/{slug}'],
        'controllerMethod' => ['CraftInertiaE2E\\NativeDestinationController@wrongMethod', 'route-tests/controller-method/{slug}'],
    ] as $handle => [$destination, $uriFormat]) {
        $routingMismatches[$handle] = entryData(routingEntry(createSection($handle, [$type], $destination, $uriFormat), $type, 'Native '.$handle.' secret'));
    }

    return [
        'nativeNamed' => entryData($nativeNamed),
        'nativeController' => entryData($nativeController),
        'routingMismatches' => $routingMismatches,
    ];
}

function routingEntry(Section $section, EntryType $type, string $title): Entry
{
    return Entry::find()->sectionId($section->id)->status(null)->one() ?? createEntry($section, $type, $title);
}

/** @return array{id: int, title: string, url: string} */
function entryData(Entry $entry): array
{
    return ['id' => $entry->id, 'title' => $entry->title, 'url' => $entry->getUrl()];
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

if ($mode === 'asset-version') {
    echo 'POC_FIXTURES_JSON='.json_encode(['version' => Vite::manifestHash()], JSON_THROW_ON_ERROR)."\n";
    exit;
}

if ($mode === 'dynamic-route') {
    $routes = app(Routes::class);
    $uid = '94dd37df-8c77-4730-b34a-3587288b2df1';
    if (($argv[2] ?? 'off') === 'on') {
        $routes->saveRoute(new DynamicRoute(['articles'], 'e2e/collision', uid: $uid));
    } else {
        $routes->deleteRouteByUid($uid);
    }
    $app->terminate();
    echo "POC_FIXTURES_JSON={}\n";
    exit;
}

if ($mode === 'publish') {
    $draft = Entry::find()->draftId((int) $argv[2])->status(null)->one();
    app(Drafts::class)->applyDraft($draft);
    $app->terminate();
    echo "POC_FIXTURES_JSON={\"published\":true}\n";
    exit;
}

if (in_array($mode, ['create-publish-case', 'create-preview-case', 'create-native-preview-case'], true)) {
    $suffix = Str::random(8);
    $sectionHandle = $mode === 'create-native-preview-case' ? 'pocNative' : 'articles';
    $entry = createEntry(Sections::getSectionByHandle($sectionHandle), EntryTypes::getEntryTypeByHandle('pocArticle'), 'E2E before '.$suffix, [
        'postDate' => Carbon::parse('2010-01-01T12:00:00Z'),
    ]);
    $data = [...entryData($entry), 'before' => $entry->title, 'after' => 'E2E after '.$suffix, 'editUrl' => $entry->getCpEditUrl()];
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

if ($mode === 'seed-routing') {
    $fixtures = [...json_decode(file_get_contents($runtime.'/fixtures.json'), true, flags: JSON_THROW_ON_ERROR), ...nativeRoutingFixtures()];
    file_put_contents($runtime.'/fixtures.json', json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $app->terminate();
    echo 'POC_FIXTURES_JSON='.json_encode($fixtures, JSON_THROW_ON_ERROR)."\n";
    exit;
}

if (is_file($runtime.'/fixtures.json')) {
    echo 'POC_FIXTURES_JSON='.file_get_contents($runtime.'/fixtures.json')."\n";
    exit;
}

$type = createType('pocArticle', ['summary', 'body']);
$minimalType = createType('pocMinimal');
$projectType = createType('pocProject', ['location']);
$homeType = createType('pocHome', ['introduction']);
$journalType = createType('pocJournal', ['teaser', 'copy']);
$caseType = createType('pocCaseStudy', ['city']);
$section = createSection('articles', [$type, $minimalType]);
$projects = createSection('projects', [$projectType], 'App\\Http\\Controllers\\SiteController@project');
createSection('pocNative', [$type], null);
createSection('pocEmpty', [$type]);
$journal = createSection('journal', [$journalType], uriFormat: 'articles/{slug}');
$caseStudies = createSection('caseStudies', [$caseType], 'App\\Http\\Controllers\\SiteController@project', 'projects/{slug}');
$routingFixtures = nativeRoutingFixtures();
$homeSection = createSection('homepage', [$homeType], 'home', '__home__', SectionType::Single);
$home = Entry::find()->sectionId($homeSection->id)->status(null)->one();
$home->title = 'E2E homepage';
$home->postDate = Carbon::parse('2020-01-01T12:00:00Z');
$home->setFieldValues(['introduction' => 'A project-owned public frontend.']);
save($home, Elements::saveElement($home));

$published = [];
for ($number = 1; $number <= 22; $number++) {
    $title = sprintf('E2E article %02d', $number);
    $entry = createEntry($section, $type, $title, [
        'postDate' => Carbon::parse('2020-01-01T12:00:00Z')->addDays($number),
    ]);
    $published[] = entryData($entry);
}

$disabled = createEntry($section, $type, 'E2E disabled secret', ['enabled' => false]);
$future = createEntry($section, $type, 'E2E future secret', ['postDate' => Carbon::parse('2099-01-01T12:00:00Z')]);
$expired = createEntry($section, $type, 'E2E expired secret', ['expiryDate' => Carbon::parse('2021-01-01T12:00:00Z')]);
$minimal = createEntry($section, $minimalType, 'E2E minimal entry', ['postDate' => Carbon::parse('2010-01-01T12:00:00Z')]);
$xss = createEntry($section, $type, '<img src=x onerror="window.__pocXss=1">', ['postDate' => Carbon::parse('2010-01-01T12:00:00Z')], [
    'summary' => '<script>window.__pocXss=2</script>',
    'body' => '<img src=x onerror="window.__pocXss=3">',
]);
$project = createEntry($projects, $projectType, 'E2E public project', fields: ['location' => 'Utrecht']);
$alternateArticle = createEntry($journal, $journalType, 'E2E journal entry', fields: ['teaser' => 'An alternate teaser', 'copy' => 'An alternate copy field']);
$alternateProject = createEntry($caseStudies, $caseType, 'E2E case study', fields: ['city' => 'Amsterdam']);

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
    'slug' => 'e2e-unpublished-draft-secret',
    'enabled' => true,
]);
$unpublished->setAuthorIds([$adminId]);
$unpublished->setFieldValues(['summary' => 'Unpublished summary secret', 'body' => 'Unpublished body secret']);
save($unpublished, app(Drafts::class)->saveElementAsDraft($unpublished, $adminId, 'E2E unpublished draft'));

$fixtures = [
    'home' => entryData($home),
    'published' => $published,
    'disabled' => entryData($disabled),
    'future' => entryData($future),
    'expired' => entryData($expired),
    'minimal' => entryData($minimal),
    'xss' => entryData($xss),
    'project' => entryData($project),
    'alternateArticle' => entryData($alternateArticle),
    'alternateProject' => entryData($alternateProject),
    ...$routingFixtures,
    'namedDraft' => [
        ...entryData($namedBase),
        'draftId' => $namedDraft->draftId,
        'token' => previewToken($namedDraft),
        'expiredToken' => previewToken($namedDraft, true),
    ],
    'unpublished' => [...entryData($unpublished), 'token' => previewToken($unpublished)],
];

file_put_contents($runtime.'/fixtures.json', json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$app->terminate();
echo 'POC_FIXTURES_JSON='.json_encode($fixtures, JSON_THROW_ON_ERROR)."\n";
