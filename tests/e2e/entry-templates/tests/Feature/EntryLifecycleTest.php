<?php

namespace Tests\Feature;

use CraftCms\Cms\Plugin\Plugins;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EntryLifecycleTest extends TestCase
{
    private function command(string ...$arguments): void
    {
        (new Process([PHP_BINARY, 'artisan', ...$arguments], base_path()))->mustRun();
    }

    private function template(string $template): void
    {
        $code = '$s=\\CraftCms\\Cms\\Support\\Facades\\Sections::getSectionByHandle("articles");'
            .'$settings=$s->getSiteSettings();foreach($settings as $setting){$setting->template='.var_export($template, true).';}'
            .'$s->setSiteSettings($settings);if(!\\CraftCms\\Cms\\Support\\Facades\\Sections::saveSection($s)){throw new \\RuntimeException("Fixture template save failed");}';
        $this->command('tinker', '--execute='.$code);
    }

    private function bootFresh(): void
    {
        $this->refreshApplication();
        config(['inertia.ssr.enabled' => false]);
        \CraftCms\Cms\View\TemplateMode::set(\CraftCms\Cms\View\TemplateMode::Site);
    }

    public function testNativeLifecycleWithCachedRoutesNeedsAnExplicitWorkingFallback(): void
    {
        try {
            $this->command('route:cache', '--no-interaction');
            $this->command('config:clear', '--no-interaction');
            $this->command('config:cache', '--no-interaction');
            $this->bootFresh();
            $this->get('/articles/first')->assertOk()->assertSee('data-page', false);
            $this->command('craft:plugin:disable', 'inertia', '--no-interaction');
            $this->bootFresh();
            $this->assertFalse(app(Plugins::class)->isPluginEnabled('inertia'));
            $this->get('/articles/first')->assertNotFound()->assertHeaderMissing('X-Inertia');
            $this->template('plain');
            $this->bootFresh();
            $this->get('/articles/first')->assertOk()->assertSee('Native Twig')->assertHeaderMissing('X-Inertia');
            $this->command('craft:plugin:enable', 'inertia', '--no-interaction');
            $this->bootFresh();
            $this->get('/articles/first')->assertOk()->assertSee('Native Twig')->assertHeaderMissing('X-Inertia');
            $this->template('inertia:Articles/Show');
            $this->bootFresh();
            $this->get('/articles/first')->assertOk()->assertSee('data-page', false);
        } finally {
            $this->command('craft:plugin:enable', 'inertia', '--no-interaction');
            $this->template('inertia:Articles/Show');
            $this->command('config:clear', '--no-interaction');
            $this->command('route:clear', '--no-interaction');
        }
    }
}
