<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveDataProviderTest as AbstractActiveDataProviderTest;

/**
 * @group pgsql
 */
final class ActiveDataProviderTest extends AbstractActiveDataProviderTest
{
    protected ?string $driverName = 'pgsql';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }
}
