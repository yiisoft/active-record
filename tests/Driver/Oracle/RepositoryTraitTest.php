<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class RepositoryTraitTest extends \Yiisoft\ActiveRecord\Tests\RepositoryTraitTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }
}
