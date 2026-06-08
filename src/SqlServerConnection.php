<?php

declare(strict_types=1);

namespace Chance\Hyperf\Database\Sqlsrv;

use Chance\Hyperf\Database\Sqlsrv\Query\Grammars\SqlServerGrammar as QueryGrammar;
use Chance\Hyperf\Database\Sqlsrv\Query\Processors\SqlServerProcessor;
use Chance\Hyperf\Database\Sqlsrv\Schema\Grammars\SqlServerGrammar as SchemaGrammar;
use Chance\Hyperf\Database\Sqlsrv\Schema\SqlServerBuilder;
use Chance\Hyperf\Database\Sqlsrv\Task\SqlServerTask;
use Closure;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Connection;
use Hyperf\Database\Query\Processors\Processor;
use Hyperf\Database\Schema\Builder;
use Hyperf\Support\Filesystem\Filesystem;
use Hyperf\Task\Task as TaskMessage;
use Hyperf\Task\TaskExecutor;
use RuntimeException;
use Throwable;

class SqlServerConnection extends Connection
{
    protected bool $isTaskEnvironment = false;

    /**
     * Execute a Closure within a transaction.
     *
     * @throws Throwable
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed
    {
        for ($a = 1; $a <= $attempts; ++$a) {
            if ('sqlsrv' === $this->getDriverName()) {
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
     * Get the schema state for the connection.
     *
     * @throws RuntimeException
     */
    public function getSchemaState(?Filesystem $files = null, ?callable $processFactory = null)
    {
        throw new RuntimeException('Schema dumping is not supported when using SQL Server.');
    }

    public function setIsTaskEnvironment(bool $isTaskEnvironment = true): static
    {
        $this->isTaskEnvironment = $isTaskEnvironment;

        return $this;
    }

    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array
    {
        if (!$this->isTaskEnvironment) {
            return $this->runInTaskWorker(__FUNCTION__, [(string) $this->getConfig('name'), $query, $bindings, $useReadPdo]);
        }

        return parent::select($query, $bindings, $useReadPdo);
    }

    public function statement(string $query, array $bindings = []): bool
    {
        if (!$this->isTaskEnvironment) {
            return $this->runInTaskWorker(__FUNCTION__, [(string) $this->getConfig('name'), $query, $bindings]);
        }

        return parent::statement($query, $bindings);
    }

    public function affectingStatement(string $query, array $bindings = []): int
    {
        if (!$this->isTaskEnvironment) {
            return $this->runInTaskWorker(__FUNCTION__, [(string) $this->getConfig('name'), $query, $bindings]);
        }

        return parent::affectingStatement($query, $bindings);
    }

    public function unprepared(string $query): bool
    {
        if (!$this->isTaskEnvironment) {
            return $this->runInTaskWorker(__FUNCTION__, [(string) $this->getConfig('name'), $query]);
        }

        return parent::unprepared($query);
    }

    /**
     * Dispatch a SqlServerTask method to a task worker.
     *
     * The Task execute timeout is configurable per connection through the
     * `task_timeout` option (in seconds) and defaults to 10 to keep the
     * previous behaviour. Raise it for heavy queries that legitimately take
     * longer than 10s, which would otherwise fail with "Task [N] execute timeout".
     */
    protected function runInTaskWorker(string $method, array $arguments): mixed
    {
        $executor = ApplicationContext::getContainer()->get(TaskExecutor::class);
        $timeout = (float) ($this->getConfig('task_timeout') ?? 10);

        return $executor->execute(new TaskMessage([SqlServerTask::class, $method], $arguments), $timeout);
    }

    /**
     * Get the default query grammar instance.
     */
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        return $this->withTablePrefix(new QueryGrammar());
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return $this->withTablePrefix(new SchemaGrammar());
    }

    /**
     * Get the default post processor instance.
     *
     * @return SqlServerProcessor
     */
    protected function getDefaultPostProcessor(): Processor
    {
        return new SqlServerProcessor();
    }
}
