<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\BatchQueryResultTest as BaseBatchQueryResultTest;

/**
 * @group sqlite
 */
final class BatchQueryResultTest extends BaseBatchQueryResultTest
{
    public ?string $driverName = 'sqlite';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }
}
