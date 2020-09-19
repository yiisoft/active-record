<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mssql;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveDataProviderTest as AbstractActiveDataProviderTest;

/**
 * @group mssql
 */
final class ActiveDataProviderTest extends AbstractActiveDataProviderTest
{
    protected ?string $driverName = 'mssql';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }
}
