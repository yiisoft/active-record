<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mssql;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\TestTrigger;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\TestTriggerAlert;
use Yiisoft\ActiveRecord\Tests\Support\MssqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Factory\Factory;

final class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordTest
{
    public function testSaveWithTrigger(): void
    {
        $this->reloadFixtureAfterTest();

        // drop trigger if exist
        $sql = <<<SQL
        IF (OBJECT_ID('[dbo].[test_alert]') IS NOT NULL)
        BEGIN
            DROP TRIGGER [dbo].[test_alert];
        END
        SQL;
        $this->db()->createCommand($sql)->execute();

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
        $this->db()->createCommand($sql)->execute();

        $record = new TestTrigger();

        $record->stringcol = 'test';

        $record->save();
        $this->assertEquals(1, $record->id);

        $testRecordQuery = TestTriggerAlert::query();

        $this->assertEquals('test', $testRecordQuery->findByPk(1)->stringcol);
    }

    protected static function createConnection(): ConnectionInterface
    {
        return (new MssqlHelper())->createConnection();
    }

    protected function createFactory(): Factory
    {
        return (new MssqlHelper())->createFactory($this->db());
    }
}
