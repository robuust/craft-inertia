<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class IsolationTest extends TestCase
{
    #[DataProvider('foreignEnvironment')]
    public function testForeignEnvironmentIsRejectedBeforeProviders(string $kernel, array $environment): void
    {
        $this->assertBootstrap($kernel, $environment, null, 78);
    }

    public static function foreignEnvironment(): array
    {
        return [
            'console inherited database' => ['Console', ['DB_DATABASE' => 'review_external_database']],
            'http inherited database' => ['Http', ['DB_DATABASE' => 'review_external_database']],
            'console inherited URL' => ['Console', ['DB_URL' => 'postgres://sentinel:sentinel@127.0.0.1:1/review_external_database']],
            'http URL query override' => ['Http', ['DB_URL' => 'postgres://sentinel:sentinel@127.0.0.1:1/clean_installation?database=review_external_database']],
            'different driver' => ['Console', ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => '/never-created-review-sentinel.sqlite']],
            'direct endpoint' => ['Console', ['DB_DIRECT_HOST' => 'review-external.invalid']],
            'application URL' => ['Console', ['APP_URL' => 'http://review-external.invalid']],
        ];
    }

    #[DataProvider('foreignCachedConfiguration')]
    public function testForeignCachedConfigurationIsRejectedBeforeProviders(string $kernel, bool $url): void
    {
        $config = $this->cachedConfiguration();
        if ($url) {
            $config['database']['connections']['pgsql']['url'] = 'postgres://sentinel:sentinel@127.0.0.1:1/clean_installation?database=review_external_database';
        } else {
            $config['database']['connections']['pgsql']['database'] = 'review_external_database';
        }

        $this->assertBootstrap($kernel, [], $config, 78);
    }

    public static function foreignCachedConfiguration(): array
    {
        return [
            'console cached database' => ['Console', false],
            'http cached database' => ['Http', false],
            'console cached URL' => ['Console', true],
            'http cached URL' => ['Http', true],
        ];
    }

    public function testValidInstallEnvironmentAndProjectOptInReachTheProviderBoundary(): void
    {
        $this->assertBootstrap('Console', ['APP_ENV' => 'install', 'FRONTEND_ENABLED' => 'true'], null, 79);
    }

    public function testSafeCachedTargetCannotBeReplacedByInheritedDatabaseOrUrl(): void
    {
        $this->assertBootstrap('Http', [
            'DB_DATABASE' => 'review_external_database',
            'DB_URL' => 'postgres://sentinel:sentinel@127.0.0.1:1/review_external_database',
        ], $this->cachedConfiguration(), 79);
    }

    private function cachedConfiguration(): array
    {
        $target = json_decode(file_get_contents(dirname(__DIR__, 2).'/bootstrap/database-target.json'), true, flags: JSON_THROW_ON_ERROR);

        return [
            'app' => ['url' => $target['appUrl']],
            'database' => ['default' => 'pgsql', 'connections' => ['pgsql' => $target['connection']]],
        ];
    }

    private function assertBootstrap(string $kernel, array $environment, ?array $cache, int $expected): void
    {
        $root = dirname(__DIR__, 2);
        $target = $this->cachedConfiguration();
        $connection = $target['database']['connections']['pgsql'];
        $cachePath = tempnam($root.'/bootstrap/cache', 'isolation-');
        if ($cache === null) {
            unlink($cachePath);
        } else {
            file_put_contents($cachePath, '<?php return '.var_export($cache, true).';');
        }
        $environment = array_merge([
            'APP_CONFIG_CACHE' => $cachePath,
            'APP_URL' => $target['app']['url'],
            'DB_CONNECTION' => 'pgsql', 'DB_URL' => false,
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'], 'DB_DIRECT_HOST' => false,
            'DB_DIRECT_PORT' => false, 'DB_DIRECT_USERNAME' => false, 'DB_DIRECT_PASSWORD' => false,
            'DB_DIRECT_SSLMODE' => false, 'DB_POOLED' => false,
        ], $environment);
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->beforeBootstrapping(\Illuminate\Foundation\Bootstrap\RegisterProviders::class, function () {
    echo 'PROVIDERS_BOUNDARY';
    exit(79);
});
try {
    $app->make($argv[1])->bootstrap();
} catch (\RuntimeException $exception) {
    if (str_starts_with($exception->getMessage(), 'Installation fixture isolation:')) {
        echo 'ISOLATION_BLOCKED';
        exit(78);
    }
    throw $exception;
}
exit(80);
PHP;

        try {
            $contract = $kernel === 'Console' ? \Illuminate\Contracts\Console\Kernel::class : \Illuminate\Contracts\Http\Kernel::class;
            $process = new Process([PHP_BINARY, '-r', $script, $contract], $root, $environment);
            $process->run();

            $this->assertSame($expected, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame($expected === 78 ? 'ISOLATION_BLOCKED' : 'PROVIDERS_BOUNDARY', $process->getOutput());
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }
        }
    }
}
