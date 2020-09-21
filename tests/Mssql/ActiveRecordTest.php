<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mssql;

use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\ActiveRecord\Tests\ActiveRecordTest as BaseActiveRecordTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\TestTrigger;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\TestTriggerAlert;

/**
 * @group mssql
 */
final class ActiveRecordTest extends BaseActiveRecordTest
{
    protected ?string $driverName = 'mssql';

    public function setUp(): void
    {
        parent::setUp();

        BaseActiveRecord::connectionId($this->driverName);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->mssqlConnection->close();

        unset($this->mssqlConnection);
    }

    public function testSaveWithTrigger(): void
    {
        $db = $this->mssqlConnection;

        /* drop trigger if exist */
        $sql = 'IF (OBJECT_ID(N\'[dbo].[test_alert]\') IS NOT NULL)
BEGIN
      DROP TRIGGER [dbo].[test_alert];
END';
        $db->createCommand($sql)->execute();

        /* create trigger */
        $sql = 'CREATE TRIGGER [dbo].[test_alert] ON [dbo].[test_trigger]
AFTER INSERT
AS
BEGIN
    INSERT INTO [dbo].[test_trigger_alert] ( [stringcol] )
    SELECT [stringcol]
    FROM [inserted]
END';
        $db->createCommand($sql)->execute();

        $record = new TestTrigger();

        $record->stringcol = 'test';

        $this->assertTrue($record->save());
        $this->assertEquals(1, $record->id);

        $testRecord = TestTriggerAlert::findOne(1);

        $this->assertEquals('test', $testRecord->stringcol);
    }
}
