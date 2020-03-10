<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use Yiisoft\ActiveRecord\Tests\BatchQueryResultTest as BaseBatchQueryResultTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

/**
 * @group pgsql
 */
final class BatchQueryResultTest extends BaseBatchQueryResultTest
{
    public ?string $driverName = 'pgsql';

    public function setUp(): void
    {
        parent::setUp();

        ActiveRecord::setDriverName($this->driverName);
    }
}
