<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class EventsTraitTest extends \Yiisoft\ActiveRecord\Tests\EventsTraitTest
{
    public function testSetValueOnUpdateOnUpsertWithUpdatePropertiesFalse(): void
    {
        $this->markTestSkipped('Yiisoft\Db\Oracle\DMLQueryBuilder::upsertReturning() is not supported by Oracle.');
    }

    protected static function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }
}
