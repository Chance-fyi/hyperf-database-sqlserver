<?php
/**
 * Created by PhpStorm
 * Date 2023/5/17 16:45
 */

namespace Hyperf\Database\Sqlsrv\Aspect;

use Hyperf\Database\Sqlsrv\Connectors\SqlServerConnector;
use Hyperf\Database\Sqlsrv\SqlServerConnection;
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
            if ($methodName === 'createConnection' && $arguments['driver'] === 'sqlsrv') {
                return new SqlServerConnection($arguments['connection'], $arguments['database'], $arguments['prefix'], $arguments['config']);
            }
            if ($methodName === 'createConnector' && $arguments['config']['driver'] === 'sqlsrv') {
                return new SqlServerConnector();
            }
            throw $e;
        }
    }
}