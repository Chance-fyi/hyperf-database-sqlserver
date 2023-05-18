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
use Hyperf\Database\Connection;
use Hyperf\Database\Query\Processors\Processor;
use Hyperf\Database\Schema\Builder;
use Hyperf\Database\Sqlsrv\Query\Grammars\SqlServerGrammar as QueryGrammar;
use Hyperf\Database\Sqlsrv\Query\Processors\SqlServerProcessor;
use Hyperf\Database\Sqlsrv\Schema\Grammars\SqlServerGrammar as SchemaGrammar;
use Hyperf\Database\Sqlsrv\Schema\SqlServerBuilder;
use Hyperf\Support\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

class SqlServerConnection extends Connection
{
    /**
     * Execute a Closure within a transaction.
     *
     * @throws Throwable
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed
    {
        for ($a = 1; $a <= $attempts; ++$a) {
            if ($this->getDriverName() === 'sqlsrv') {
                return parent::transaction($callback, $attempts);
            }

            $this->getPdo()->exec('BEGIN TRAN');

            // We'll simply execute the given callback within a try / catch block
            // and if we catch any exception we can rollback the transaction
            // so that none of the changes are persisted to the database.
            try {
                $result = $callback($this);

                $this->getPdo()->exec('COMMIT TRAN');
            }

                // If we catch an exception, we will rollback so nothing gets messed
                // up in the database. Then we'll re-throw the exception so it can
                // be handled how the developer sees fit for their applications.
            catch (Throwable $e) {
                $this->getPdo()->exec('ROLLBACK TRAN');

                throw $e;
            }

            return $result;
        }
        return null;
    }

    /**
     * Get the default query grammar instance.
     */
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        return $this->withTablePrefix(new QueryGrammar());
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return SqlServerBuilder
     */
    public function getSchemaBuilder(): Builder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new SqlServerBuilder($this);
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Get the schema state for the connection.
     *
     * @param Filesystem|null $files
     * @param callable|null $processFactory
     *
     * @throws RuntimeException
     */
    public function getSchemaState(Filesystem $files = null, callable $processFactory = null)
    {
        throw new RuntimeException('Schema dumping is not supported when using SQL Server.');
    }

    /**
     * Get the default post processor instance.
     *
     * @return SqlServerProcessor
     */
    protected function getDefaultPostProcessor(): Processor
    {
        return new SqlServerProcessor;
    }
}
