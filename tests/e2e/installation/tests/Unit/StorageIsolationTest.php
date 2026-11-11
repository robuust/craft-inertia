<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class StorageIsolationTest extends TestCase
{
    #[DataProvider('foreignStorage')]
    public function testLocalStorageIsEnforcedBeforeProviders(string $kernel, string $mode): void
    {
        $root = dirname(__DIR__, 2);
        $target = json_decode(file_get_contents($root . '/bootstrap/database-target.json'), true, flags: JSON_THROW_ON_ERROR);
        $cachePath = tempnam($root . '/bootstrap/cache', 'storage-isolation-');
        if ($mode === 'inherited') {
            unlink($cachePath);
        } else {
            $config = [
                'app' => ['url' => $target['appUrl']],
                'database' => ['default' => 'pgsql', 'connections' => ['pgsql' => $target['connection']]],
                'cache' => [
                    'default' => $mode === 'cached-file' ? 'file' : 'redis',
                    'limiter' => 'external',
                    'stores' => [
                        'file' => ['driver' => 'file', 'path' => '/unowned-storage-sentinel/cache', 'lock_path' => '/unowned-storage-sentinel/locks'],
                        'redis' => ['driver' => 'redis', 'connection' => 'external'],
                        'external' => ['driver' => 'redis', 'connection' => 'external'],
                    ],
                ],
                'session' => [
                    'driver' => $mode === 'cached-file' ? 'file' : 'redis',
                    'files' => '/unowned-storage-sentinel/sessions',
                    'connection' => 'external', 'store' => 'external', 'block_store' => 'external',
                ],
            ];
            file_put_contents($cachePath, '<?php return ' . var_export($config, true) . ';');
        }
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->beforeBootstrapping(\Illuminate\Foundation\Bootstrap\RegisterProviders::class, function () use ($app) {
    $config = $app->make('config');
    $storage = __DIR__.'/storage';
    $cache = $storage.'/framework/cache/data';
    if ($app->storagePath() !== $storage
        || $config->get('cache.default') !== 'file'
        || $config->get('cache.limiter') !== 'file'
        || $config->get('cache.stores') !== ['file' => ['driver' => 'file', 'path' => $cache, 'lock_path' => $cache]]
        || $config->get('session.driver') !== 'file'
        || $config->get('session.files') !== $storage.'/framework/sessions'
        || $config->get('session.connection') !== null
        || $config->get('session.store') !== null
        || $config->get('session.block_store') !== 'file') {
        echo 'UNSAFE_LOCAL_STORAGE';
        exit(78);
    }
    // Only try named stores after the complete local map is established.
    // A red probe exits above, before any store or application provider resolves.
    $manager = new \Illuminate\Cache\CacheManager($app);
    foreach (['redis', 'external', 'failover'] as $name) {
        try {
            $manager->store($name);
            exit(80);
        } catch (\InvalidArgumentException $exception) {
            if ($exception->getMessage() !== "Cache store [$name] is not defined.") {
                throw $exception;
            }
        }
    }
    echo 'LOCAL_STORAGE_BOUNDARY';
    exit(79);
});
$app->make($argv[1])->bootstrap();
PHP;
        try {
            $contract = $kernel === 'Console' ? \Illuminate\Contracts\Console\Kernel::class : \Illuminate\Contracts\Http\Kernel::class;
            $process = new Process([PHP_BINARY, '-r', $script, $contract], $root, [
                'APP_CONFIG_CACHE' => $cachePath, 'LARAVEL_STORAGE_PATH' => '/unowned-storage-sentinel',
                'CACHE_STORE' => 'redis', 'REDIS_HOST' => 'sentinel.invalid',
                'SESSION_DRIVER' => 'redis', 'SESSION_STORE' => 'external', 'SESSION_CONNECTION' => 'external',
            ]);
            $process->run();
            $this->assertSame(79, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame('LOCAL_STORAGE_BOUNDARY', $process->getOutput());
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }
        }
    }

    public static function foreignStorage(): array
    {
        return [
            'console inherited Redis' => ['Console', 'inherited'],
            'http inherited Redis' => ['Http', 'inherited'],
            'console cached external stores' => ['Console', 'cached-redis'],
            'http cached external stores' => ['Http', 'cached-redis'],
            'console cached external file paths' => ['Console', 'cached-file'],
            'http cached external file paths' => ['Http', 'cached-file'],
        ];
    }
}
