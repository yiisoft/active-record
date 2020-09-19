<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveDataProviderTest as AbstractActiveDataProviderTest;

/**
 * @group mysql
 */
final class ActiveDataProviderTest extends AbstractActiveDataProviderTest
{
    protected ?string $driverName = 'mysql';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }
}
