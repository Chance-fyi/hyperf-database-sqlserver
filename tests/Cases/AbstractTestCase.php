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

namespace HyperfTest\Cases;

use Hyperf\Config\Config;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use PHPUnit\Framework\TestCase;

/**
 * Class AbstractTestCase.
 */
abstract class AbstractTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClassLoader::init(configDir: BASE_PATH . 'tests/config');

        $container = new Container((new DefinitionSourceFactory())());
        ApplicationContext::setContainer($container);

        $container->set(ConfigInterface::class, new Config([
            'databases' => [
                'default' => [
                    'driver' => 'sqlsrv',
                    'host' => 'db',
                    'database' => 'master',
                    'port' => 1433,
                    'username' => 'sa',
                    'password' => 'XTguSD7of8yx%G%r',
                    'trust_server_certificate' => true,
                    'pool' => [
                        'min_connections' => 1,
                        'max_connections' => 10,
                        'connect_timeout' => 10.0,
                        'wait_timeout' => 3.0,
                        'heartbeat' => -1,
                        'max_idle_time' => 60,
                    ],
                ],
            ]
        ]));
    }
}
