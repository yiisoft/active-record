<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\Internal\ModelRelationFilter;
use Yiisoft\ActiveRecord\Tests\Driver\Pgsql\Stubs\Promotion;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BitValues;
use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Expression\Value\ArrayValue;
use Yiisoft\Db\QueryBuilder\Condition\ArrayOverlaps;

final class ActiveQueryTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryTest
{
    public function testBit(): void
    {
        $bitValueQuery = BitValues::query();
        $falseBit = $bitValueQuery->findByPk(1);
        $this->assertSame(0, $falseBit->val);

        $bitValueQuery = BitValues::query();
        $trueBit = $bitValueQuery->findByPk(2);
        $this->assertSame(1, $trueBit->val);
    }

    public function testModelRelationFilterUsesArrayOverlapsForArrayColumns(): void
    {
        $query = Promotion::query()->link(['array_item_ids' => 'id']);

        ModelRelationFilter::apply($query, [
            ['id' => 1],
        ]);

        $where = $query->getWhere();

        $this->assertInstanceOf(ArrayOverlaps::class, $where);
        $this->assertSame('array_item_ids', $where->column);
        $this->assertInstanceOf(ArrayValue::class, $where->values);
        $this->assertSame([1], $where->values->value);
    }

    protected static function createConnection(): ConnectionInterface
    {
        return (new PgsqlHelper())->createConnection();
    }
}
