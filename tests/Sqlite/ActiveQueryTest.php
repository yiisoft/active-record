<?php

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as BaseActiveQueryTest;

final class ActiveQueryTest extends BaseActiveQueryTest
{
    public ?string $driverName = 'sqlite';
}
