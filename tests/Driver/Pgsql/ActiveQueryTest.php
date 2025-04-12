<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BitValues;
use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveQueryTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new PgsqlHelper())->createConnection();
    }

    public function testBit(): void
    {
        $this->checkFixture($this->db(), 'bit_values');

        $bitValueQuery = new ActiveQuery(BitValues::class);
        $falseBit = $bitValueQuery->findOne(1);
        $this->assertSame(0, $falseBit->val);

        $bitValueQuery = new ActiveQuery(BitValues::class);
        $trueBit = $bitValueQuery->findOne(2);
        $this->assertSame(1, $trueBit->val);
    }
}
