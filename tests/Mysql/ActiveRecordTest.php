<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveRecordTest as BaseActiveRecordTest;

/**
 * @group mysql
 */
final class ActiveRecordTest extends BaseActiveRecordTest
{
    protected ?string $driverName = 'mysql';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->mysqlConnection->close();

        unset($this->mysqlConnection);
    }
}
