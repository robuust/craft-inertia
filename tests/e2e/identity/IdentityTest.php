<?php

namespace Tests\Feature;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Plugin\Plugins;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Runs on fresh boots before, during and after the disposable identity migration. */
class IdentityTest extends TestCase
{
    public function testNativeLifecyclePreservesProjectDataAndResponseContracts(): void
    {
        Http::preventStrayRequests();
        $stage = getenv('IDENTITY_STAGE');
        $directory = getenv('IDENTITY_REPORT_DIR');
        $legacy = str_starts_with($stage, 'legacy-');
        $handle = $legacy ? 'inertia-poc' : 'inertia';
        $class = $legacy ? 'Robuust\\CraftInertiaPoc\\Plugin' : 'Robuust\\CraftInertia\\Plugin';
        $key = $legacy ? 'inertia-poc' : 'craft-inertia';
        $enabled = in_array($stage, ['legacy-enabled', 'canonical-enabled', 'canonical-reenabled', 'canonical-restored'], true);
        $installed = !str_ends_with($stage, '-uninstalled');
        $plugins = app(Plugins::class);
        $this->assertSame($installed, $plugins->isPluginInstalled($handle));
        $this->assertSame($enabled || $stage === 'canonical-optout', $plugins->isPluginEnabled($handle));
        $this->assertSame($enabled || $stage === 'canonical-optout', $this->app->getProvider($class) !== null);
        if (!$legacy) {
            $this->assertFalse($plugins->isPluginInstalled('inertia-poc'));
            $this->assertFalse(class_exists('Robuust\\CraftInertiaPoc\\Plugin'));
        }
        $this->assertSame('frontend::upgrade', config($key.'.rootView'));
        $this->assertSame(['App\\UpgradePolicy', 'indexable'], config($key.'.indexingPolicy'));
        $this->assertSame('http://127.0.0.1:13727/custom-upgrade', config('inertia.ssr.url'));
        $this->assertFalse(config('inertia.ssr.enabled'));
        $this->assertArrayNotHasKey('rootView', config('inertia'));
        $this->assertArrayNotHasKey('indexingPolicy', config('inertia'));

        if ($stage === 'legacy-enabled') {
            Schema::create('identity_project_records', function (Blueprint $table): void {
                $table->integer('id')->primary();
                $table->text('value');
            });
            DB::table('identity_project_records')->insert(['id' => 1, 'value' => 'Preserve project data: <native> & Vue']);
        }
        $data = [];
        foreach (['users', 'elements', 'sites', 'sitegroups', 'entries', 'fields', 'sections', 'identity_project_records'] as $table) {
            $data[$table] = DB::table($table)->orderBy('id')->get()->toArray();
        }
        $project = app(ProjectConfig::class)->get();
        unset($project['plugins'], $project['dateModified']);
        $snapshot = ['data' => $data, 'projectConfig' => $project];
        if ($stage === 'legacy-enabled') {
            file_put_contents($directory.'/preserved-project.json', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }
        $this->assertSame(json_decode(file_get_contents($directory.'/preserved-project.json'), true), json_decode(json_encode($snapshot), true));
        $this->assertNotEmpty($data['users']);
        $this->assertNotEmpty($data['sites']);

        Cms::config()->disallowRobots = false;
        $html = $this->get('/about');
        $html->assertOk()->assertSee('About this site');
        $headers = ['X-Inertia' => 'true', 'Accept' => 'text/html', 'X-Inertia-Version' => app(\Illuminate\Foundation\Vite::class)->manifestHash()];
        $json = $this->get('/about', $headers);
        if ($enabled) {
            $html->assertSee('identity-custom-root', false)->assertSee('data-page', false)
                ->assertHeader('X-Robots-Tag', 'index, follow');
            $json->assertOk()->assertHeader('X-Inertia', 'true')->assertJsonPath('component', 'Page')
                ->assertJsonPath('props.title', 'About this site')->assertHeader('X-Robots-Tag', 'index, follow');
            $this->get('/', $headers)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->get('/about', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale'])
                ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/about'));
        } else {
            $html->assertSee('Project native fallback')->assertDontSee('data-page', false);
            $json->assertOk()->assertHeaderMissing('X-Inertia')->assertSee('Project native fallback');
        }
        $this->get('/not-configured')->assertNotFound()->assertHeaderMissing('X-Inertia');
        $this->get('/api/ping', $headers)->assertOk()->assertExactJson(['native' => true]);
        $this->get('/feed', $headers)->assertOk()->assertContent('<feed>Native feed</feed>');
        $this->get('/actions/users/session-info', ['Accept' => 'application/json'])->assertOk()->assertJsonPath('isGuest', true);
        file_put_contents($directory.'/'.$stage.'.json', json_encode([
            'stage' => $stage, 'handle' => $handle, 'installed' => $installed, 'enabled' => $plugins->isPluginEnabled($handle),
            'provider' => $this->app->getProvider($class) !== null,
            'dataSha256' => hash('sha256', json_encode($snapshot)),
            'rootView' => config($key.'.rootView'), 'indexingPolicy' => config($key.'.indexingPolicy'),
            'pluginRows' => DB::table('plugins')->orderBy('handle')->get()->toArray(),
            'pluginConfig' => app(ProjectConfig::class)->get('plugins'),
            'migrationTracks' => DB::table('migrations')->where('track', 'like', 'plugin:%')->get()->toArray(),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
}
