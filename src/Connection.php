<?php
/**
 * Created by PhpStorm
 * Date 2023/2/10 13:53
 */

namespace Hyperf\Database\Sqlsrv;

use Exception;
use Hyperf\Contract\ConnectionInterface;
use Hyperf\Contract\PoolInterface;
use Hyperf\Database\Sqlsrv\Connectors\SqlServerConnector;
use Hyperf\Pool\Connection as BaseConnection;
use Hyperf\Pool\Exception\ConnectionException;
use PDO;
use Psr\Container\ContainerInterface;

class Connection extends BaseConnection implements ConnectionInterface
{
    protected PDO $connection;
    protected array $config = [];

    /**
     * @throws Exception
     */
    public function __construct(ContainerInterface $container, PoolInterface $pool, array $config)
    {
        parent::__construct($container, $pool);
        $this->config = array_replace_recursive($this->config, $config);

        $this->reconnect();
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    public function getActiveConnection(): static
    {
        if ($this->check()) {
            return $this;
        }

        if (!$this->reconnect()) {
            throw new ConnectionException('Connection reconnect failed.');
        }

        return $this;
    }

    public function getCon()
    {
        return $this->connection;
    }

    /**
     * @throws Exception
     */
    public function reconnect(): bool
    {
        $connector = new SqlServerConnector();
        $this->connection = $connector->connect($this->config);
        $this->lastUseTime = microtime(true);

        return true;
    }

    public function close(): bool
    {
        unset($this->connection);

        return true;
    }
}