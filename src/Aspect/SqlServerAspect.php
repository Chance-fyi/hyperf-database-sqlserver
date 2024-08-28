<?php

namespace Chance\Hyperf\Database\Sqlsrv\Aspect;

use Chance\Hyperf\Database\Sqlsrv\Connectors\SqlServerConnector;
use Chance\Hyperf\Database\Sqlsrv\SqlServerConnection;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use InvalidArgumentException;

#[Aspect]
class SqlServerAspect extends AbstractAspect
{
    public array $classes = [
        'Hyperf\Database\Connectors\ConnectionFactory::createConnection',
        'Hyperf\Database\Connectors\ConnectionFactory::createConnector',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        try {
            return $proceedingJoinPoint->process();
        } catch (InvalidArgumentException $e) {
            $methodName = $proceedingJoinPoint->methodName;
            $arguments = $proceedingJoinPoint->arguments['keys'];
            if ('createConnection' === $methodName && 'sqlsrv' === $arguments['driver']) {
                return new SqlServerConnection($arguments['connection'], $arguments['database'], $arguments['prefix'], $arguments['config']);
            }
            if ('createConnector' === $methodName && 'sqlsrv' === $arguments['config']['driver']) {
                return new SqlServerConnector();
            }

            throw $e;
        }
    }
}
