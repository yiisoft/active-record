<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Unit;

final class ActiveRecordTest extends \Yiisoft\Db\Tests\ActiveRecordTest
{
    public ?string $driverName = 'mysql';
}
