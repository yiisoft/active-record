<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveRecordTest as BaseActiveRecordTest;

/**
 * @group sqlite
 */
class ActiveRecordTest extends BaseActiveRecordTest
{
    protected ?string $driverName = 'sqlite';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }
}
