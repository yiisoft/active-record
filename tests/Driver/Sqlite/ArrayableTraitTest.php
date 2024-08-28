<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\Tests\Support\SqliteHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ArrayableTraitTest extends \Yiisoft\ActiveRecord\Tests\ArrayableTraitTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new SqliteHelper())->createConnection();
    }
}
