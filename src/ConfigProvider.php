<?php

declare(strict_types=1);

namespace Chance\Hyperf\Database\Sqlsrv;

use Chance\Hyperf\Database\Sqlsrv\Connectors\SqlServerConnector;
use Chance\Hyperf\Database\Sqlsrv\Listener\RegisterConnectionListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'dependencies' => [
                'db.connector.sqlsrv' => SqlServerConnector::class,
            ],
            'listeners' => [
                RegisterConnectionListener::class,
            ],
        ];
    }
}
