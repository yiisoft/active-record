<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as BaseActiveQueryTest;

/**
 * @group mysql
 */
final class ActiveQueryTest extends BaseActiveQueryTest
{
    public ?string $driverName = 'mysql';
}
