<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use JsonException;
use ReflectionException;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Redis\LuaScriptBuilder;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Dummy;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\NullValues;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderItem;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\InvalidParamException;
use Yiisoft\Db\Exception\NotSupportedException;

abstract class RedisActiveRecordTest extends TestCase
{
    public function testEquals(): void
    {
        $this->customerData();
        $this->itemData();

        $customerA = new Customer($this->redisConnection);
        $customerB = new Customer($this->redisConnection);
        $this->assertFalse($customerA->equals($customerB));

        $customerA = new Customer($this->redisConnection);
        $customerB = new Item($this->redisConnection);
        $this->assertFalse($customerA->equals($customerB));
    }

    public function testRefresh(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $this->assertFalse($customer->refresh());

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);

        $customer = $customerQuery->findOne(1);
        $customer->name = 'to be refreshed';

        $this->assertTrue($customer->refresh());
        $this->assertEquals('user1', $customer->name);
    }

    public function testStoreNull(): void
    {
        $record = new NullValues($this->redisConnection);

        $this->assertNull($record->var1);
        $this->assertNull($record->var2);
        $this->assertNull($record->var3);
        $this->assertNull($record->stringcol);

        $record->var1 = 123;
        $record->var2 = 456;
        $record->var3 = 789;
        $record->stringcol = 'hello!';

        $record->save();
        $this->assertTrue($record->refresh());

        $this->assertEquals(123, $record->var1);
        $this->assertEquals(456, $record->var2);
        $this->assertEquals(789, $record->var3);
        $this->assertEquals('hello!', $record->stringcol);

        $record->var1 = null;
        $record->var2 = null;
        $record->var3 = null;
        $record->stringcol = null;

        $record->save();
        $this->assertTrue($record->refresh());

        $this->assertNull($record->var1);
        $this->assertNull($record->var2);
        $this->assertNull($record->var3);

        $this->assertNull($record->stringcol);

        $record->var1 = 0;
        $record->var2 = 0;
        $record->var3 = 0;
        $record->stringcol = '';

        $record->save();
        $this->assertTrue($record->refresh());

        $this->assertEquals(0, $record->var1);
        $this->assertEquals(0, $record->var2);
        $this->assertEquals(0, $record->var3);
        $this->assertEquals('', $record->stringcol);
    }

    public function testStoreEmpty(): void
    {
        $record = new NullValues($this->redisConnection);

        /* this is to simulate empty html form submission */
        $record->var1 = '';
        $record->var2 = '';
        $record->var3 = '';
        $record->stringcol = '';

        $record->save();
        $this->assertTrue($record->refresh());

        /** {@see https://github.com/yiisoft/yii2/commit/34945b0b69011bc7cab684c7f7095d837892a0d4#commitcomment-4458225} */
        $this->assertSame($record->var1, $record->var2);
        $this->assertSame($record->var2, $record->var3);
    }

    public function testIsPrimaryKey(): void
    {
        $customer = new Customer($this->redisConnection);

        $this->assertTrue($customer->isPrimaryKey(['id']));
        $this->assertFalse($customer->isPrimaryKey([]));
        $this->assertFalse($customer->isPrimaryKey(['id', 'name']));
        $this->assertFalse($customer->isPrimaryKey(['name']));
        $this->assertFalse($customer->isPrimaryKey(['name', 'email']));

        $orderItem = new OrderItem($this->redisConnection);

        $this->assertTrue($orderItem->isPrimaryKey(['order_id', 'item_id']));
        $this->assertFalse($orderItem->isPrimaryKey([]));
        $this->assertFalse($orderItem->isPrimaryKey(['order_id']));
        $this->assertFalse($orderItem->isPrimaryKey(['item_id']));
        $this->assertFalse($orderItem->isPrimaryKey(['quantity']));
        $this->assertFalse($orderItem->isPrimaryKey(['quantity', 'subtotal']));
        $this->assertFalse($orderItem->isPrimaryKey(['order_id', 'item_id', 'quantity']));
    }

    public function testOutdatedRelationsAreResetForNewRecords(): void
    {
        $this->itemData();
        $this->orderData();

        $orderItem = new OrderItem($this->redisConnection);

        $orderItem->order_id = 1;
        $orderItem->item_id = 3;
        $this->assertEquals(1, $orderItem->order->id);
        $this->assertEquals(3, $orderItem->item->id);

        /** test `__set()`. */
        $orderItem->order_id = 2;
        $orderItem->item_id = 1;
        $this->assertEquals(2, $orderItem->order->id);
        $this->assertEquals(1, $orderItem->item->id);

        /** test `setAttribute()`. */
        $orderItem->setAttribute('order_id', 2);
        $orderItem->setAttribute('item_id', 2);
        $this->assertEquals(2, $orderItem->order->id);
        $this->assertEquals(2, $orderItem->item->id);
    }

    public function testAutoIncrement(): void
    {
        $this->redisConnection->executeCommand('FLUSHDB');

        $customer = new Customer($this->redisConnection);

        $customer->setAttributes(
            [
                'id' => 4,
                'email' => 'user4@example.com',
                'name' => 'user4',
                'address' => 'address4',
                'status' => 1,
                'profile_id' => null,
            ]
        );

        $customer->save();
        $this->assertEquals(4, $customer->id);

        $customer = new Customer($this->redisConnection);

        $customer->setAttributes(
            [
                'email' => 'user5@example.com',
                'name' => 'user5',
                'address' => 'address5',
                'status' => 1,
                'profile_id' => null,
            ]
        );

        $customer->save();
        $this->assertEquals(5, $customer->id);

        $customer = new Customer($this->redisConnection);

        $customer->setAttributes(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 1,
                'profile_id' => null,
            ]
        );

        $customer->save();
        $this->assertEquals(1, $customer->id);

        $customer = new Customer($this->redisConnection);

        $customer->setAttributes(
            [
                'email' => 'user6@example.com',
                'name' => 'user6',
                'address' => 'address6',
                'status' => 1,
                'profile_id' => null,
            ]
        );

        $customer->save();
        $this->assertEquals(6, $customer->id);

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->findOne(4);
        $this->assertNotNull($customers);
        $this->assertEquals('user4', $customers->name);

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->findOne(5);
        $this->assertNotNull($customers);
        $this->assertEquals('user5', $customers->name);

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->findOne(1);
        $this->assertNotNull($customer);
        $this->assertEquals('user1', $customers->name);

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->findOne(6);
        $this->assertNotNull($customer);
        $this->assertEquals('user6', $customers->name);
    }

    /**
     * {@see https://github.com/yiisoft/yii2-redis/issues/93}
     */
    public function testDeleteAllWithCondition(): void
    {
        $this->orderData();

        $order = new Order($this->redisConnection);

        $deletedCount = $order->deleteAll(['in', 'id', [1, 2, 3]]);
        $this->assertEquals(3, $deletedCount);
    }

    public function illegalValuesForFindByCondition(): array
    {
        return [
            /** code injection */
            [['id' => ["' .. redis.call('FLUSHALL') .. '" => 1]], ["'\\' .. redis.call(\\'FLUSHALL\\') .. \\'", 'rediscallFLUSHALL'], ["' .. redis.call('FLUSHALL') .. '"]],
            [['id' => ['`id`=`id` and 1' => 1]], ["'`id`=`id` and 1'", 'ididand']],
            [['id' => [
                'legal' => 1,
                '`id`=`id` and 1' => 1,
            ]], ["'`id`=`id` and 1'", 'ididand']],
            [['id' => [
                'nested_illegal' => [
                    'false or 1=' => 1,
                ],
            ]], [], ['false or 1=']],

            /** custom condition injection */
            [['id' => [
                'or',
                '1=1',
                'id' => 'id',
            ]], ["cid0=='or' or cid0=='1=1' or cid0=='id'"], []],
            [['id' => [
                0 => 'or',
                'first' => '1=1',
                'second' => 1,
            ]], ["cid0=='or' or cid0=='1=1' or cid0=='1'"], []],
            [['id' => [
                'name' => 'test',
                'email' => 'test@example.com',
                "' .. redis.call('FLUSHALL') .. '" => "' .. redis.call('FLUSHALL') .. '",
            ]], ["'\\' .. redis.call(\\'FLUSHALL\\') .. \\'", 'rediscallFLUSHALL'], ["' .. redis.call('FLUSHALL') .. '"]],
        ];
    }

    /**
     * @dataProvider illegalValuesForFindByCondition
     *
     * @param array $filterWithInjection
     * @param array $expectedStrings
     * @param array $unexpectedStrings
     *
     * @throws Exception|InvalidConfigException|JsonException|NotSupportedException|ReflectionException
     * @throws InvalidParamException
     */
    public function testValueEscapingInFindByCondition(
        array $filterWithInjection,
        array $expectedStrings,
        array $unexpectedStrings = []
    ): void {
        $this->itemData();

        $itemQuery = new ActiveQuery(Item::class, $this->redisConnection);

        $query = $this->invokeMethod($itemQuery, 'findByCondition', [$filterWithInjection['id']]);

        $lua = new LuaScriptBuilder();

        $script = $lua->buildOne($query);

        foreach ($expectedStrings as $string) {
            $this->assertStringContainsString($string, $script);
        }

        foreach ($unexpectedStrings as $string) {
            $this->assertStringNotContainsString($string, $script);
        }

        /** ensure injected FLUSHALL call did not succeed */
        $query->one();
        $itemQuery = new ActiveQuery(Item::class, $this->redisConnection);
        $this->assertGreaterThan(3, $itemQuery->count());
    }

    public function testInsert(): void
    {
        $customer = new Customer($this->redisConnection);

        $customer->email = 'user4@example.com';
        $customer->name = 'user4';
        $customer->address = 'address4';

        $this->assertNull($customer->id);
        $this->assertTrue($customer->isNewRecord);

        $customer->save();

        $this->assertNotNull($customer->id);
        $this->assertFalse($customer->isNewRecord);
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $customer = new Customer($this->redisConnection);

        $customer->id = 1337;
        $customer->email = 'user1337@example.com';
        $customer->name = 'user1337';
        $customer->address = 'address1337';

        $this->assertTrue($customer->isNewRecord);
        $customer->save();

        $this->assertEquals(1337, $customer->id);
        $this->assertFalse($customer->isNewRecord);
    }

    public function testAttributeAccess(): void
    {
        $model = new Customer($this->redisConnection);

        $this->assertTrue($model->canSetProperty('name'));
        $this->assertTrue($model->canGetProperty('name'));
        $this->assertFalse($model->canSetProperty('unExistingColumn'));
        $this->assertFalse(isset($model->name));

        $model->name = 'foo';
        $this->assertTrue(isset($model->name));

        unset($model->name);
        $this->assertNull($model->name);

        /** {@see https://github.com/yiisoft/yii2-gii/issues/190} */
        $baseModel = new Customer($this->redisConnection);
        $this->assertFalse($baseModel->hasProperty('unExistingColumn'));

        $customer = new Customer($this->redisConnection);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->canGetProperty('id'));
        $this->assertTrue($customer->canSetProperty('id'));

        /** tests that we really can get and set this property */
        $this->assertNull($customer->id);
        $customer->id = 10;
        $this->assertNotNull($customer->id);

        /** Let's test relations */
        $this->assertTrue($customer->canGetProperty('orderItems'));
        $this->assertFalse($customer->canSetProperty('orderItems'));

        /** Newly created model must have empty relation */
        $this->assertSame([], $customer->orderItems);

        /** does it still work after accessing the relation? */
        $this->assertTrue($customer->canGetProperty('orderItems'));
        $this->assertFalse($customer->canSetProperty('orderItems'));

        try {
            /** @var $itemClass ActiveRecordInterface */
            $customer->orderItems = [new Item($this->redisConnection)];
            $this->fail('setter call above MUST throw Exception');
        } catch (\Exception $e) {
            /** catch exception "Setting read-only property" */
            $this->assertInstanceOf(InvalidCallException::class, $e);
        }

        /** related attribute $customer->orderItems didn't change cause it's read-only */
        $this->assertSame([], $customer->orderItems);
        $this->assertFalse($customer->canGetProperty('non_existing_property'));
        $this->assertFalse($customer->canSetProperty('non_existing_property'));
    }

    public function testGetOldPrimaryKeyException(): void
    {
        $dummyClass = new Dummy($this->redisConnection);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Yiisoft\ActiveRecord\Tests\Stubs\Redis\Dummy does not have a primary key. ' .
            'You should either define a primary key for the corresponding table or override the primaryKey() method.'
        );

        $dummyClass->getOldPrimaryKey();
    }

    private function itemData(): void
    {
        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Agile Web Application Development with Yii1.1 and PHP5', 'category_id' => 1]);
        $item->save();

        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Yii 1.1 Application Development Cookbook', 'category_id' => 1]);
        $item->save();

        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Ice Age', 'category_id' => 2]);
        $item->save();

        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Toy Story', 'category_id' => 2]);
        $item->save();

        $item = new Item($this->redisConnection);
        $item->setAttributes(['name' => 'Cars', 'category_id' => 2]);
        $item->save();
    }

    private function orderData(): void
    {
        $order = new Order($this->redisConnection);
        $order->setAttributes(['customer_id' => 1, 'created_at' => 1325282384, 'total' => 110.0]);
        $order->save();

        $order = new Order($this->redisConnection);
        $order->setAttributes(['customer_id' => 2, 'created_at' => 1325334482, 'total' => 33.0]);
        $order->save();

        $order = new Order($this->redisConnection);
        $order->setAttributes(['customer_id' => 2, 'created_at' => 1325502201, 'total' => 40.0]);
        $order->save();
    }
}
