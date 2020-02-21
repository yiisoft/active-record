<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveRecordTest as BaseActiveRecordTest;

class ActiveRecordTest extends BaseActiveRecordTest
{
    public ?string $driverName = 'sqlite';

    public function setUp(): void
    {
        parent::setUp();

        ActiveRecord::setDriverName($this->driverName);
    }
}
