<?php
/**
 * Created by PhpStorm
 * Date 2023/2/10 16:12
 */

return [
    'default' => [
        'host' => env('SQLSRV_HOST', 'localhost'),
        'database' => env('SQLSRV_DATABASE', 'hyperf'),
        'port' => env('SQLSRV_PORT', 1433),
        'username' => env('SQLSRV_USERNAME', 'root'),
        'password' => env('SQLSRV_PASSWORD', ''),
        'trust_server_certificate' => true,
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('SQLSRV_MAX_IDLE_TIME', 60),
        ],
    ],
];