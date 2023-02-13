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

namespace Hyperf\Database\Sqlsrv\Pool;

use Hyperf\Di\Container;
use Psr\Container\ContainerInterface;

class PoolFactory
{
    /**
     * @var SqlServerPool[]
     */
    protected array $pools = [];

    public function __construct(protected ContainerInterface $container)
    {
    }

    public function getPool(string $name): SqlServerPool
    {
        if (isset($this->pools[$name])) {
            return $this->pools[$name];
        }

        if ($this->container instanceof Container) {
            $pool = $this->container->make(SqlServerPool::class, ['name' => $name]);
        } else {
            $pool = new SqlServerPool($this->container, $name);
        }
        return $this->pools[$name] = $pool;
    }
}
