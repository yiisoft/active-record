<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mssql;

use Yiisoft\ActiveRecord\Tests\Support\MssqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class RepositoryTraitTest extends \Yiisoft\ActiveRecord\Tests\RepositoryTraitTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new MssqlHelper())->createConnection();
    }
}
