<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mssql;

use Yiisoft\ActiveRecord\Tests\Support\MssqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ArrayableTraitTest extends \Yiisoft\ActiveRecord\Tests\ArrayableTraitTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new MssqlHelper())->createConnection();
    }
}
