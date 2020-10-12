<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Tests\Stubs\DummyActiveRecord;
use Yiisoft\Db\Exception\NotSupportedException;

/**
 * @group main
 */
final class DummyActiveRecordTest extends TestCase
{
    public function testUpdateAllException(): void
    {
        $dummyClass = new DummyActiveRecord($this->sqliteConnection);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\ActiveRecord\BaseActiveRecord::updateAll is not supported.');

        $dummyClass->updateAll(['id' => 1]);
    }

    public function testUpdateAllCountersException(): void
    {
        $dummyClass = new DummyActiveRecord($this->sqliteConnection);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\ActiveRecord\BaseActiveRecord::updateAllCounters is not supported');

        $dummyClass->updateAllCounters(['id' => 1]);
    }

    public function testDeleteAllException(): void
    {
        $dummyClass = new DummyActiveRecord($this->sqliteConnection);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('Yiisoft\ActiveRecord\BaseActiveRecord::deleteAll is not supported.');

        $dummyClass->deleteAll(['id' => 1]);
    }
}
