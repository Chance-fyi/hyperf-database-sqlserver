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

namespace HyperfTest\Cases;

use Hyperf\DbConnection\Db;

/**
 * @internal
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
