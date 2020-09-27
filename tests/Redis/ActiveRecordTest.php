<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Redis;

use JsonException;
use ReflectionException;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Redis\LuaScriptBuilder;
use Yiisoft\ActiveRecord\Tests\ActiveRecordTestTrait;
use Yiisoft\ActiveRecord\Tests\TestCase;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Category;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Dummy;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\NullValues;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderItem;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderWithNullFK;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderItemWithNullFK;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\InvalidParamException;
use Yiisoft\Db\Exception\NotSupportedException;

/**
 * @group redis
 */
final class ActiveRecordTest extends TestCase
{
    protected string $driverName = 'redis';

    public function setUp(): void
    {
        parent::setUp();

        $this->redisConnection->open();
        $this->redisConnection->flushdb();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->redisConnection->close();

        unset($this->redisConnection);
    }

    public function testCallFind(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        /** find count, sum, average, min, max, scalar */
        $this->assertEquals(3, $customer->find()->count());
        $this->assertEquals(2, $customer->find()->where(['in', 'id', [1, 2]])->count());
        $this->assertEquals(6, $customer->find()->sum('id'));
        $this->assertEquals(2, $customer->find()->average('id'));
        $this->assertEquals(1, $customer->find()->min('id'));
        $this->assertEquals(3, $customer->find()->max('id'));
    }

    public function testFindAll(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $this->assertCount(1, $customer->findAll(3));
        $this->assertCount(1, $customer->findAll(['id' => 1]));
        $this->assertCount(3, $customer->findAll(['id' => [1, 2, 3]]));
    }

    public function testFindScalar(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        /** query scalar */
        $customerName = $customer->find()->where(['id' => 2])->withAttribute('name')->scalar();

        $this->assertEquals('user2', $customerName);
    }

    public function testFindExists(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $this->assertTrue($customer->find()->where(['id' => 2])->exists());
        $this->assertTrue($customer->find()->where(['id' => 2])->withAttribute('name')->exists());

        $this->assertFalse($customer->find()->where(['id' => 42])->exists());
        $this->assertFalse($customer->find()->where(['id' => 42])->withAttribute('name')->exists());
    }

    public function testFindColumn(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $this->assertEquals(
            ['user1', 'user2', 'user3'],
            $customer->find()->withAttribute('name')->column()
        );

        $this->assertEquals(
            ['user3', 'user2', 'user1'],
            $customer->find()->orderBy(['name' => SORT_DESC])->withAttribute('name')->column()
        );
    }

    public function testFindLazyViaTable(): void
    {
        $this->orderData();

        $order = new Order($this->redisConnection);

        $orders = $order->findOne(2);
        $this->assertCount(0, $orders->books);
        $this->assertEquals(2, $orders->id);

        $orders = $order->find()->where(['id' => 1])->asArray()->one();
        $this->assertIsArray($orders);
    }

    public function testFindEagerViaTable(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $order = new Order($this->redisConnection);

        $orders = $order->find()->with('books')->orderBy('id')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertCount(2, $order->books);
        $this->assertEquals(1, $order->id);
        $this->assertEquals(1, $order->books[0]->id);
        $this->assertEquals(2, $order->books[1]->id);

        $order = $orders[1];
        $this->assertCount(0, $order->books);
        $this->assertEquals(2, $order->id);

        $order = $orders[2];
        $this->assertCount(1, $order->books);
        $this->assertEquals(3, $order->id);
        $this->assertEquals(2, $order->books[0]->id);

        /** https://github.com/yiisoft/yii2/issues/1402 */
        $orders = $order->find()->with('books')->orderBy('id')->asArray()->all();
        $this->assertCount(3, $orders);
        $this->assertIsArray($orders[0]['orderItems'][0]);

        $order = $orders[0];
        $this->assertCount(2, $order['books']);
        $this->assertEquals(1, $order['id']);
        $this->assertEquals(1, $order['books'][0]['id']);
        $this->assertEquals(2, $order['books'][1]['id']);
        $this->assertIsArray($order);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/5341}
     *
     * Issue: Plan 1 -- * Account * -- * User
     * Our Tests: Category 1 -- * Item * -- * Order
     */
    public function testDeeplyNestedTableRelationWith(): void
    {
        $this->categoryData();
        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $category = new Category($this->redisConnection);

        $categories = $category->find()->with('orders')->indexBy('id')->all();

        $category = $categories[1];

        $this->assertNotNull($category);

        $orders = $category->orders;

        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);

        $ids = [$orders[0]->id, $orders[1]->id];

        sort($ids);
        $this->assertEquals([1, 3], $ids);

        $category = $categories[2];
        $this->assertNotNull($category);

        $orders = $category->orders;
        $this->assertCount(1, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertEquals(2, $orders[0]->id);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/5341}
     *
     * Issue: Plan 1 -- * Account * -- * User
     * Our Tests: Category 1 -- * Item * -- * Order
     */
    public function testDeeplyNestedTableRelation(): void
    {
        $this->categoryData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $category = new Category($this->redisConnection);

        $categories = $category->findOne(1);
        $this->assertNotNull($categories);

        $orders = $categories->orders;
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);

        $ids = [$orders[0]->id, $orders[1]->id];

        sort($ids);
        $this->assertEquals([1, 3], $ids);

        $categories = $category->findOne(2);
        $this->assertNotNull($categories);

        $orders = $categories->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertInstanceOf(Order::class, $orders[0]);
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
        $orderItem = new OrderItem($this->redisConnection);

        $this->assertTrue($customer->isPrimaryKey(['id']));
        $this->assertFalse($customer->isPrimaryKey([]));
        $this->assertFalse($customer->isPrimaryKey(['id', 'name']));
        $this->assertFalse($customer->isPrimaryKey(['name']));
        $this->assertFalse($customer->isPrimaryKey(['name', 'email']));

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

    public function testOutdatedRelationsAreResetForExistingRecords(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderItem = new OrderItem($this->redisConnection);

        $orderItems = $orderItem->findOne(1);
        $this->assertEquals(1, $orderItems->order->id);
        $this->assertEquals(1, $orderItems->item->id);

        /** test `__set()`. */
        $orderItems->order_id = 2;
        $orderItems->item_id = 1;
        $this->assertEquals(2, $orderItems->order->id);
        $this->assertEquals(1, $orderItems->item->id);

        /** Test `setAttribute()`. */
        $orderItems->setAttribute('order_id', 3);
        $orderItems->setAttribute('item_id', 1);
        $this->assertEquals(3, $orderItems->order->id);
        $this->assertEquals(1, $orderItems->item->id);
    }

    public function testOutdatedViaTableRelationsAreReset(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $order = new Order($this->redisConnection);

        $orders = $order->findOne(1);
        $orderItemIds = ArrayHelper::getColumn($orders->items, 'id');
        sort($orderItemIds);
        $this->assertSame(['1', '2'], $orderItemIds);

        $orders->id = 2;
        $orderItemIds = ArrayHelper::getColumn($orders->items, 'id');
        sort($orderItemIds);
        $this->assertSame(['3', '4', '5'], $orderItemIds);

        unset($orders->id);
        $this->assertSame([], $orders->items);

        $order = new Order($this->redisConnection);
        $this->assertSame([], $orders->items);

        $order->id = 3;
        $orderItemIds = ArrayHelper::getColumn($order->items, 'id');
        $this->assertSame(['2'], $orderItemIds);
    }

    /**
     * overridden because null values are not part of the asArray result in redis
     */
    public function testFindAsArray(): void
    {
        $this->customerData();

        $customerInstance = new Customer($this->redisConnection);

        /** asArray */
        $customer = $customerInstance->find()->where(['id' => 2])->asArray()->one();

        $this->assertEquals([
            'id' => 2,
            'email' => 'user2@example.com',
            'name' => 'user2',
            'address' => 'address2',
            'status' => 1,
        ], $customer);

        /** find all asArray */
        $customers = $customerInstance->find()->asArray()->all();

        $this->assertCount(3, $customers);
        $this->assertArrayHasKey('id', $customers[0]);
        $this->assertArrayHasKey('name', $customers[0]);
        $this->assertArrayHasKey('email', $customers[0]);
        $this->assertArrayHasKey('address', $customers[0]);
        $this->assertArrayHasKey('status', $customers[0]);
        $this->assertArrayHasKey('id', $customers[1]);
        $this->assertArrayHasKey('name', $customers[1]);
        $this->assertArrayHasKey('email', $customers[1]);
        $this->assertArrayHasKey('address', $customers[1]);
        $this->assertArrayHasKey('status', $customers[1]);
        $this->assertArrayHasKey('id', $customers[2]);
        $this->assertArrayHasKey('name', $customers[2]);
        $this->assertArrayHasKey('email', $customers[2]);
        $this->assertArrayHasKey('address', $customers[2]);
        $this->assertArrayHasKey('status', $customers[2]);
    }

    public function testStatisticalFind(): void
    {
        $this->customerData();
        $this->orderItemData();

        $customer = new Customer($this->redisConnection);
        $orderItem = new OrderItem($this->redisConnection);

        // find count, sum, average, min, max, scalar
        $this->assertEquals(3, $customer->find()->count());
        $this->assertEquals(6, $customer->find()->sum('id'));
        $this->assertEquals(2, $customer->find()->average('id'));
        $this->assertEquals(1, $customer->find()->min('id'));
        $this->assertEquals(3, $customer->find()->max('id'));

        $this->assertEquals(7, $orderItem->find()->count());
        $this->assertEquals(8, $orderItem->find()->sum('quantity'));
    }

    public function testUpdatePk(): void
    {
        $this->orderItemData();

        $orderItemInstance = new OrderItem($this->redisConnection);

        /** updateCounters */
        $pk = ['order_id' => 2, 'item_id' => 4];

        $orderItem = $orderItemInstance->findOne($pk);
        $this->assertEquals(2, $orderItem->order_id);
        $this->assertEquals(4, $orderItem->item_id);

        $orderItem->order_id = 2;
        $orderItem->item_id = 10;
        $orderItem->save();

        $this->assertNull($orderItem->findOne($pk));
        $this->assertNotNull($orderItemInstance->findOne(['order_id' => 2, 'item_id' => 10]));
    }

    public function testFilterWhere(): void
    {
        $query = new ActiveQuery(Dummy::class, $this->redisConnection);

        $query->filterWhere([
            'id' => 0,
            'title' => '   ',
            'author_ids' => [],
        ]);
        $this->assertEquals(['id' => 0], $query->getWhere());

        $query->andFilterWhere(['status' => null]);
        $this->assertEquals(['id' => 0], $query->getWhere());

        $query->orFilterWhere(['name' => '']);
        $this->assertEquals(['id' => 0], $query->getWhere());

        /** should work with operator format */
        $query = new ActiveQuery(Dummy::class, $this->redisConnection);

        $condition = ['like', 'name', 'Alex'];
        $query->filterWhere($condition);
        $this->assertEquals($condition, $query->getWhere());

        $query->andFilterWhere(['between', 'id', null, null]);
        $this->assertEquals($condition, $query->getWhere());

        $query->orFilterWhere(['not between', 'id', null, null]);
        $this->assertEquals($condition, $query->getWhere());

        $query->andFilterWhere(['in', 'id', []]);
        $this->assertEquals($condition, $query->getWhere());

        $query->andFilterWhere(['not in', 'id', []]);
        $this->assertEquals($condition, $query->getWhere());

        $query->andFilterWhere(['not in', 'id', []]);
        $this->assertEquals($condition, $query->getWhere());

        $query->andFilterWhere(['like', 'id', '']);
        $this->assertEquals($condition, $query->getWhere());

        $query->andFilterWhere(['or like', 'id', '']);
        $this->assertEquals($condition, $query->getWhere());

        $query->andFilterWhere(['not like', 'id', '   ']);
        $this->assertEquals($condition, $query->getWhere());

        $query->andFilterWhere(['or not like', 'id', null]);
        $this->assertEquals($condition, $query->getWhere());
    }

    public function testFilterWhereRecursively(): void
    {
        $query = new ActiveQuery(Dummy::class, $this->redisConnection);

        $query->filterWhere(
            ['and', ['like', 'name', ''], ['like', 'title', ''], ['id' => 1], ['not', ['like', 'name', '']]]
        );
        $this->assertEquals(['and', ['id' => 1]], $query->getWhere());
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
                'profile_id' => null
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
                'profile_id' => null
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
                'profile_id' => null
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
                'profile_id' => null
            ]
        );
        $customer->save();
        $this->assertEquals(6, $customer->id);

        $customers = $customer->findOne(4);
        $this->assertNotNull($customers);
        $this->assertEquals('user4', $customers->name);

        $customers = $customer->findOne(5);
        $this->assertNotNull($customers);
        $this->assertEquals('user5', $customers->name);

        $customers = $customer->findOne(1);
        $this->assertNotNull($customer);
        $this->assertEquals('user1', $customers->name);

        $customers = $customer->findOne(6);
        $this->assertNotNull($customer);
        $this->assertEquals('user6', $customers->name);
    }

    public function testEscapeData(): void
    {
        $customer = new Customer($this->redisConnection);

        $customer->email = "the People's Republic of China";
        $customer->save();

        /** @var Customer $c */
        $c = $customer->findOne(['email' => "the People's Republic of China"]);
        $this->assertSame("the People's Republic of China", $c->email);
    }

    public function testFindEmptyWith(): void
    {
        $order = new Order($this->redisConnection);

        $orders = $order->find()->where(['total' => 100000])->orWhere(['total' => 1])->with('customer')->all();
        $this->assertEquals([], $orders);
    }

    public function testEmulateExecution(): void
    {
        $order = new Order($this->redisConnection);

        $rows = $order->find()->emulateExecution()->all();
        $this->assertSame([], $rows);

        $row = $order->find()->emulateExecution()->one();
        $this->assertNull($row);

        $exists = $order->find()->emulateExecution()->exists();
        $this->assertFalse($exists);

        $count = $order->find()->emulateExecution()->count();
        $this->assertSame(0, $count);

        $sum = $order->find()->emulateExecution()->sum('id');
        $this->assertSame(0, $sum);

        $sum = $order->find()->emulateExecution()->average('id');
        $this->assertSame(0, $sum);

        $max = $order->find()->emulateExecution()->max('id');
        $this->assertNull($max);

        $min = $order->find()->emulateExecution()->min('id');
        $this->assertNull($min);

        /** withAttribute() only needed for column() and scalar(). */
        $scalar = $order->find()->withAttribute('id')->emulateExecution()->scalar();
        $this->assertNull($scalar);

        /** withAttribute() only needed for column() and scalar(). */
        $column = $order->find()->withAttribute('id')->emulateExecution()->column();
        $this->assertSame([], $column);
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

    public function testBuildKey(): void
    {
        $this->orderItemData();

        $orderItemInstance = new OrderItem($this->redisConnection);

        $pk = ['order_id' => 3, 'item_id' => 'nostr'];
        $key = $orderItemInstance->buildKey($pk);

        $orderItem = $orderItemInstance->findOne($pk);
        $this->assertNotNull($orderItem);

        $pk = ['order_id' => $orderItem->order_id, 'item_id' => $orderItem->item_id];
        $this->assertEquals($key, $orderItemInstance->buildKey($pk));
    }

    public function testNotCondition(): void
    {
        $this->orderData();

        $order = new Order($this->redisConnection);

        $orders = $order->find()->where(['not', ['customer_id' => 2]])->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(1, $orders[0]['customer_id']);
    }


    public function testBetweenCondition(): void
    {
        $this->orderData();

        $order = new Order($this->redisConnection);

        $orders = $order->find()->where(['between', 'total', 30, 50])->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]['customer_id']);
        $this->assertEquals(2, $orders[1]['customer_id']);

        $orders = $order->find()->where(['not between', 'total', 30, 50])->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(1, $orders[0]['customer_id']);
    }

    public function testInCondition(): void
    {
        $this->orderData();

        $order = new Order($this->redisConnection);

        $orders = $order->find()->where(['in', 'customer_id', [1, 2]])->all();
        $this->assertCount(3, $orders);

        $orders = $order->find()->where(['not in', 'customer_id', [1, 2]])->all();
        $this->assertCount(0, $orders);

        $orders = $order->find()->where(['in', 'customer_id', [1]])->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(1, $orders[0]['customer_id']);

        $orders = $order->find()->where(['in', 'customer_id', [2]])->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]['customer_id']);
        $this->assertEquals(2, $orders[1]['customer_id']);
    }

    public function testCountQuery(): void
    {
        $this->itemData();

        $item = new Item($this->redisConnection);

        $query = $item->find();
        $this->assertEquals(5, $query->count());

        $query = $item->find()->where(['category_id' => 1]);
        $this->assertEquals(2, $query->count());

        /** negative values deactivate limit and offset (in case they were set before) */
        $query = $item->find()->where(['category_id' => 1])->limit(-1)->offset(-1);
        $this->assertEquals(2, $query->count());
    }

    public function illegalValuesForWhere(): array
    {
        return [
            [['id' => ["' .. redis.call('FLUSHALL') .. '" => 1]], ["'\\' .. redis.call(\\'FLUSHALL\\') .. \\'", 'rediscallFLUSHALL']],
            [['id' => ['`id`=`id` and 1' => 1]], ["'`id`=`id` and 1'", 'ididand']],
            [['id' => [
                'legal' => 1,
                '`id`=`id` and 1' => 1,
            ]], ["'`id`=`id` and 1'", 'ididand']],
            [['id' => [
                'nested_illegal' => [
                    'false or 1=' => 1
                ]
            ]], [], ['false or 1=']],
        ];
    }

    /**
     * @dataProvider illegalValuesForWhere
     *
     * @param array $filterWithInjection
     * @param array $expectedStrings
     * @param array $unexpectedStrings
     *
     * @throws Exception|NotSupportedException
     */
    public function testValueEscapingInWhere(
        array $filterWithInjection,
        array $expectedStrings,
        array $unexpectedStrings = []
    ): void {
        $item = new Item($this->redisConnection);

        $query = $item->find()->where($filterWithInjection['id']);

        $lua = new LuaScriptBuilder();

        $script = $lua->buildOne($query);

        foreach ($expectedStrings as $string) {
            $this->assertStringContainsString($string, $script);
        }

        foreach ($unexpectedStrings as $string) {
            $this->assertStringNotContainsString($string, $script);
        }
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
                    'false or 1=' => 1
                ]
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
                "' .. redis.call('FLUSHALL') .. '" => "' .. redis.call('FLUSHALL') .. '"
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
     * @throws Exception|InvalidConfigException|NotSupportedException|JsonException|ReflectionException
     * @throws InvalidParamException
     */
    public function testValueEscapingInFindByCondition(
        array $filterWithInjection,
        array $expectedStrings,
        array $unexpectedStrings = []
    ): void {
        $this->itemData();

        $item = new Item($this->redisConnection);

        $query = $this->invokeMethod($item, 'findByCondition', [$filterWithInjection['id']]);

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
        $this->assertGreaterThan(3, $item->find()->count());
    }

    public function testFind(): void
    {
        $this->customerData();

        $customerInstance = new Customer($this->redisConnection);

        /** find one */
        $result = $customerInstance->find();
        $this->assertInstanceOf(ActiveQueryInterface::class, $result);

        $customer = $result->one();
        $this->assertInstanceOf(Customer::class, $customer);

        /** find all */
        $customers = $customerInstance->find()->all();
        $this->assertCount(3, $customers);
        $this->assertInstanceOf(Customer::class, $customers[0]);
        $this->assertInstanceOf(Customer::class, $customers[1]);
        $this->assertInstanceOf(Customer::class, $customers[2]);

        /** find by a single primary key */
        $customer = $customerInstance->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer = $customerInstance->findOne(5);
        $this->assertNull($customer);

        $customer = $customerInstance->findOne(['id' => [5, 6, 1]]);
        $this->assertInstanceOf(Customer::class, $customer);

        $customer = $customerInstance->find()->where(['id' => [5, 6, 1]])->one();
        $this->assertNotNull($customer);

        /** find by column values */
        $customer = $customerInstance->findOne(['id' => 2, 'name' => 'user2']);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer = $customerInstance->findOne(['id' => 2, 'name' => 'user1']);
        $this->assertNull($customer);

        $customer = $customerInstance->findOne(['id' => 5]);
        $this->assertNull($customer);

        $customer = $customerInstance->findOne(['name' => 'user5']);
        $this->assertNull($customer);

        /** find by attributes */
        $customer = $customerInstance->find()->where(['name' => 'user2'])->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals(2, $customer->id);

        /** scope */
        $this->assertCount(2, $customerInstance->find()->active()->all());
        $this->assertEquals(2, $customerInstance->find()->active()->count());
    }

    public function testHasAttribute(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $this->assertTrue($customer->hasAttribute('id'));
        $this->assertTrue($customer->hasAttribute('email'));
        $this->assertFalse($customer->hasAttribute(0));
        $this->assertFalse($customer->hasAttribute(null));
        $this->assertFalse($customer->hasAttribute(42));

        $customer = $customer->findOne(1);
        $this->assertTrue($customer->hasAttribute('id'));
        $this->assertTrue($customer->hasAttribute('email'));
        $this->assertFalse($customer->hasAttribute(0));
        $this->assertFalse($customer->hasAttribute(null));
        $this->assertFalse($customer->hasAttribute(42));
    }

    public function testFindScalar1(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $customerName = $customer->find()->where(['id' => 2])->withAttribute('name')->scalar();
        $this->assertEquals('user2', $customerName);

        $customerName = $customer->find()->where(['status' => 2])->withAttribute('name')->scalar();
        $this->assertEquals('user3', $customerName);

        $customerName = $customer->find()->where(['status' => 2])->withAttribute('noname')->scalar();
        $this->assertNull($customerName);

        $customerId = $customer->find()->where(['status' => 2])->withAttribute('id')->scalar();
        $this->assertEquals(3, $customerId);
    }

    public function testRefresh(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $this->assertFalse($customer->refresh());

        $customer = $customer->findOne(1);
        $customer->name = 'to be refreshed';

        $this->assertTrue($customer->refresh());
        $this->assertEquals('user1', $customer->name);
    }

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

        $customerA = (new Customer($this->redisConnection))->findOne(1);
        $customerB = (new Customer($this->redisConnection))->findOne(2);
        $this->assertFalse($customerA->equals($customerB));

        $customerB = (new Customer($this->redisConnection))->findOne(1);
        $this->assertTrue($customerA->equals($customerB));

        $customerA = (new Customer($this->redisConnection))->findOne(1);
        $customerB = (new Item($this->redisConnection))->findOne(1);
        $this->assertFalse($customerA->equals($customerB));
    }

    public function testFindCount(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        /** @var $this TestCase|ActiveRecordTestTrait */
        $this->assertEquals(3, $customer->find()->count());

        $this->assertEquals(1, $customer->find()->where(['id' => 1])->count());
        $this->assertEquals(2, $customer->find()->where(['id' => [1, 2]])->count());
        $this->assertEquals(2, $customer->find()->where(['id' => [1, 2]])->offset(1)->count());
        $this->assertEquals(2, $customer->find()->where(['id' => [1, 2]])->offset(2)->count());

        /** limit should have no effect on count() */
        $this->assertEquals(3, $customer->find()->limit(1)->count());
        $this->assertEquals(3, $customer->find()->limit(2)->count());
        $this->assertEquals(3, $customer->find()->limit(10)->count());
        $this->assertEquals(3, $customer->find()->offset(2)->limit(2)->count());
    }

    public function testFindLimit(): void
    {
        $this->customerData();

        $customerInstance = new Customer($this->redisConnection);

        $customers = $customerInstance->find()->all();
        $this->assertCount(3, $customers);

        $customers = $customerInstance->find()->orderBy('id')->limit(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user1', $customers[0]->name);

        $customers = $customerInstance->find()->orderBy('id')->limit(1)->offset(1)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user2', $customers[0]->name);

        $customers = $customerInstance->find()->orderBy('id')->limit(1)->offset(2)->all();
        $this->assertCount(1, $customers);
        $this->assertEquals('user3', $customers[0]->name);

        $customers = $customerInstance->find()->orderBy('id')->limit(2)->offset(1)->all();
        $this->assertCount(2, $customers);
        $this->assertEquals('user2', $customers[0]->name);
        $this->assertEquals('user3', $customers[1]->name);

        $customers = $customerInstance->find()->limit(2)->offset(3)->all();
        $this->assertCount(0, $customers);

        $customer = $customerInstance->find()->orderBy('id')->one();
        $this->assertEquals('user1', $customer->name);

        $customer = $customerInstance->find()->orderBy('id')->offset(0)->one();
        $this->assertEquals('user1', $customer->name);

        $customer = $customerInstance->find()->orderBy('id')->offset(1)->one();
        $this->assertEquals('user2', $customer->name);

        $customer = $customerInstance->find()->orderBy('id')->offset(2)->one();
        $this->assertEquals('user3', $customer->name);

        $customer = $customerInstance->find()->offset(3)->one();
        $this->assertNull($customer);
    }

    public function testFindComplexCondition(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $this->assertEquals(
            2,
            $customer->find()->where(['OR', ['name' => 'user1'], ['name' => 'user2']])->count()
        );
        $this->assertCount(
            2,
            $customer->find()->where(['OR', ['name' => 'user1'], ['name' => 'user2']])->all()
        );

        $this->assertEquals(
            2,
            $customer->find()->where(['name' => ['user1', 'user2']])->count()
        );
        $this->assertCount(
            2,
            $customer->find()->where(['name' => ['user1', 'user2']])->all()
        );

        $this->assertEquals(
            1,
            $customer->find()->where(['AND', ['name' => ['user2', 'user3']], ['BETWEEN', 'status', 2, 4]])->count()
        );
        $this->assertCount(
            1,
            $customer->find()->where(['AND', ['name' => ['user2', 'user3']], ['BETWEEN', 'status', 2, 4]])->all()
        );
    }

    public function testFindNullValues(): void
    {
        $this->customerData();

        $customerInstance = new Customer($this->redisConnection);

        $customer = $customerInstance->findOne(2);
        $customer->name = null;
        $customer->save();

        $result = $customerInstance->find()->where(['name' => null])->all();
        $this->assertCount(1, $result);
        $this->assertEquals(2, reset($result)->primaryKey);
    }

    public function testExists(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $this->assertTrue($customer->find()->where(['id' => 2])->exists());
        $this->assertFalse($customer->find()->where(['id' => 5])->exists());
        $this->assertTrue($customer->find()->where(['name' => 'user1'])->exists());
        $this->assertFalse($customer->find()->where(['name' => 'user5'])->exists());

        $this->assertTrue($customer->find()->where(['id' => [2, 3]])->exists());
        $this->assertTrue($customer->find()->where(['id' => [2, 3]])->offset(1)->exists());
        $this->assertFalse($customer->find()->where(['id' => [2, 3]])->offset(2)->exists());
    }

    public function testFindLazy(): void
    {
        $this->customerData();
        $this->orderData();

        $customerInstance = new Customer($this->redisConnection);

        $customer = $customerInstance->findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $orders = $customer->orders;
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(2, $orders);
        $this->assertCount(1, $customer->relatedRecords);

        unset($customer['orders']);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $customer = $customerInstance->findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        $orders = $customer->getOrders()->where(['id' => 3])->all();
        $this->assertFalse($customer->isRelationPopulated('orders'));
        $this->assertCount(0, $customer->relatedRecords);
        $this->assertCount(1, $orders);
        $this->assertEquals(3, $orders[0]->id);
    }

    public function testFindEager(): void
    {
        $this->customerData();
        $this->orderData();

        $customerInstance = new Customer($this->redisConnection);
        $orderInstance = new Order($this->redisConnection);

        $customers = $customerInstance->find()->with('orders')->indexBy('id')->all();

        ksort($customers);
        $this->assertCount(3, $customers);
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
        $this->assertTrue($customers[2]->isRelationPopulated('orders'));
        $this->assertTrue($customers[3]->isRelationPopulated('orders'));
        $this->assertCount(1, $customers[1]->orders);
        $this->assertCount(2, $customers[2]->orders);
        $this->assertCount(0, $customers[3]->orders);

        unset($customers[1]->orders);
        $this->assertFalse($customers[1]->isRelationPopulated('orders'));

        $customer = $customerInstance->find()->where(['id' => 1])->with('orders')->one();
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertCount(1, $customer->orders);
        $this->assertCount(1, $customer->relatedRecords);

        /** multiple with() calls */
        $orders = $orderInstance->find()->with('customer', 'items')->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));

        $orders = $orderInstance->find()->with('customer')->with('items')->all();
        $this->assertCount(3, $orders);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
    }

    public function testFindLazyVia(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderInstance = new Order($this->redisConnection);

        /**
         * @var $this TestCase|ActiveRecordTestTrait
         * @var $order Order
         */
        $order = $orderInstance->findOne(1);

        $this->assertEquals(1, $order->id);
        $this->assertCount(2, $order->items);
        $this->assertEquals(1, $order->items[0]->id);
        $this->assertEquals(2, $order->items[1]->id);
    }

    public function testFindEagerViaRelation(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $order = new Order($this->redisConnection);

        /** @var $this TestCase|ActiveRecordTestTrait */
        $orders = $order->find()->with('items')->orderBy('id')->all();

        $this->assertCount(3, $orders);
        $order = $orders[0];

        $this->assertEquals(1, $order->id);
        $this->assertTrue($order->isRelationPopulated('items'));
        $this->assertCount(2, $order->items);
        $this->assertEquals(1, $order->items[0]->id);
        $this->assertEquals(2, $order->items[1]->id);
    }

    public function testFindNestedRelation(): void
    {
        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $customer = new Customer($this->redisConnection);

        /** @var $this TestCase|ActiveRecordTestTrait */
        $customers = $customer->find()->with('orders', 'orders.items')->indexBy('id')->all();

        ksort($customers);
        $this->assertCount(3, $customers);
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
        $this->assertTrue($customers[2]->isRelationPopulated('orders'));
        $this->assertTrue($customers[3]->isRelationPopulated('orders'));
        $this->assertCount(1, $customers[1]->orders);
        $this->assertCount(2, $customers[2]->orders);
        $this->assertCount(0, $customers[3]->orders);
        $this->assertTrue($customers[1]->orders[0]->isRelationPopulated('items'));
        $this->assertTrue($customers[2]->orders[0]->isRelationPopulated('items'));
        $this->assertTrue($customers[2]->orders[1]->isRelationPopulated('items'));
        $this->assertCount(2, $customers[1]->orders[0]->items);
        $this->assertCount(3, $customers[2]->orders[0]->items);
        $this->assertCount(1, $customers[2]->orders[1]->items);

        $customers = $customer->find()->where(['id' => 1])->with('ordersWithItems')->one();
        $this->assertTrue($customers->isRelationPopulated('ordersWithItems'));
        $this->assertCount(1, $customers->ordersWithItems);

        /** @var Order $order */
        $order = $customers->ordersWithItems[0];
        $this->assertTrue($order->isRelationPopulated('orderItems'));
        $this->assertCount(2, $order->orderItems);
    }

    /**
     * Ensure ActiveRelationTrait does preserve order of items on find via().
     *
     * {@see https://github.com/yiisoft/yii2/issues/1310.}
     */
    public function testFindEagerViaRelationPreserveOrder(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderInstance = new Order($this->redisConnection);

        $orders = $orderInstance->find()->with('itemsInOrder1')->orderBy('created_at')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertEquals(1, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertCount(2, $order->itemsInOrder1);
        $this->assertEquals(1, $order->itemsInOrder1[0]->id);
        $this->assertEquals(2, $order->itemsInOrder1[1]->id);

        $order = $orders[1];
        $this->assertEquals(2, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertCount(3, $order->itemsInOrder1);
        $this->assertEquals(5, $order->itemsInOrder1[0]->id);
        $this->assertEquals(3, $order->itemsInOrder1[1]->id);
        $this->assertEquals(4, $order->itemsInOrder1[2]->id);

        $order = $orders[2];
        $this->assertEquals(3, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertCount(1, $order->itemsInOrder1);
        $this->assertEquals(2, $order->itemsInOrder1[0]->id);
    }

    public function testFindEagerViaRelationPreserveOrderB(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $order = new Order($this->redisConnection);

        /**
         *  different order in via table.
         *
         *  @var Order \Yiisoft\ActiveRecord\ActiveRecordInterface
         */
        $orders = $order->find()->with('itemsInOrder2')->orderBy('created_at')->all();
        $this->assertCount(3, $orders);

        $order = $orders[0];
        $this->assertEquals(1, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertCount(2, $order->itemsInOrder2);
        $this->assertEquals(1, $order->itemsInOrder2[0]->id);
        $this->assertEquals(2, $order->itemsInOrder2[1]->id);

        $order = $orders[1];
        $this->assertEquals(2, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertCount(3, $order->itemsInOrder2);
        $this->assertEquals(5, $order->itemsInOrder2[0]->id);
        $this->assertEquals(3, $order->itemsInOrder2[1]->id);
        $this->assertEquals(4, $order->itemsInOrder2[2]->id);

        $order = $orders[2];
        $this->assertEquals(3, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertCount(1, $order->itemsInOrder2);
        $this->assertEquals(2, $order->itemsInOrder2[0]->id);
    }

    public function testUnlink(): void
    {
        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();
        $this->orderItemWithNullFkData();
        $this->orderWithNullFKData();

        $customerInstance = new Customer($this->redisConnection);
        $orderInstance = new Order($this->redisConnection);
        $orderWithNullFKInstance = new OrderWithNullFK($this->redisConnection);

        /**
         * has many without delete
         * @var $this TestCase|ActiveRecordTestTrait
         */
        $customer = $customerInstance->findOne(2);
        $this->assertCount(2, $customer->ordersWithNullFK);

        $customer->unlink('ordersWithNullFK', $customer->ordersWithNullFK[1], false);
        $this->assertCount(1, $customer->ordersWithNullFK);

        $orderWithNullFK = $orderWithNullFKInstance->findOne(3);
        $this->assertEquals(3, $orderWithNullFK->id);
        $this->assertNull($orderWithNullFK->customer_id);

        /** has many with delete */
        $customer = $customerInstance->findOne(2);
        $this->assertCount(2, $customer->orders);

        $customer->unlink('orders', $customer->orders[1], true);
        $this->assertCount(1, $customer->orders);
        $this->assertNull($orderInstance->findOne(3));

        /** via model with delete */
        $order = $orderInstance->findOne(2);
        $this->assertCount(3, $order->items);
        $this->assertCount(3, $order->orderItems);

        $order->unlink('items', $order->items[2], true);
        $this->assertCount(2, $order->items);
        $this->assertCount(2, $order->orderItems);

        /** via model without delete */
        $this->assertCount(3, $order->itemsWithNullFK);
        $order->unlink('itemsWithNullFK', $order->itemsWithNullFK[2], false);
        $this->assertCount(2, $order->itemsWithNullFK);
        $this->assertCount(2, $order->orderItems);
    }

    public function testUnlinkAllAndConditionSetNull(): void
    {
        $this->customerData();
        $this->orderWithNullFKData();

        $customerInstance = new Customer($this->redisConnection);
        $orderWithNullFKInstance = new OrderWithNullFK($this->redisConnection);

        /** in this test all orders are owned by customer 1 */
        $orderWithNullFKInstance->updateAll(['customer_id' => 1], ['not', ['id' => 0]]);

        $customer = $customerInstance->findOne(1);
        $this->assertCount(3, $customer->ordersWithNullFK);
        $this->assertCount(1, $customer->expensiveOrdersWithNullFK);
        $this->assertEquals(3, $orderWithNullFKInstance->find()->count());

        $customer->unlinkAll('expensiveOrdersWithNullFK');
        $this->assertCount(3, $customer->ordersWithNullFK);
        $this->assertCount(0, $customer->expensiveOrdersWithNullFK);
        $this->assertEquals(3, $orderWithNullFKInstance->find()->count());

        $customer = $customerInstance->findOne(1);
        $this->assertCount(2, $customer->ordersWithNullFK);
        $this->assertCount(0, $customer->expensiveOrdersWithNullFK);
    }

    public function testUnlinkAllAndConditionDelete(): void
    {
        $this->customerData();
        $this->orderData();

        $customerInstance = new Customer($this->redisConnection);
        $orderInstance = new Order($this->redisConnection);

        /** in this test all orders are owned by customer 1 */
        $orderInstance->updateAll(['customer_id' => 1], ['not', ['id' => 0]]);

        $customer = $customerInstance->findOne(1);
        $this->assertCount(3, $customer->orders);
        $this->assertCount(1, $customer->expensiveOrders);
        $this->assertEquals(3, $orderInstance->find()->count());

        $customer->unlinkAll('expensiveOrders', true);
        $this->assertCount(3, $customer->orders);
        $this->assertCount(0, $customer->expensiveOrders);
        $this->assertEquals(2, $orderInstance->find()->count());

        $customer = $customerInstance->findOne(1);
        $this->assertCount(2, $customer->orders);
        $this->assertCount(0, $customer->expensiveOrders);
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

    public function testUpdate(): void
    {
        $this->customerData();

        $customerInstance = new Customer($this->redisConnection);

        $customer = $customerInstance->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);
        $this->assertFalse($customer->isNewRecord);
        $this->assertEmpty($customer->dirtyAttributes);

        $customer->name = 'user2x';
        $customer->save();

        $this->assertEquals('user2x', $customer->name);
        $this->assertFalse($customer->isNewRecord);

        $customer2 = $customerInstance->findOne(2);
        $this->assertEquals('user2x', $customer2->name);

        /** updateAll */
        $customer = $customerInstance->findOne(3);
        $this->assertEquals('user3', $customer->name);

        $ret = $customerInstance->updateAll(['name' => 'temp'], ['id' => 3]);
        $this->assertEquals(1, $ret);

        $customer = $customerInstance->findOne(3);
        $this->assertEquals('temp', $customer->name);

        $ret = $customerInstance->updateAll(['name' => 'tempX'], ['not', ['id' => 0]]);
        $this->assertEquals(3, $ret);

        $ret = $customerInstance->updateAll(['name' => 'temp'], ['name' => 'user6']);
        $this->assertEquals(0, $ret);
    }

    public function testUpdateAttributes(): void
    {
        $this->customerData();

        $customerInstance = new Customer($this->redisConnection);

        $customer = $customerInstance->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);
        $this->assertFalse($customer->isNewRecord);

        $customer->updateAttributes(['name' => 'user2x']);
        $this->assertEquals('user2x', $customer->name);
        $this->assertFalse($customer->isNewRecord);

        $customer2 = $customerInstance->findOne(2);
        $this->assertEquals('user2x', $customer2->name);

        $customer = $customerInstance->findOne(1);
        $this->assertEquals('user1', $customer->name);
        $this->assertEquals(1, $customer->status);

        $customer->name = 'user1x';
        $customer->status = 2;
        $customer->updateAttributes(['name']);
        $this->assertEquals('user1x', $customer->name);
        $this->assertEquals(2, $customer->status);

        $customer = $customerInstance->findOne(1);
        $this->assertEquals('user1x', $customer->name);
        $this->assertEquals(1, $customer->status);
    }

    public function testUpdateCounters(): void
    {
        $this->customerData();
        $this->orderData();
        $this->orderItemData();

        $orderInstance = new OrderItem($this->redisConnection);

        /** updateCounters */
        $pk = ['order_id' => 2, 'item_id' => 4];

        $orderItem = $orderInstance->findOne($pk);
        $this->assertEquals(1, $orderItem->quantity);

        $ret = $orderItem->updateCounters(['quantity' => -1]);
        $this->assertEquals(1, $ret);
        $this->assertEquals(0, $orderItem->quantity);

        $orderItem = $orderInstance->findOne($pk);
        $this->assertEquals(0, $orderItem->quantity);

        /** updateAllCounters */
        $pk = ['order_id' => 1, 'item_id' => 2];

        $orderItem = $orderInstance->findOne($pk);
        $this->assertEquals(2, $orderItem->quantity);

        $ret = $orderInstance->updateAllCounters(['quantity' => 3, 'subtotal' => -10], $pk);
        $this->assertEquals(1, $ret);

        $orderItem = $orderInstance->findOne($pk);
        $this->assertEquals(5, $orderItem->quantity);
        $this->assertEquals(30, $orderItem->subtotal);
    }

    public function testDelete(): void
    {
        $this->customerData();

        $customerInstance = new Customer($this->redisConnection);

        /** delete */
        $customer = $customerInstance->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer->delete();

        $customer = $customerInstance->findOne(2);
        $this->assertNull($customer);

        /** deleteAll */
        $customers = $customerInstance->find()->all();
        $this->assertCount(2, $customers);

        $ret = $customerInstance->deleteAll();
        $this->assertEquals(2, $ret);

        $customers = $customerInstance->find()->all();
        $this->assertCount(0, $customers);

        $ret = $customerInstance->deleteAll();
        $this->assertEquals(0, $ret);
    }

    public function testBooleanAttribute(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $customer->name = 'boolean customer';
        $customer->email = 'mail@example.com';
        $customer->status = true;

        $customer->save();
        $customer->refresh();
        $this->assertEquals(1, $customer->status);

        $customer->status = false;
        $customer->save();

        $customer->refresh();
        $this->assertEquals(0, $customer->status);

        $customers = $customer->find()->where(['status' => true])->all();
        $this->assertCount(2, $customers);

        $customers = $customer->find()->where(['status' => false])->all();
        $this->assertCount(1, $customers);
    }

    public function testFindEmptyInCondition(): void
    {
        $this->customerData();

        $customer = new Customer($this->redisConnection);

        $customers = $customer->find()->where(['id' => [1]])->all();
        $this->assertCount(1, $customers);

        $customers = $customer->find()->where(['id' => []])->all();
        $this->assertCount(0, $customers);

        $customers = $customer->find()->where(['IN', 'id', [1]])->all();
        $this->assertCount(1, $customers);

        $customers = $customer->find()->where(['IN', 'id', []])->all();
        $this->assertCount(0, $customers);
    }

    public function testFindEagerIndexBy(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderInstance = new Order($this->redisConnection);

        $order = $orderInstance->find()->with('itemsIndexed')->where(['id' => 1])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));

        $items = $order->itemsIndexed;
        $this->assertCount(2, $items);
        $this->assertTrue(isset($items[1]));
        $this->assertTrue(isset($items[2]));

        $order = $orderInstance->find()->with('itemsIndexed')->where(['id' => 2])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));

        $items = $order->itemsIndexed;
        $this->assertCount(3, $items);
        $this->assertTrue(isset($items[3]));
        $this->assertTrue(isset($items[4]));
        $this->assertTrue(isset($items[5]));
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

    public function testLink(): void
    {
        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $customerInstance = new Customer($this->redisConnection);
        $itemInstance = new Item($this->redisConnection);
        $orderInstance = new Order($this->redisConnection);
        $orderItemInstance = new OrderItem($this->redisConnection);

        $customer = $customerInstance->findOne(2);
        $this->assertCount(2, $customer->orders);

        /** has many */
        $order = new Order($this->redisConnection);
        $order->total = 100;
        $order->created_at = time();
        $this->assertTrue($order->isNewRecord);

        /** belongs to */
        $order = new Order($this->redisConnection);
        $order->total = 100;
        $order->created_at = time();
        $this->assertTrue($order->isNewRecord);

        $customer = $customerInstance->findOne(1);
        $this->assertNull($order->customer);

        $order->link('customer', $customer);
        $this->assertFalse($order->isNewRecord);
        $this->assertEquals(1, $order->customer_id);
        $this->assertEquals(1, $order->customer->primaryKey);

        /** via model */
        $order = $orderInstance->findOne(1);
        $this->assertCount(2, $order->items);
        $this->assertCount(2, $order->orderItems);

        $orderItem = $orderInstance->findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertNull($orderItem);

        $item = $itemInstance->findOne(3);
        $order->link('items', $item, ['quantity' => 10, 'subtotal' => 100]);
        $this->assertCount(3, $order->items);
        $this->assertCount(3, $order->orderItems);

        $orderItem = $orderItemInstance->findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertInstanceOf(OrderItem::class, $orderItem);
        $this->assertEquals(10, $orderItem->quantity);
        $this->assertEquals(100, $orderItem->subtotal);
    }

    public function testFindByConditionException(): void
    {
        $dummy = new Dummy($this->redisConnection);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('"Yiisoft\ActiveRecord\Tests\Stubs\Redis\Dummy" must have a primary key.');
        $dummy->findOne([1]);
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

    private function categoryData(): void
    {
        $category = new Category($this->redisConnection);
        $category->setAttributes(['name' => 'Books']);
        $category->save();

        $category = new Category($this->redisConnection);
        $category->setAttributes(['name' => 'Movies']);
        $category->save();
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

    private function orderItemData(): void
    {
        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 1, 'item_id' => 1, 'quantity' => 1, 'subtotal' => 30.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 1, 'item_id' => 2, 'quantity' => 2, 'subtotal' => 40.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 4, 'quantity' => 1, 'subtotal' => 10.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 5, 'quantity' => 1, 'subtotal' => 15.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 2, 'item_id' => 3, 'quantity' => 1, 'subtotal' => 8.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 3, 'item_id' => 2, 'quantity' => 1, 'subtotal' => 40.0]);
        $orderItem->save();

        $orderItem = new OrderItem($this->redisConnection);
        $orderItem->setAttributes(['order_id' => 3, 'item_id' => 'nostr', 'quantity' => 1, 'subtotal' => 40.0]);
        $orderItem->save();
    }

    private function orderWithNullFKData(): void
    {
        $orderWithNullFKData = new OrderWithNullFK($this->redisConnection);
        $orderWithNullFKData->setAttributes(['customer_id' => 1, 'created_at' => 1325282384, 'total' => 110.0]);        $orderWithNullFKData->save();

        $orderWithNullFKData = new OrderWithNullFK($this->redisConnection);
        $orderWithNullFKData->setAttributes(['customer_id' => 2, 'created_at' => 1325334482, 'total' => 33.0]);
        $orderWithNullFKData->save();

        $orderWithNullFKData = new OrderWithNullFK($this->redisConnection);
        $orderWithNullFKData->setAttributes(['customer_id' => 2, 'created_at' => 1325502201, 'total' => 40.0]);
        $orderWithNullFKData->save();
    }

    private function orderItemWithNullFkData(): void
    {
        $orderItemWithNullFK = new OrderItemWithNullFK($this->redisConnection);
        $orderItemWithNullFK->setAttributes(['order_id' => 1, 'item_id' => 1, 'quantity' => 1, 'subtotal' => 30.0]);
        $orderItemWithNullFK->save();

        $orderItemWithNullFK = new OrderItemWithNullFK($this->redisConnection);
        $orderItemWithNullFK->setAttributes(['order_id' => 1, 'item_id' => 2, 'quantity' => 2, 'subtotal' => 40.0]);
        $orderItemWithNullFK->save();

        $orderItemWithNullFK = new OrderItemWithNullFK($this->redisConnection);
        $orderItemWithNullFK->setAttributes(['order_id' => 2, 'item_id' => 4, 'quantity' => 1, 'subtotal' => 10.0]);
        $orderItemWithNullFK->save();

        $orderItemWithNullFK = new OrderItemWithNullFK($this->redisConnection);
        $orderItemWithNullFK->setAttributes(['order_id' => 2, 'item_id' => 5, 'quantity' => 1, 'subtotal' => 15.0]);
        $orderItemWithNullFK->save();

        $orderItemWithNullFK = new OrderItemWithNullFK($this->redisConnection);
        $orderItemWithNullFK->setAttributes(['order_id' => 2, 'item_id' => 3, 'quantity' => 1, 'subtotal' => 8.0]);
        $orderItemWithNullFK->save();

        $orderItemWithNullFK = new OrderItemWithNullFK($this->redisConnection);
        $orderItemWithNullFK->setAttributes(['order_id' => 3, 'item_id' => 2, 'quantity' => 1, 'subtotal' => 40.0]);
        $orderItemWithNullFK->save();
    }
}
