<?php

use Hyperf\Database\Sqlsrv\Sqlsrv;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Coroutine;
use Hyperf\Utils\Parallel;

require "vendor/autoload.php";

const BASE_PATH = __DIR__ . "/../";
$container = new Container((new DefinitionSourceFactory())());
ApplicationContext::setContainer($container);

$now = microtime(true);

$parallel = new Parallel();

for ($i = 2; $i > 0; --$i) {
    $parallel->add(function () {
        $now = microtime(true);
        Sqlsrv::statement("WAITFOR DELAY '00:00:02'");
        var_dump(microtime(true) - $now);
        return Coroutine::id();
    });
}

$parallel->wait();
var_dump(microtime(true) - $now);
