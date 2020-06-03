<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as BaseActiveQueryTest;

/**
 * @group sqlite
 */
final class ActiveQueryTest extends BaseActiveQueryTest
{
    protected ?string $driverName = 'sqlite';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }
}
