<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mssql;

use Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest as AbstractActiveRecordFactoryTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\TestTrigger;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\TestTriggerAlert;

/**
 * @group mssql
 */
final class ActiveRecordFactoryTest extends AbstractActiveRecordFactoryTest
{
    protected string $driverName = 'mssql';

    public function setUp(): void
    {
        parent::setUp();

        $this->arFactory->withConnection($this->mssqlConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->mssqlConnection->close();

        unset($this->arFactory, $this->mssqlConnection);
    }

    public function testSaveWithTrigger(): void
    {
        $db = $this->mssqlConnection;

        /** drop trigger if exist */
        $sql = 'IF (OBJECT_ID(N\'[dbo].[test_alert]\') IS NOT NULL)
BEGIN
      DROP TRIGGER [dbo].[test_alert];
END';
        $db->createCommand($sql)->execute();

        /** create trigger */
        $sql = 'CREATE TRIGGER [dbo].[test_alert] ON [dbo].[test_trigger]
AFTER INSERT
AS
BEGIN
    INSERT INTO [dbo].[test_trigger_alert] ( [stringcol] )
    SELECT [stringcol]
    FROM [inserted]
END';
        $db->createCommand($sql)->execute();

        $record = $this->arFactory->createAR(TestTrigger::class);

        $record->stringcol = 'test';

        $this->assertTrue($record->save());
        $this->assertEquals(1, $record->id);

        $testRecordQuery = $this->arFactory->createQueryTo(TestTriggerAlert::class);

        $this->assertEquals('test', $testRecordQuery->findOne(1)->stringcol);
    }
}
