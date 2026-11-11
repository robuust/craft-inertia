<?php

namespace Tests\Feature;

use CraftCms\Cms\Plugin\Plugins;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Robuust\CraftInertia\Plugin;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InstallationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['frontend.enabled' => true, 'inertia.ssr.enabled' => false]);
        Http::preventStrayRequests();
    }

    public function testInstallationNeedsNoContentSchemaAndPublishesOnlyConfiguration(): void
    {
        foreach (['sections', 'fields', 'entries'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertTrue(app(Plugins::class)->isPluginEnabled('inertia'));
        $this->assertInstanceOf(Plugin::class, $this->app->getProvider(Plugin::class));
        $native = app(Plugins::class)->getPlugin('inertia');
        $this->assertSame('Inertia', $native->name);
        $this->assertSame('robuust/craft-inertia', $native->packageName);
        $this->assertSame('1.0.0', $native->schemaVersion);
        $published = ServiceProvider::pathsToPublish(Plugin::class);
        $this->assertSame([
            realpath(base_path('vendor/robuust/craft-inertia/config/craft-inertia.php')) => config_path('craft-inertia.php'),
        ], array_combine(array_map('realpath', array_keys($published)), array_values($published)));
    }

    public function testIntegrationConfigurationKeepsTheOfficialAdapterOwner(): void
    {
        $official = config('inertia');
        $this->assertSame('frontend::app', config('craft-inertia.rootView'));
        $this->assertArrayHasKey('ssr', $official);
        $this->assertArrayNotHasKey('rootView', $official);
        $this->assertArrayNotHasKey('indexingPolicy', $official);
        $this->artisan('vendor:publish', ['--tag' => 'craft-inertia-config'])->assertSuccessful();
        $this->assertSame($official, config('inertia'));
        $this->assertFileDoesNotExist(config_path('inertia-poc.php'));
    }

    public function testDirectHtmlUsesTheProjectRootAndBundle(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('build/assets/app-', false)
            ->assertSee('data-page', false)
            ->assertSee('Welcome')
            ->assertDontSee('public/vendor/craft', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function testInertiaUsesExplicitProjectPropsAndManifestVersion(): void
    {
        $response = $this->get('/about', $this->inertiaHeaders());

        $response->assertOk()->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Page')
            ->assertJsonPath('props.title', 'About this site')
            ->assertJsonPath('props.links.home', url('/'))
            ->assertJsonPath('props.links.about', url('/about'))
            ->assertJsonMissingPath('props.craft')
            ->assertJsonMissingPath('props.auth');
        $this->assertSame(app(\Illuminate\Foundation\Vite::class)->manifestHash(), $response->json('version'));
        $this->assertStringContainsString('X-Inertia', $response->headers->get('Vary'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function testProjectOptOutKeepsNativeContentEvenForInertiaHeaders(): void
    {
        config(['frontend.enabled' => false]);

        $this->get('/about', ['X-Inertia' => 'true'])
            ->assertOk()->assertHeaderMissing('X-Inertia')
            ->assertSee('About this site')->assertSee('Project native fallback')
            ->assertDontSee('data-page', false)->assertDontSee('build/assets', false);
        $this->assertTrue(app(Plugins::class)->isPluginEnabled('inertia'));
    }

    public function testStaleProjectAssetVersionRequiresAFullVisit(): void
    {
        $this->get('/about', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/about'));
    }

    public function testInstallingDoesNotCaptureUnconfiguredPublicUrls(): void
    {
        $this->get('/not-configured')->assertNotFound()->assertHeaderMissing('X-Inertia');
    }

    public function testUnrelatedNativeEndpointsRemainNative(): void
    {
        $this->assertNativeEndpoints();
    }

    public function testNativeDisableAndEnableRestoreConfiguredResponsesOnFreshBoots(): void
    {
        try {
            $this->pluginCommand('disable');
            $this->refreshApplication();
            config(['frontend.enabled' => true, 'inertia.ssr.enabled' => false]);

            $this->assertFalse(app(Plugins::class)->isPluginEnabled('inertia'));
            $this->assertNull($this->app->getProvider(Plugin::class));
            $this->get('/about', ['X-Inertia' => 'true'])->assertOk()
                ->assertHeaderMissing('X-Inertia')->assertSee('About this site')
                ->assertSee('Project native fallback')->assertDontSee('data-page', false);
            $this->assertNativeEndpoints();
        } finally {
            $this->pluginCommand('enable');
        }

        $this->refreshApplication();
        config(['frontend.enabled' => true, 'inertia.ssr.enabled' => false]);
        $this->assertTrue(app(Plugins::class)->isPluginEnabled('inertia'));
        $this->assertInstanceOf(Plugin::class, $this->app->getProvider(Plugin::class));
        $this->get('/about', $this->inertiaHeaders())->assertOk()
            ->assertHeader('X-Inertia', 'true')->assertJsonPath('component', 'Page');
    }

    private function assertNativeEndpoints(): void
    {
        $this->get('/native', ['X-Inertia' => 'true'])->assertOk()
            ->assertSee('Native page')->assertSee('Project native fallback')->assertHeaderMissing('X-Inertia');
        $this->get('/feed', ['X-Inertia' => 'true'])->assertOk()
            ->assertContent('<feed>Native feed</feed>')->assertHeaderMissing('X-Inertia');
        $this->get('/api/ping', ['X-Inertia' => 'true'])->assertOk()
            ->assertExactJson(['native' => true])->assertHeaderMissing('X-Inertia');
        $this->get('/actions/users/session-info', ['X-Inertia' => 'true', 'Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('isGuest', true)->assertHeaderMissing('X-Inertia');
        $this->get('/admin/login', [
            'X-Inertia' => 'true', 'Accept' => 'text/html',
            'X-Inertia-Version' => \CraftCms\Cms\Cp\Cp::vite()->manifestHash(),
        ])->assertOk()->assertJsonPath('component', 'auth/Login')->assertJsonMissingPath('props.links');
    }

    /** @return array<string, string> */
    private function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(\Illuminate\Foundation\Vite::class)->manifestHash()];
    }

    private function pluginCommand(string $action): void
    {
        (new Process([PHP_BINARY, 'artisan', 'craft:plugin:'.$action, 'inertia', '--no-interaction'], base_path()))->mustRun();
    }
}
