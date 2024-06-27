<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\DbHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    abstract protected function createConnection(): ConnectionInterface;

    protected function checkFixture(ConnectionInterface $db, string $tablename, bool $reset = false): void
    {
        $schema = $db->getSchema();
        $tableSchema = $schema->getTableSchema($tablename, true);

        if ($tableSchema === null || $reset) {
            DbHelper::loadFixture($db);

            $schema->refresh();
        }
    }

    protected function db(): ConnectionInterface
    {
        return ConnectionProvider::get();
    }

    protected function setUp(): void
    {
        parent::setUp();

        ConnectionProvider::set($this->createConnection());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        ConnectionProvider::unset();
    }
}
