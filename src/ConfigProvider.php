<?php

declare(strict_types=1);

namespace Chance\Hyperf\Database\Sqlsrv;

use Chance\Hyperf\Database\Sqlsrv\Aspect\SqlServerAspect;

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
            'aspects' => [
                SqlServerAspect::class,
            ],
        ];
    }
}
