<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class BatchQueryResultTest extends \Yiisoft\ActiveRecord\Tests\BatchQueryResultTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new PgsqlHelper())->createConnection();
    }
}
