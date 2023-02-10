<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Database\Sqlsvr;

use Hyperf\Database\Sqlsrv\Connectors\SqlServerConnector;
use Hyperf\Database\Sqlsrv\SqlServerConnection;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Parallel;

require 'vendor/autoload.php';

$config = [
    'host' => 'db',
    'port' => 1433,
    'database' => 'master',
    'username' => 'sa',
    'password' => 'XTguSD7of8yx%G%r',
    'trust_server_certificate' => true,
];

$now = microtime(true);

$parallel = new Parallel();

for ($i = 2; $i > 0; --$i) {
    $parallel->add(function () use ($config) {
        $now = microtime(true);
        $connector = new SqlServerConnector();
        $conn = $connector->connect($config);
        $sqlsrv = new SqlServerConnection($conn);
        $sqlsrv->statement("WAITFOR DELAY '00:00:02'");
        var_dump(microtime(true) - $now);
        return Coroutine::id();
    });
}

$parallel->wait();
var_dump(microtime(true) - $now);
