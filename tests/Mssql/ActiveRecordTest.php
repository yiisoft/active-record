<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mssql;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\ActiveRecordTest as AbstractActiveRecordTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\TestTrigger;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\TestTriggerAlert;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group mssql
 */
final class ActiveRecordTest extends AbstractActiveRecordTest
{
    protected string $driverName = 'mssql';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->mssqlConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->mssqlConnection->close();

        unset($this->mssqlConnection);
    }

    public function testSaveWithTrigger(): void
    {
        $this->checkFixture($this->db, 'test_trigger');

        $db = $this->mssqlConnection;

        /** drop trigger if exist */
        $sql = 'IF (OBJECT_ID(N\'[dbo].[test_alert]\') IS NOT NULL)
BEGIN
      DROP TRIGGER [dbo].[test_alert];
END';
        $db
            ->createCommand($sql)
            ->execute();

        /** create trigger */
        $sql = 'CREATE TRIGGER [dbo].[test_alert] ON [dbo].[test_trigger]
AFTER INSERT
AS
BEGIN
    INSERT INTO [dbo].[test_trigger_alert] ( [stringcol] )
    SELECT [stringcol]
    FROM [inserted]
END';
        $db
            ->createCommand($sql)
            ->execute();

        $record = new TestTrigger($db);

        $record->stringcol = 'test';

        $this->assertTrue($record->save());
        $this->assertEquals(1, $record->id);

        $testRecordQuery = new ActiveQuery(TestTriggerAlert::class, $db);

        $this->assertEquals('test', $testRecordQuery->findOne(1)->stringcol);
    }
}
