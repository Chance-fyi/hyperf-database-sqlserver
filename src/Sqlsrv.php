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

namespace Hyperf\Database\Sqlsrv;

use Closure;
use Generator;
use Hyperf\Context\Context;
use Hyperf\Database\ConnectionInterface;
use Hyperf\Database\Query\Builder;
use Hyperf\Database\Query\Expression;
use Hyperf\Database\Sqlsrv\Pool\PoolFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Coroutine;

/**
 * DB Helper.
 * @method static Builder table(string $table)
 * @method static Expression raw($value)
 * @method static mixed selectOne(string $query, array $bindings = [], bool $useReadPdo = true)
 * @method static array select(string $query, array $bindings = [], bool $useReadPdo = true)
 * @method static Generator cursor(string $query, array $bindings = [], bool $useReadPdo = true)
 * @method static bool insert(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static bool statement(string $query, array $bindings = [])
 * @method static int affectingStatement(string $query, array $bindings = [])
 * @method static bool unprepared(string $query)
 * @method static array prepareBindings(array $bindings)
 * @method static mixed transaction(Closure $callback, int $attempts = 1)
 * @method static void beginTransaction()
 * @method static void rollBack()
 * @method static void commit()
 * @method static int transactionLevel()
 * @method static array pretend(Closure $callback)
 * @method static ConnectionInterface connection(string $pool = "default")
 */
class Sqlsrv
{
    public function __call($name, $arguments)
    {
        if ($name === 'connection') {
            return $this->__connection(...$arguments);
        }
        return $this->__connection()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        $db = ApplicationContext::getContainer()->get(Sqlsrv::class);
        if ($name === 'connection') {
            return $db->__connection(...$arguments);
        }
        return $db->__connection()->{$name}(...$arguments);
    }

    public function __connection($name = "default"): SqlServerConnection
    {
        $connection = null;
        $id = $this->getContextKey($name);
        if (Context::has($id)) {
            $connection = Context::get($id);
        }

        if (!$connection instanceof ConnectionInterface) {
            $container = ApplicationContext::getContainer();
            $pool = $container->get(PoolFactory::class)->getPool($name);
            $connection = $pool->get();
            try {
                $connection = $connection->getConnection();
                Context::set($id, $connection);
            } finally {
                if (Coroutine::inCoroutine()) {
                    defer(function () use ($connection, $id) {
                        Context::set($id, null);
                        $connection->release();
                    });
                }
            }
        }

        return new SqlServerConnection($connection->getCon());
    }

    private function getContextKey($name): string
    {
        return sprintf('sqlsrv.connection.%s', $name);
    }
}
