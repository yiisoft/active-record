<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveDataProviderTest as AbstractActiveDataProviderTest;

/**
 * @group sqlite
 */
final class ActiveDataProviderTest extends AbstractActiveDataProviderTest
{
    protected ?string $driverName = 'sqlite';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }
}
