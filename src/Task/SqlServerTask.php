<?php

namespace Chance\Hyperf\Database\Sqlsrv\Task;

use Chance\Hyperf\Database\Sqlsrv\SqlServerConnection;
use Hyperf\DbConnection\Db;

class SqlServerTask
{
    public function select(string $connection, string $query, array $bindings = [], bool $useReadPdo = true): array
    {
        /** @var SqlServerConnection $conn */
        $conn = Db::connection($connection);

        return $conn->setIsTaskEnvironment()->select($query, $bindings, $useReadPdo);
    }

    public function statement(string $connection, string $query, array $bindings = []): bool
    {
        /** @var SqlServerConnection $conn */
        $conn = Db::connection($connection);

        return $conn->setIsTaskEnvironment()->statement($query, $bindings);
    }

    public function affectingStatement(string $connection, string $query, array $bindings = []): int
    {
        /** @var SqlServerConnection $conn */
        $conn = Db::connection($connection);

        return $conn->setIsTaskEnvironment()->affectingStatement($query, $bindings);
    }

    public function unprepared(string $connection, string $query): bool
    {
        /** @var SqlServerConnection $conn */
        $conn = Db::connection($connection);

        return $conn->setIsTaskEnvironment()->unprepared($query);
    }
}
