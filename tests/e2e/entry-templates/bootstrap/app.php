<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php')
    ->withMiddleware()
    ->withExceptions()
    ->create();

$app->useStoragePath(dirname(__DIR__).'/storage');
$app->afterBootstrapping(LoadConfiguration::class, require __DIR__.'/isolation.php');
$app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
    $cache = $app->storagePath('framework/cache/data');
    $app->make('config')->set([
        'cache' => [
            'default' => 'file', 'limiter' => 'file',
            'stores' => ['file' => ['driver' => 'file', 'path' => $cache, 'lock_path' => $cache]],
            'prefix' => 'installation-fixture',
        ],
        'session.driver' => 'file', 'session.connection' => null, 'session.store' => null,
        'session.files' => $app->storagePath('framework/sessions'), 'session.block_store' => 'file',
        'mail' => [
            'default' => 'log', 'mailers' => ['log' => ['transport' => 'log']],
            'from' => ['address' => 'site@installation.test', 'name' => 'Installation fixture'],
        ],
        'queue.default' => 'sync',
    ]);
});

return $app;
