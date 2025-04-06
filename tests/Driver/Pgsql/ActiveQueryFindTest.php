<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveQueryFindTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryFindTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new PgsqlHelper())->createConnection();
    }
}
