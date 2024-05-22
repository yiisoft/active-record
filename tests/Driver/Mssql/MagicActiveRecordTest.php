<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mssql;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\TestTrigger;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\TestTriggerAlert;
use Yiisoft\ActiveRecord\Tests\Support\MssqlHelper;

final class MagicActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\MagicActiveRecordTest
{
    public function setUp(): void
    {
        parent::setUp();

        $mssqlHelper = new MssqlHelper();
        $this->db = $mssqlHelper->createConnection();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db->close();

        unset($this->db);
    }

    public function testSaveWithTrigger(): void
    {
        $this->checkFixture($this->db, 'test_trigger');

        // drop trigger if exist
        $sql = <<<SQL
        IF (OBJECT_ID('[dbo].[test_alert]') IS NOT NULL)
        BEGIN
            DROP TRIGGER [dbo].[test_alert];
        END
        SQL;
        $this->db->createCommand($sql)->execute();

        // create trigger
        $sql = <<<SQL
        CREATE TRIGGER [dbo].[test_alert] ON [dbo].[test_trigger]
        AFTER INSERT
        AS
        BEGIN
            INSERT INTO [dbo].[test_trigger_alert] ( [stringcol] )
            SELECT [stringcol]
            FROM [inserted]
        END
        SQL;
        $this->db->createCommand($sql)->execute();

        $record = new TestTrigger($this->db);

        $record->stringcol = 'test';

        $this->assertTrue($record->save());
        $this->assertEquals(1, $record->id);

        $testRecordQuery = new ActiveQuery(TestTriggerAlert::class, $this->db);

        $this->assertEquals('test', $testRecordQuery->findOne(1)->stringcol);
    }
}
