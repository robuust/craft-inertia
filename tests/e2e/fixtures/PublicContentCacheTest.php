<?php

namespace Tests\Feature;

use App\Http\PublicContentCache;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\RouteToken\RouteTokens;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\User\Models\User;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** @internal */
class PublicContentCacheTest extends TestCase
{
    use DatabaseTransactions;

    private array $writes = [];
    private array $hits = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false, 'inertia.ssr.enabled' => false, 'cache.default' => 'array', 'site-content.cacheEnabled' => true]);
        Event::listen(KeyWritten::class, function (KeyWritten $event): void {
            if (str_starts_with($event->key, 'site-content:')) {
                $this->writes[] = $event;
            }
        });
        Event::listen(CacheHit::class, function (CacheHit $event): void {
            if (str_starts_with($event->key, 'site-content:')) {
                $this->hits[] = $event->key;
            }
        });
    }

    public function testHtmlAndInertiaReuseOnlyPublicDataWithFreshPolicy(): void
    {
        $entry = $this->article('Cache public detail');
        $path = parse_url($entry->getUrl(), PHP_URL_PATH);
        $this->page($path, [])->assertOk()->assertViewIs('frontend::app');
        config(['site-content.indexableSites' => [Sites::getCurrentSite()->handle]]);

        $response = $this->page($path)->assertOk()->assertJsonPath('props.entry.title', 'Cache public detail');

        $this->page($path)->assertHeader('X-Robots-Tag', 'index, follow')->assertJsonPath('props.seo.robots', 'index, follow');
        $this->assertCount(1, $this->writes);
        $this->assertContains($this->writes[0]->key, $this->hits);
        $this->assertPublicArray($this->writes[0]->value[0]);
        $this->assertArrayNotHasKey('seo', $this->writes[0]->value[0]);
        $this->assertArrayNotHasKey('isPreview', $this->writes[0]->value[0]);
        $this->assertPrivate($response);
    }

    #[DataProvider('disabledSettings')]
    public function testCachingRequiresAnExplicitBooleanOptIn(mixed $enabled): void
    {
        config(['site-content.cacheEnabled' => $enabled]);

        $this->page('/articles')->assertOk();
        $this->page('/articles')->assertOk();

        $this->assertSame([], $this->writes);
        $this->assertSame([], $this->hits);
    }

    public static function disabledSettings(): array
    {
        return [[false], [null], ['true']];
    }

    #[DataProvider('privateInputs')]
    public function testTokenPreviewAndSessionInputsBypassCache(string $query, array $headers, array $session): void
    {
        $response = $this->withSession($session)->get('/articles'.$query, $headers + $this->headers())->assertOk();

        $this->assertSame([], $this->writes);
        $this->assertSame([], $this->hits);
        $this->assertPrivate($response);
    }

    public static function privateInputs(): array
    {
        return [
            ['?token=invalid', [], []], ['?x-craft-preview=1', [], []], ['?x-craft-live-preview=1', [], []],
            ['', ['X-Craft-Token' => 'invalid'], []], ['', ['X-Craft-Preview-Token' => 'invalid'], []],
            ['', [], ['notice' => 'Private notice']], ['', [], ['success' => 'Private success']],
            ['', [], ['error' => 'Private failure']], ['', [], ['errors' => new \Illuminate\Support\ViewErrorBag()]],
            ['', [], ['inertia.flash_data' => ['private' => 'Private flash']]], ['', [], ['_old_input' => ['email' => 'private@example.test']]],
        ];
    }

    public function testConsumedNativeRouteTokenBypassesCache(): void
    {
        $token = app(RouteTokens::class)->createToken('/articles');

        $this->page('/articles?token='.$token)->assertOk();

        $this->assertFalse(request()->query->has('token'));
        $this->assertTrue(request()->getHadToken());
        $this->assertSame([], $this->writes);
    }

    public function testNativeSiteTokenBypassesCache(): void
    {
        $token = Crypt::encrypt(Sites::getCurrentSite()->id);

        $this->page('/articles', ['X-Craft-Site-Token' => $token] + $this->headers())->assertOk();

        $this->assertSame([], $this->writes);
    }

    public function testAuthenticatedAndUnselectedPagesBypassCache(): void
    {
        $this->actingAs(User::query()->firstOrFail(), 'craft')->page('/articles')->assertOk();
        $this->assertSame([], $this->writes);
        $this->app['auth']->guard('craft')->logout();

        $this->page('/')->assertOk();

        $this->assertSame([], $this->writes);
    }

    public function testFilteredPaginatedAndSchemaInputsHaveSeparateCachedData(): void
    {
        $this->page('/articles?q=E2E%20article&sort=oldest&page=1')->assertJsonCount(20, 'props.entries');
        $this->page('/articles?q=E2E%20article&sort=oldest&page=2')->assertJsonPath('props.entries.0.title', 'E2E article 21');
        $this->page('/articles?topic=architecture&sort=title')->assertJsonCount(2, 'props.entries');
        $this->page('/articles?topic=missing')->assertJsonPath('props.entries', []);
        $this->page('/articles?q=E2E%20article&sort=oldest&page=2')->assertJsonPath('props.pagination.currentPage', 2);
        $this->assertCount(4, $this->writes);

        config(['site-content.articles.summaryField' => 'body']);
        $this->page('/articles?q=E2E%20article&sort=oldest&page=2')
            ->assertJsonPath('props.entries.0.summary', "Published body for E2E article 21\nSecond paragraph.");

        $this->assertCount(5, $this->writes);
        $this->assertCount(5, array_unique(array_map(fn (KeyWritten $event): string => $event->key, $this->writes)));
    }

    public function testNativeSaveRefreshesDetailAndOverview(): void
    {
        $entry = $this->article('Cache before save');
        $path = parse_url($entry->getUrl(), PHP_URL_PATH);
        $this->page($path)->assertJsonPath('props.entry.title', 'Cache before save');
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries.0.title', 'Cache before save');
        $this->page($path);
        $this->assertCount(2, $this->writes);
        $entry->title = 'Cache after native save';
        $entry->setFieldValue('body', 'Updated public body');
        $this->save($entry);

        $this->page($path)->assertJsonPath('props.entry.title', 'Cache after native save')->assertJsonPath('props.entry.body', 'Updated public body');
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries.0.title', 'Cache after native save');

        $this->assertCount(4, $this->writes);
    }

    public function testEmptyQueryRefreshesAfterPublishingThenUnpublishingAndDeleting(): void
    {
        $entry = $this->article('Cache lifecycle publication', false);
        $path = parse_url($entry->getUrl(), PHP_URL_PATH);
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries', []);
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries', []);
        $this->assertCount(1, $this->writes);
        $entry->enabled = true;
        $this->save($entry);

        $this->page('/articles?q=Cache')->assertJsonPath('props.entries.0.title', 'Cache lifecycle publication');
        $this->page($path)->assertOk();
        $entry->enabled = false;
        $this->save($entry);
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries', []);
        $this->page($path)->assertNotFound();
        $entry->enabled = true;
        $this->save($entry);
        $this->page($path)->assertOk();
        $this->assertTrue(Elements::deleteElement($entry));
        $this->page($path)->assertNotFound();
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries', []);
    }

    public function testNativeRelatedAssetAndNestedSavesRefreshProjectedData(): void
    {
        $fixtures = json_decode(file_get_contents(getenv('POC_FIXTURES_PATH')), true, flags: JSON_THROW_ON_ERROR);
        $entry = Entry::find()->id($fixtures['content']['article']['id'])->one();
        $path = parse_url($entry->getUrl(), PHP_URL_PATH);
        $this->page($path)->assertJsonPath('props.entry.related.0.title', 'E2E article 21');
        $related = $entry->getFieldValue('relatedArticles')->one();
        $related->title = 'Related native cache save';
        $this->save($related);
        $this->page($path)->assertJsonPath('props.entry.related.0.title', 'Related native cache save');
        $asset = $entry->getFieldValue('heroImage')->one();
        $asset->alt = 'Updated native asset description';
        $this->save($asset);
        $this->page($path)->assertJsonPath('props.entry.image.alt', 'Updated native asset description');
        $group = $entry->getFieldValue('contentBlocks')->all()[1];
        $child = $group->getFieldValue('nestedContent')->one();
        $child->setFieldValue('heading', 'Updated nested native content');
        $this->save($child);

        $this->page($path)->assertJsonPath('props.entry.blocks.1.children.0.heading', 'Updated nested native content');

        $this->assertCount(4, $this->writes);
    }

    public function testScheduledPublicationAndExpiryFollowNativeMinuteBoundariesWithoutWrites(): void
    {
        $this->travelTo(now()->setDateTime(2035, 1, 1, 12, 0, 10));
        $entry = $this->article('Cache scheduled boundary');
        $entry->postDate = now()->addMinutes(2)->setSeconds(30);
        $entry->expiryDate = now()->addMinutes(4)->setSeconds(30);
        $this->save($entry);
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries', []);
        $this->assertSame(110, $this->writes[0]->seconds);
        $this->travel(109)->seconds();
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries', []);
        $this->assertCount(1, $this->writes);

        $this->travel(1)->seconds();
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries.0.title', 'Cache scheduled boundary');
        $this->assertCount(2, $this->writes);
        $this->travel(119)->seconds();
        $this->page('/articles?q=Cache')->assertJsonCount(1, 'props.entries');
        $this->travel(1)->seconds();
        $this->page('/articles?q=Cache')->assertJsonPath('props.entries', []);
        $this->assertCount(3, $this->writes);
    }

    public function testCachedDetailDropsAnExpiredRelationWithoutAnySave(): void
    {
        $this->travelTo(now()->setDateTime(2035, 1, 1, 12, 0, 10));
        $entry = $this->article('Cache owner');
        $related = $this->article('Cache expiring relation');
        $related->expiryDate = now()->addMinutes(2)->setSeconds(30);
        $this->save($related);
        $entry->setFieldValue('relatedArticles', [$related->id]);
        $this->save($entry);
        $path = parse_url($entry->getUrl(), PHP_URL_PATH);
        $this->page($path)->assertJsonCount(1, 'props.entry.related');

        $this->travel(110)->seconds();
        $this->page($path)->assertJsonPath('props.entry.related', []);

        $this->assertCount(2, $this->writes);
    }

    public function testNewlyPublishedTaxonomyRefreshesAnEmptyFilter(): void
    {
        $section = Sections::getSectionByHandle('topics');
        $topic = new Entry(['sectionId' => $section->id, 'typeId' => $section->getEntryTypes()[0]->id,
            'siteId' => Sites::getCurrentSite()->id, 'title' => 'Cache topic', 'slug' => 'cache-topic', 'enabled' => false, 'postDate' => now()->subDay()]);
        $this->save($topic);
        $entry = $this->article('Cache taxonomy article');
        $entry->setFieldValue('topics', [$topic->id]);
        $this->save($entry);
        $this->page('/articles?topic=cache-topic')->assertJsonPath('props.entries', []);
        $topic->enabled = true;
        $this->save($topic);

        $this->page('/articles?topic=cache-topic')->assertJsonPath('props.entries.0.title', 'Cache taxonomy article');

        $this->assertCount(2, $this->writes);
    }

    public function testProjectionFailureLeavesNoCachedDataOrOpenCollector(): void
    {
        Route::middleware(['web', 'craft', \Robuust\CraftInertia\Http\HandleInertiaRequests::class])
            ->get('/cache-failure', fn () => PublicContentCache::remember(request(), ['failure'], function (): array {
                Entry::find()->section('articles')->one();
                throw new \RuntimeException('Private projection failure');
            }))->metadata(['public.ssr' => true, 'public.contentCache' => true]);

        $response = $this->page('/cache-failure')->assertStatus(500)->assertDontSee('Private projection failure');

        $this->assertSame([], $this->writes);
        $this->assertFalse(ElementCaches::isCollectingCacheInfo());
        $this->assertPrivate($response);
    }

    private function article(string $title, bool $enabled = true): Entry
    {
        $section = Sections::getSectionByHandle('articles');
        $entry = new Entry(['sectionId' => $section->id, 'typeId' => $section->getEntryTypes()[0]->id,
            'siteId' => Sites::getCurrentSite()->id, 'title' => $title, 'enabled' => $enabled, 'postDate' => now()->subDay()]);
        $entry->setAuthorIds([User::query()->firstOrFail()->getCraftUserId()]);
        $entry->setFieldValues(['summary' => 'Public cache summary', 'body' => 'Public cache body']);
        $this->save($entry);

        return $entry;
    }

    private function save(object $element): void
    {
        $this->assertTrue(Elements::saveElement($element), json_encode($element->errors()->getMessages()));
    }

    private function page(string $path, ?array $headers = null): TestResponse
    {
        // Laravel's test kernel serves multiple requests in one container; native
        // scoped element state belongs to one real HTTP request.
        $this->app->forgetScopedInstances();

        return $this->get($path, $headers ?? $this->headers());
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Inertia-Version' => Vite::manifestHash()];
    }

    private function assertPrivate(TestResponse $response): void
    {
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertContains('X-Inertia', $response->getVary());
    }

    private function assertPublicArray(array $data): void
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                $this->assertPublicArray($value);
            } else {
                $this->assertTrue(is_scalar($value) || $value === null);
            }
        }
    }
}
