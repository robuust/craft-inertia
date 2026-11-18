<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Console;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use CraftCms\Cms\Plugin\Plugins;
use ErrorException;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteException;
use Inertia\Ssr\BundleDetector;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\HasHealthCheck;
use Inertia\Ssr\HttpGateway;
use Robuust\CraftInertia\Http\HandleInertiaRequests;
use Robuust\CraftInertia\Routing\EntryTemplates;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\ExecutableFinder;
use TypeError;

/** Inspects the consuming project's public frontend configuration and artifacts. */
final class DoctorCommand extends Command
{
    /** @var string Command name and options for the consuming project's frontend paths. */
    protected $signature = 'craft-inertia:doctor
        {--component= : Vue component name, for example Site/Quickstart}
        {--pages=resources/js/pages : Vue pages directory, relative to the project or absolute}
        {--entry=resources/js/app.js : Client entry used by the public Blade view}
        {--build-directory= : Vite build directory override used by the public Blade view}';

    /** @var string Description shown in the Artisan command list. */
    protected $description = 'Check the Craft Inertia public frontend setup';

    /**
     * @param Plugins $plugins Craft plugin registry.
     * @param Factory $views Laravel view factory with the project's namespaces.
     * @param Vite $vite Laravel Vite instance with the project's build settings.
     * @return int Failure when a checked requirement is missing or unavailable.
     */
    public function handle(Plugins $plugins, Factory $views, Vite $vite): int
    {
        $hot = $vite->isRunningHot();
        $checks = [
            $this->checkDependencies(),
            $this->checkPlugin($plugins),
            $this->checkRootView($views),
            $this->checkComponent(),
            $this->checkClient($vite, $hot),
            $this->checkSsr($hot),
        ];

        $this->newLine();
        $this->line('Manual checks: Open the mapped entry URL and verify the Vue resolver and client assets in the browser.');
        $this->line('Confirm native control panel and action routes stay outside public SSR, and verify session, validation and redirect behavior on configured forms.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Report the installed PHP and Composer versions against the package requirements.
     *
     * @return bool Whether all inspected PHP requirements are satisfied.
     */
    private function checkDependencies(): bool
    {
        $manifest = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $requirements = $manifest['require'];
        $compatible = $this->check(
            Semver::satisfies(PHP_VERSION, $requirements['php']),
            'PHP '.PHP_VERSION.' (requires '.$requirements['php'].')',
        );

        $parser = new VersionParser;
        foreach (['craftcms/cms', 'laravel/framework', 'inertiajs/inertia-laravel'] as $package) {
            $installed = InstalledVersions::isInstalled($package);
            $version = $installed ? (InstalledVersions::getPrettyVersion($package) ?? 'unknown version') : 'missing';
            $supported = $this->check(
                $installed && InstalledVersions::satisfies($parser, $package, $requirements[$package]),
                "$package: $version (requires {$requirements[$package]})",
            );
            $compatible = $supported && $compatible;
        }

        $this->reportFrontendToolchain();

        return $compatible;
    }

    /** Report frontend packages visible on the PHP host and the availability of Node.js. */
    private function reportFrontendToolchain(): void
    {
        $node = (new ExecutableFinder)->find('node');
        $this->line($node !== null
            ? 'Node.js executable found on this process’s PATH.'
            : 'Node.js executable unavailable on this process’s PATH.');

        $missing = [];
        foreach (['vue', 'vite', '@inertiajs/vue3', '@inertiajs/vite'] as $package) {
            $path = base_path("node_modules/$package/package.json");
            $manifest = is_file($path) && is_readable($path) ? @file_get_contents($path) : false;
            $version = $manifest === false ? null : (json_decode($manifest, true)['version'] ?? null);
            if (!is_string($version) || $version === '') {
                $missing[] = $package;
                continue;
            }

            $this->line("$package: $version");
        }

        if ($missing !== []) {
            $this->line('Frontend versions unavailable on this host: '.implode(', ', $missing).'.');
        }
        if ($missing !== [] || $node === null) {
            $this->line('Check the frontend build environment and lockfile. See the Quickstart and compatibility guides for versions.');
        }
    }

    /**
     * @param Plugins $plugins Craft plugin registry.
     * @return bool Whether the plugin is installed and enabled.
     */
    private function checkPlugin(Plugins $plugins): bool
    {
        if (!$plugins->isPluginInstalled('craft-inertia')) {
            return $this->check(false, 'Craft plugin craft-inertia is missing. Confirm discovery with craft:plugin:list.');
        }

        return $this->check(
            $plugins->isPluginEnabled('craft-inertia'),
            'Craft plugin craft-inertia is installed and enabled.',
            'Craft plugin craft-inertia is installed but disabled. Check its Craft settings and environment overrides.',
        );
    }

    /**
     * @param Factory $views Laravel view factory.
     * @return bool Whether the native public root view resolves.
     */
    private function checkRootView(Factory $views): bool
    {
        try {
            $root = app(HandleInertiaRequests::class)->rootView(request());
        } catch (TypeError) {
            return $this->check(false, 'Set craft-inertia.rootView to the name of a public Blade view.');
        }

        return $this->check(
            $root !== '' && $views->exists($root),
            "Public root view resolves: $root",
            "Public root view is missing: $root. Check craft-inertia.rootView and the project’s view namespace.",
        );
    }

    /**
     * Check the Vue file for the supplied component without invoking the JavaScript resolver.
     *
     * @return bool Whether the checked Vue file exists, or the check was skipped.
     */
    private function checkComponent(): bool
    {
        $component = $this->option('component');
        if ($component === null && !$this->input->hasParameterOption('--component', true)) {
            $this->line('Vue file check skipped. Pass --component=Site/Quickstart or the name used by your section template.');

            return true;
        }

        if (!is_string($component) || !preg_match(EntryTemplates::COMPONENT_PATTERN, $component)) {
            return $this->check(false, 'Pass a relative Vue component name without the inertia: prefix or the .vue extension.');
        }

        $pages = $this->option('pages');
        if (!is_string($pages) || $pages === '') {
            return $this->check(false, 'Pass a directory path with --pages.');
        }
        if (!Path::isAbsolute($pages)) {
            $pages = base_path($pages);
        }
        $path = Path::join($pages, "$component.vue");

        return $this->check(
            is_file($path) && is_readable($path),
            "Vue file found: $path",
            "Vue file is missing or unreadable: $path. Check the filename and case, or use --pages for a different directory.",
        );
    }

    /**
     * @param Vite $vite Laravel Vite instance with the project's build settings.
     * @param bool $hot Whether Laravel selects assets from the Vite hot file.
     * @return bool Whether the checked client artifact exists.
     */
    private function checkClient(Vite $vite, bool $hot): bool
    {
        $entry = $this->option('entry');
        if (!is_string($entry) || $entry === '') {
            return $this->check(false, 'Pass the client entry path with --entry.');
        }
        $buildDirectory = $this->option('build-directory');
        if ($buildDirectory === '' || ($buildDirectory === null && $this->input->hasParameterOption('--build-directory', true))) {
            return $this->check(false, 'Pass a directory with --build-directory.');
        }
        if ($hot) {
            $this->line('Vite hot file found. Check that the browser can reach the dev server.');

            $source = Path::isAbsolute($entry) ? $entry : base_path($entry);

            return $this->check(
                is_file($source) && is_readable($source),
                "Vite client source found: $entry",
                "Vite client source is missing or unreadable: $entry. Check the project’s Vite input and --entry.",
            );
        }

        try {
            $content = $vite->content($entry, $buildDirectory);
        } catch (ViteException|ErrorException|TypeError $exception) {
            return $this->check(false, $exception->getMessage().' Check the client build, --entry and --build-directory.');
        }

        return $this->check(
            $content !== false,
            "Vite manifest and built client file found for $entry.",
            "The Vite manifest's built client file is unreadable for $entry. Check the client build and asset permissions.",
        );
    }

    /**
     * @param bool $hot Whether the adapter selects Vite development SSR.
     * @return bool Whether the checked SSR requirements are met.
     */
    private function checkSsr(bool $hot): bool
    {
        if (!config('inertia.ssr.enabled', true)) {
            $this->line('Inertia SSR is disabled in the adapter configuration.');

            return true;
        }

        if ($hot) {
            $this->line('Inertia SSR is enabled in Vite hot mode. Verify the Vite SSR endpoint from an eligible page and check inertia.ssr.hot_url for separate PHP and Vite hosts.');

            return true;
        }

        $this->line('Inertia SSR is enabled in built-asset mode.');
        $gateway = app(Gateway::class);
        $bundleAvailable = !$gateway instanceof HttpGateway || $this->checkSsrBundle();
        if (!$gateway instanceof HasHealthCheck) {
            $this->warn('The configured SSR gateway has no health check. Verify connectivity with the project’s SSR tooling.');

            return $bundleAvailable;
        }

        try {
            $healthy = $gateway->isHealthy();
        } catch (Exception) {
            $healthy = false;
        }
        $connected = $this->check(
            $healthy,
            'The configured Inertia SSR gateway is healthy.',
            'The configured Inertia SSR gateway is unhealthy. Check the supervised process and inertia.ssr.url.',
        );

        return $connected && $bundleAvailable;
    }

    /**
     * Inspect the bundle selected by the official production SSR gateway.
     *
     * @return bool Whether the adapter's local bundle requirement is met.
     */
    private function checkSsrBundle(): bool
    {
        $bundle = app(BundleDetector::class)->detect();
        if ($bundle !== null && is_file($bundle) && is_readable($bundle)) {
            $configured = config('inertia.ssr.bundle');
            if ($configured && $configured !== $bundle) {
                $this->warn('Inertia selected a fallback bundle. Check the configured inertia.ssr.bundle path.');
            }

            return $this->check(true, "Inertia SSR bundle found: $bundle");
        }
        if (!config('inertia.ssr.ensure_bundle_exists', true)) {
            $this->line('No local SSR bundle found. The adapter allows remote SSR without a local bundle.');

            return true;
        }

        return $this->check(false, 'Inertia SSR bundle is missing or unreadable. Build it or check inertia.ssr.bundle.');
    }

    /**
     * @param bool $passed Whether the check passed.
     * @param string $message Result text.
     * @param string|null $failure Optional result text for a failed check.
     * @return bool Whether the check passed.
     */
    private function check(bool $passed, string $message, ?string $failure = null): bool
    {
        if ($passed) {
            $this->info($message);
        } else {
            $this->error($failure ?? $message);
        }

        return $passed;
    }
}
