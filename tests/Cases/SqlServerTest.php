<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use Hyperf\DbConnection\Db;

/**
 * @internal
 *
 * @coversNothing
 */
class SqlServerTest extends AbstractTestCase
{
    public function testConnection()
    {
        Db::statement('select 1');
        $this->assertTrue(true);
    }
}
