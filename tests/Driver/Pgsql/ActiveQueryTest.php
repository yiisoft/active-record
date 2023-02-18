<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as AbstractActiveQueryTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group pgsql
 */
final class ActiveQueryTest extends AbstractActiveQueryTest
{
    protected string $driverName = 'pgsql';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->pgsqlConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pgsqlConnection->close();

        unset($this->pgsqlConnection);
    }
}
