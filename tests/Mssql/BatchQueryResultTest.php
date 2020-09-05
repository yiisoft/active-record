<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mssql;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\BatchQueryResultTest as BaseBatchQueryResultTest;

/**
 * @group mssql
 */
final class BatchQueryResultTest extends BaseBatchQueryResultTest
{
    public ?string $driverName = 'mssql';

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
