<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class PrivatePropertiesTraitTest extends \Yiisoft\ActiveRecord\Tests\PrivatePropertiesTraitTest
{
    protected static function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }
}