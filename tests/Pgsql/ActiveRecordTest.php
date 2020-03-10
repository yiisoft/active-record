<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use Yiisoft\ActiveRecord\Tests\ActiveRecordTest as BaseActiveRecordTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

/**
 * @group pgsql
 */
final class ActiveRecordTest extends BaseActiveRecordTest
{
    public ?string $driverName = 'pgsql';

    public function setUp(): void
    {
        parent::setUp();

        ActiveRecord::setDriverName($this->driverName);
    }
}
