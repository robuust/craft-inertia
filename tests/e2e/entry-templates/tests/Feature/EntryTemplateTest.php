<?php

namespace Tests\Feature;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Element\Events\SetRoute;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\ControllerRoute;
use CraftCms\Cms\Support\Facades\ElementCaches;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Inertia\Ssr\Gateway;
use InvalidArgumentException;
use Robuust\CraftInertia\Content\EntryProjection;
use Robuust\CraftInertia\Routing\EntryTemplates;
use Tests\TestCase;

class EntryTemplateTest extends TestCase
{
    private array $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = json_decode(file_get_contents(base_path('entries.json')), true, flags: JSON_THROW_ON_ERROR);
        config(['inertia.ssr.enabled' => false]);
        Http::preventStrayRequests();
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(\Illuminate\Foundation\Vite::class)->manifestHash()];
    }

    private function entry(): Entry
    {
        return Entry::find()->id($this->fixtures['article']['id'])->one();
    }

    public function testNativeHomeAndIndependentSchemasNeedNoEntryRoutesOrControllers(): void
    {
        foreach (['home' => 'Home', 'article' => 'Articles/Show', 'journal' => 'Journal', 'child' => 'Structure'] as $key => $component) {
            $response = $this->get($this->fixtures[$key]['url'], $this->headers());
            $response->assertOk()->assertHeader('X-Inertia', 'true')->assertJsonPath('component', $component)
                ->assertJsonPath('props.entry.id', $this->fixtures[$key]['id'])->assertJsonPath('props.preview', false);
        }
        $this->get('/journal/first', $this->headers())->assertJsonPath('props.entry.fields.teaser', 'Different schema teaser')
            ->assertJsonMissingPath('props.entry.fields.summary');
        $this->assertStringContainsString('/places/parent/child', $this->fixtures['child']['url']);
        $this->assertDirectoryDoesNotExist(app_path('Http/Controllers'));
        $this->assertStringNotContainsString('Articles', file_get_contents(base_path('routes/web.php')));
    }

    public function testOnlyExplicitNestedFieldsAndNativeAssetDataArePublic(): void
    {
        Inertia::share(['cpSecret' => 'CP secret', 'auth' => ['email' => 'secret@example.test']]);
        $response = $this->get('/articles/first', $this->headers());
        $response->assertOk()->assertJsonPath('props.entry.fields.summary', 'Public summary')
            ->assertJsonPath('props.entry.fields.featured', true)->assertJsonPath('props.entry.fields.rating', 3.25)
            ->assertJsonPath('props.entry.fields.tone.value', 'calm')
            ->assertJsonMissingPath('props.cpSecret')->assertJsonMissingPath('props.auth')
            ->assertJsonMissingPath('props.entry.fields.internalNote')->assertJsonMissingPath('props.entry.fields.editors');
        $fields = $response->json('props.entry.fields');
        $this->assertCount(1, $fields['relatedArticles']);
        $this->assertSame(['summary' => 'Related summary'], $fields['relatedArticles'][0]['fields']);
        $this->assertSame(['textBlock', 'groupBlock'], array_column($fields['contentBlocks'], 'type'));
        $this->assertSame('Nested heading', $fields['contentBlocks'][1]['fields']['nestedContent'][0]['fields']['heading']);
        $this->assertStringContainsString('<strong>safe</strong>', $fields['richText']);
        $this->assertStringNotContainsString('<script', $fields['richText']);
        $this->assertSame(320, $fields['heroImage'][0]['width']);
        $this->assertSame(180, $fields['heroImage'][0]['height']);
        $this->assertSame('Blue content fixture', $fields['heroImage'][0]['alt']);
        $this->assertStringContainsString('640w', $fields['heroImage'][0]['srcset']);
        $this->assertStringNotContainsString('secret', json_encode($fields));
        $this->assertArrayNotHasKey('image', $fields);
        $this->assertSame('CP secret', Inertia::getShared('cpSecret'));
    }

    public function testUnconfiguredFieldsAreEmptyAndAbsentLayoutFieldsAreOmitted(): void
    {
        config(['craft-inertia.pages' => []]);
        $this->get('/articles/first', $this->headers())->assertOk()->assertJsonPath('props.entry.fields', []);
        $projection = app(EntryProjection::class)->entry($this->entry(), ['doesNotExist']);
        $this->assertSame([], $projection['fields']);
        $this->assertCount(9, $projection);
    }

    public function testUnsupportedUserFieldIsAConfigurationError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported entry field editors');
        app(EntryProjection::class)->entry($this->entry(), ['editors']);
    }

    public function testCustomSerializerIsExplicitAndCannotReturnAnElement(): void
    {
        $projection = app(EntryProjection::class)->entry($this->entry(), ['summary' => ['serialize' => [self::class, 'upper']]]);
        $this->assertSame('PUBLIC SUMMARY', $projection['fields']['summary']);
        $this->expectException(InvalidArgumentException::class);
        app(EntryProjection::class)->entry($this->entry(), ['summary' => ['serialize' => fn () => $this->entry()]]);
    }

    public static function upper(mixed $value): string
    {
        return strtoupper($value);
    }

    public function testDependenciesIncludeTheMatchedEntryAndSelectedRelations(): void
    {
        ElementCaches::startCollectingCacheInfo();
        try {
            app(EntryProjection::class)->entry($this->entry(), config('craft-inertia.pages.Articles/Show.fields'));
        } finally {
            $dependencies = ElementCaches::stopCollectingCacheInfo();
        }
        $this->assertNotEmpty($dependencies);
        $this->assertStringContainsString((string) $this->fixtures['article']['id'], json_encode($dependencies));
    }

    public function testNativeDisabledFutureExpiredAndUnpublishedEntriesStayUnavailable(): void
    {
        foreach (['disabled', 'future', 'expired', 'unpublished'] as $key) {
            $this->get($this->fixtures[$key]['url'], $this->headers())->assertNotFound();
        }
    }

    public function testDraftAndRevisionTokensKeepTheirOwnNativeIdentityAndValues(): void
    {
        foreach (['draft' => 'Draft title', 'revision' => 'First article'] as $key => $title) {
            $fixture = $this->fixtures[$key];
            $response = $this->get($fixture['url'].'?token='.$fixture['token'].'&x-craft-preview=1', $this->headers())
                ->assertOk()->assertJsonPath('props.entry.id', $fixture['id'])
                ->assertJsonPath('props.entry.title', $title)->assertJsonPath('props.preview', true)
                ->assertJsonPath('props.seo.canonical', $this->fixtures['article']['url']);
            $this->assertMatchesRegularExpression('/noindex|none/', $response->headers->get('X-Robots-Tag'));
            $this->refreshApplication();
            config(['inertia.ssr.enabled' => false]);
        }
    }

    public function testForgedExpiredAndUnrelatedTokensDoNotExposeDrafts(): void
    {
        $draft = $this->fixtures['draft'];
        foreach (['forged-token', $draft['expiredToken']] as $token) {
            $response = $this->get($draft['url'].'?token='.$token.'&x-craft-preview=1', $this->headers());
            $response->assertOk()->assertJsonPath('props.entry.title', 'Published current')
                ->assertJsonPath('props.preview', false)->assertDontSee('Draft body');
        }
        $this->get($this->fixtures['disabled']['url'].'?token='.$draft['token'], $this->headers())
            ->assertNotFound()->assertDontSee('Disabled secret');
    }

    public function testNativeMultisiteCanonicalAndLocaleAreRetained(): void
    {
        $fixture = $this->fixtures['dutch'];
        $this->get($fixture['url'], $this->headers())->assertOk()
            ->assertJsonPath('props.site.handle', 'dutch')->assertJsonPath('props.site.language', 'nl')
            ->assertJsonPath('props.entry.title', 'Nederlands artikel')
            ->assertJsonPath('props.seo.canonical', $fixture['url']);
    }

    public function testNativeTwigAndOtherEndpointsKeepTheirOwners(): void
    {
        $this->get('/native', $this->headers())->assertOk()->assertHeaderMissing('X-Inertia')->assertSee('Native page');
        \CraftCms\Cms\View\TemplateMode::set(\CraftCms\Cms\View\TemplateMode::Site);
        $this->get('/twig/first', $this->headers())->assertOk()->assertHeaderMissing('X-Inertia')->assertSee('Native Twig');
        $this->get('/feed', $this->headers())->assertOk()->assertHeaderMissing('X-Inertia')->assertContent('<feed>Native feed</feed>');
        $this->get('/api/ping', $this->headers())->assertOk()->assertExactJson(['native' => true]);
        $this->get('/not-configured', $this->headers())->assertNotFound()->assertHeaderMissing('X-Inertia');
        $this->get('/actions/users/session-info', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('isGuest', true);
        $this->get('/admin/login', ['X-Inertia' => 'true', 'X-Inertia-Version' => \CraftCms\Cms\Cp\Cp::vite()->manifestHash()])
            ->assertOk()->assertJsonPath('component', 'auth/Login')->assertJsonMissingPath('props.entry');
    }

    public function testPriorHandledEventsAndExplicitNativeDestinationsTakePrecedence(): void
    {
        $this->get('/articles/first', $this->headers())->assertOk();
        $entry = $this->entry();
        $handled = new SetRoute($entry); $handled->handled = true;
        app(EntryTemplates::class)($handled); $this->assertNull($handled->route);
        $route = new ControllerRoute(self::class);
        $prior = new SetRoute($entry, $route); app(EntryTemplates::class)($prior); $this->assertSame($route, $prior->route);
        $settings = $entry->getSection()->getSiteSettings();
        $settings[$entry->siteId]->route = 'native.destination';
        $explicit = new SetRoute($entry); app(EntryTemplates::class)($explicit); $this->assertNull($explicit->route);
    }

    public function testPrivateHeadersAndManifestVersionUseTheOfficialProtocol(): void
    {
        $response = $this->get('/articles/first', $this->headers())->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('X-Inertia', $response->headers->get('Vary'));
        $this->assertSame(app(\Illuminate\Foundation\Vite::class)->manifestHash(), $response->json('version'));
        $this->get('/articles/first', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'old'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/articles/first'));
    }

    public function testNullsEmptyRelationsAndChoiceOrderingFollowNativeValues(): void
    {
        $entry = $this->entry();
        $projection = app(EntryProjection::class)->entry($entry, ['publishedAt', 'tags']);
        $this->assertSame('2026-11-01T12:00:00+00:00', $projection['fields']['publishedAt']);
        $this->assertSame(array_map(fn ($option) => $option->value, iterator_to_array($entry->getFieldValue('tags'))), array_column($projection['fields']['tags'], 'value'));
        $related = Entry::find()->section('articles')->slug('related')->one();
        $empty = app(EntryProjection::class)->entry($related, ['publishedAt', 'heroImage']);
        $this->assertNull($empty['fields']['publishedAt']);
        $this->assertSame([], $empty['fields']['heroImage']);
    }

    public function testOnlyExactTemplatePrefixAndNativeEligibilityAreIntercepted(): void
    {
        $this->get('/articles/first', $this->headers())->assertOk();
        $entry = $this->entry();
        $settings = $entry->getSection()->getSiteSettings()[$entry->siteId];
        $settings->template = 'Inertia:Articles/Show';
        $event = new SetRoute($entry); app(EntryTemplates::class)($event); $this->assertNull($event->route);
        $settings->template = 'inertia:Articles/Show'; $settings->hasUrls = false;
        $event = new SetRoute($entry); app(EntryTemplates::class)($event); $this->assertNull($event->route);
        $settings->hasUrls = true; Cms::config()->headlessMode = true;
        try {
            $event = new SetRoute($entry); app(EntryTemplates::class)($event); $this->assertNull($event->route);
        } finally { Cms::config()->headlessMode = false; }
        $this->post('/articles/first')->assertNotFound();
    }

    public function testPrivateContextOverridesAnIndexingOptIn(): void
    {
        Cms::config()->disallowRobots = false;
        config(['craft-inertia.indexingPolicy' => fn () => true]);
        $this->get('/articles/first', $this->headers())->assertOk()->assertHeader('X-Robots-Tag', 'index, follow');
        $this->withSession(['error' => 'Private state'])->get('/articles/first', $this->headers())
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertJsonPath('props.seo.robots', 'noindex, nofollow');
    }

    public function testAuthenticatedEntryHtmlNeverReachesSsr(): void
    {
        config(['inertia.ssr.enabled' => true, 'craft-inertia.indexingPolicy' => fn () => true]);
        $gateway = app(Gateway::class);
        $gateway->disable(fn () => false);
        $this->actingAs(\CraftCms\Cms\User\Models\User::query()->firstOrFail());
        $this->get('/articles/first')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        Http::assertNothingSent();
        $this->assertSame($gateway, app(Gateway::class));
    }

    public function testSiteTokenKeepsCanonicalCleanAndCannotEnableIndexingOrSsr(): void
    {
        config(['inertia.ssr.enabled' => true, 'craft-inertia.indexingPolicy' => fn () => true]);
        $token = \Illuminate\Support\Facades\Crypt::encrypt(1);
        $url = '/articles/first?'.http_build_query(['siteToken' => $token]);
        $this->get($url, $this->headers())->assertOk()
            ->assertJsonPath('props.seo.canonical', $this->fixtures['article']['url'])
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        Http::assertNothingSent();
    }

    public function testProjectionErrorsRestoreHostFactoryAndGateway(): void
    {
        $factory = app(ResponseFactory::class);
        $gateway = app(Gateway::class);
        Inertia::share('hostValue', 'Retained host value');
        config(['craft-inertia.pages.Articles/Show.fields' => ['editors']]);
        $this->withoutExceptionHandling();
        try {
            $this->get('/articles/first');
            $this->fail('Unsupported public fields must fail explicitly.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unsupported entry field editors', $exception->getMessage());
        }
        $this->assertSame($factory, app(ResponseFactory::class));
        $this->assertSame($gateway, app(Gateway::class));
        $this->assertSame('Retained host value', Inertia::getShared('hostValue'));
    }

    public function testSsrUsesTheStockGatewayAndPreservesHostCallbacksAndShares(): void
    {
        config(['inertia.ssr.enabled' => true]);
        $gateway = app(Gateway::class);
        $gateway->disable(fn () => false);
        $gateway->configureRequestUsing(fn ($request) => $request->withHeaders(['X-Host-Ssr' => 'retained']));
        Http::fake(['127.0.0.1:13729/*' => Http::response(['head' => [], 'body' => '<main>Rendered by stock SSR</main>'])]);
        $original = app(ResponseFactory::class);
        Inertia::share('hostSecret', 'Host share');
        $this->get('/articles/first')->assertOk()->assertSee('Rendered by stock SSR', false)->assertDontSee('Host share');
        Http::assertSent(fn ($request) => $request->hasHeader('X-Host-Ssr', 'retained'));
        Http::assertSentCount(1);
        $this->assertSame($gateway, app(Gateway::class));
        $this->assertSame($original, app(ResponseFactory::class));
        $this->assertSame('Host share', Inertia::getShared('hostSecret'));
        $this->withSession(['error' => 'Private form error'])->get('/articles/first')->assertOk()->assertDontSee('Rendered by stock SSR');
        Http::assertSentCount(1);
        $this->assertSame($gateway, app(Gateway::class));
        $this->assertNotNull($gateway->dispatch(['component' => 'Host', 'props' => []]));
        Http::assertSentCount(2);
    }
}
