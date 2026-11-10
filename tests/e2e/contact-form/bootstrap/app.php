<?php

use App\Http\Middleware\ContactPrivacy;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__ . '/../routes/web.php', api: __DIR__ . '/../routes/api.php')
    ->withMiddleware(fn(Middleware $middleware) => $middleware->append(ContactPrivacy::class))
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(fn(Response $response) => ContactPrivacy::protect(request(), $response));
    })
    ->create();

$app->useStoragePath(dirname(__DIR__) . '/storage');
$app->afterBootstrapping(LoadConfiguration::class, require __DIR__ . '/isolation.php');
$app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
    $config = $app->make('config');
    $cache = $app->storagePath('framework/cache/data');
    // Cache resets, native throttling and session locks share only this local store.
    $config->set('cache', [
        'default' => 'file',
        'limiter' => 'file',
        'stores' => ['file' => ['driver' => 'file', 'path' => $cache, 'lock_path' => $cache]],
        'prefix' => 'contact-form-fixture',
    ]);
    $config->set([
        'session.driver' => 'file',
        'session.files' => $app->storagePath('framework/sessions'),
        'session.connection' => null,
        'session.store' => null,
        'session.block_store' => 'file',
    ]);
    // A disposable fixture can only hand mail to its local capture transport.
    // Replace the whole map: inherited MAIL_URL, SMTP and failover cannot escape.
    $config->set('mail', [
        'default' => 'capture',
        'mailers' => ['capture' => ['transport' => 'capture']],
        'from' => ['address' => 'site@contact.test', 'name' => 'Contact fixture'],
    ]);
    $config->set('queue.default', 'sync');
});

return $app;
