<?php
/**
 * Created by PhpStorm
 * Date 2023/2/10 13:34
 */

namespace Hyperf\Database\Sqlsrv\Pool;

use Exception;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ConnectionInterface;
use Hyperf\Database\Sqlsrv\Connection;
use Hyperf\Pool\Pool;
use Hyperf\Utils\Arr;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

class SqlServerPool extends Pool
{
    protected array $config;

    public function __construct(ContainerInterface $container, protected string $name)
    {
        $config = $container->get(ConfigInterface::class);
        $key = sprintf('sqlsrv.%s', $this->name);
        if (!$config->has($key)) {
            throw new InvalidArgumentException(sprintf('config[%s] is not exist!', $key));
        }

        $this->config = $config->get($key);
        $options = Arr::get($this->config, 'pool', []);

        $this->frequency = make(Frequency::class);

        parent::__construct($container, $options);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws Exception
     */
    protected function createConnection(): ConnectionInterface
    {
        return new Connection($this->container, $this, $this->config);
    }
}