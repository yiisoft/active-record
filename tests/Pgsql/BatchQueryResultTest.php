<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\BatchQueryResultTest as BaseBatchQueryResultTest;

/**
 * @group pgsql
 */
final class BatchQueryResultTest extends BaseBatchQueryResultTest
{
    public ?string $driverName = 'pgsql';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pgsqlConnection->close();

        unset($this->pgsqlConnection);
    }
}
