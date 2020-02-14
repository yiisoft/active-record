<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as BaseActiveQueryTest;

final class ActiveQueryTest extends BaseActiveQueryTest
{
    public ?string $driverName = 'pgsql';
}
