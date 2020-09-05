<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as BaseActiveQueryTest;

/**
 * @group mssql
 */
final class ActiveQueryTest extends BaseActiveQueryTest
{
    protected ?string $driverName = 'mssql';

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
