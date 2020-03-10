<?php

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as BaseActiveQueryTest;

/**
 * @group sqlite
 */
final class ActiveQueryTest extends BaseActiveQueryTest
{
    public ?string $driverName = 'sqlite';
}
