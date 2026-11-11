<?php

use Illuminate\Foundation\Application;

/**
 * Reject a foreign resolved target before any application provider can connect.
 *
 * @param Application $app The isolated installation fixture.
 */
return static function (Application $app): void {
    $path = __DIR__.'/database-target.json';
    if (!is_file($path)) {
        throw new RuntimeException('Installation fixture isolation: missing generated database target.');
    }
    $target = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $config = $app->make('config');
    if ($config->get('database.default') !== 'pgsql' || $config->get('app.url') !== $target['appUrl']) {
        throw new RuntimeException('Installation fixture isolation: unexpected database connection or application URL.');
    }

    $connection = $config->get('database.connections.pgsql', []);
    foreach (['driver', 'host', 'port', 'database', 'username', 'password'] as $key) {
        if (!isset($target['connection'][$key]) || !is_scalar($connection[$key] ?? null)
            || (string) $connection[$key] !== (string) $target['connection'][$key]) {
            throw new RuntimeException('Installation fixture isolation: database target mismatch.');
        }
    }
    foreach (['url', 'read', 'write', 'direct', 'pooled', 'connect_via_database', 'connect_via_port'] as $key) {
        if (!empty($connection[$key])) {
            throw new RuntimeException('Installation fixture isolation: alternate database endpoints are unsupported.');
        }
    }

    // Only this fixture's checked connection is available to subsequent providers.
    $config->set('database.connections', ['pgsql' => array_intersect_key($connection, $target['connection'])]);
};
