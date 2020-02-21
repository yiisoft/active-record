<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\Tests\ActiveRecordTest as BaseActiveRecordTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

final class ActiveRecordTest extends BaseActiveRecordTest
{
    public ?string $driverName = 'mysql';

    public function setUp(): void
    {
        parent::setUp();

        ActiveRecord::setDriverName($this->driverName);
    }
}
