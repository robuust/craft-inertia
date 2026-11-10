<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MailIsolationTest extends TestCase
{
    #[TestWith([false])]
    #[TestWith([true])]
    public function testInheritedAndCachedMailConfigurationCannotSelectExternalTransport(bool $cached): void
    {
        $root = dirname(__DIR__, 2);
        $target = json_decode(file_get_contents($root . '/bootstrap/database-target.json'), true, flags: JSON_THROW_ON_ERROR);
        $cachePath = tempnam($root . '/bootstrap/cache', 'mail-isolation-');
        if ($cached) {
            $config = [
                'app' => ['url' => $target['appUrl']],
                'database' => ['default' => 'pgsql', 'connections' => ['pgsql' => $target['connection']]],
                'mail' => ['default' => 'smtp', 'mailers' => ['smtp' => ['transport' => 'smtp', 'url' => 'smtp://sentinel.invalid:1']]],
            ];
            file_put_contents($cachePath, '<?php return ' . var_export($config, true) . ';');
        } else {
            unlink($cachePath);
        }
        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->beforeBootstrapping(\Illuminate\Foundation\Bootstrap\RegisterProviders::class, function () use ($app) {
    $config = $app->make('config');
    echo json_encode([$config->get('mail.default'), $config->get('mail.mailers'), $config->get('queue.default')]);
    exit(79);
});
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
PHP;
        try {
            $process = new Process([PHP_BINARY, '-r', $script], $root, [
                'APP_CONFIG_CACHE' => $cachePath,
                'MAIL_MAILER' => 'smtp', 'MAIL_URL' => 'smtp://sentinel.invalid:1',
                'MAIL_HOST' => 'sentinel.invalid', 'QUEUE_CONNECTION' => 'database',
            ]);
            $process->run();
            $this->assertSame(79, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame(['capture', ['capture' => ['transport' => 'capture']], 'sync'], json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }
        }
    }
}
