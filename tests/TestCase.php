<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\DbHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function db(): ConnectionInterface
    {
        return ConnectionProvider::get();
    }

    protected function checkFixture(ConnectionInterface $db, string $tablename, bool $reset = false): void
    {
        $schema = $db->getSchema();
        $tableSchema = $schema->getTableSchema($tablename, true);

        if ($tableSchema === null || $reset) {
            DbHelper::loadFixture($db);

            $schema->refresh();
        }
    }
}
