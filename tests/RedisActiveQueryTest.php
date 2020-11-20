<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Redis\ActiveQuery;
use Yiisoft\ActiveRecord\Redis\LuaScriptBuilder;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Category;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Dummy;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderItem;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderItemWithNullFK;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\OrderWithNullFK;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\NotSupportedException;

abstract class RedisActiveQueryTest extends TestCase
{
    public function testOptions(): void
    {
        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->on(['a' => 'b'])->joinWith('profile');

        $this->assertEquals(Customer::class, $query->getARClass());
        $this->assertEquals(['a' => 'b'], $query->getOn());
        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
    }

    public function testPopulateEmptyRows(): void
    {
        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->populate([]);

        $this->assertEquals([], $query);
    }

    public function testPopulateFilledRows(): void
    {
        $this->customerData();

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $rows = $query->all();

        $result = $query->populate($rows);

        $this->assertEquals($rows, $result);
    }

    public function testOne(): void
    {
        $this->customerData();

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->one();

        $this->assertInstanceOf(Customer::class, $query);
    }

    public function testJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->joinWith('profile');

        $this->assertEquals([[['profile'], true, 'LEFT JOIN']], $query->getJoinWith());
    }

    public function testInnerJoinWith(): void
    {
        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->innerJoinWith('profile');

        $this->assertEquals([[['profile'], true, 'INNER JOIN']], $query->getJoinWith());
    }

    public function testOnCondition(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->onCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnNotSet(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->andOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAndOnConditionOnSet(): void
    {
        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->on($onOld)->andOnCondition($on, $params);

        $this->assertEquals(['and', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnNotSet(): void
    {
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->orOnCondition($on, $params);

        $this->assertEquals($on, $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testOrOnConditionOnSet(): void
    {
        $onOld = ['active' => true];
        $on = ['active' => true];
        $params = ['a' => 'b'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->on($onOld)->orOnCondition($on, $params);

        $this->assertEquals(['or', $onOld, $on], $query->getOn());
        $this->assertEquals($params, $query->getParams());
    }

    public function testAliasYetSet(): void
    {
        $aliasOld = ['old'];

        $query = new ActiveQuery(Customer::class, $this->redisConnection);

        $query = $query->from($aliasOld)->alias('alias');

        $this->assertInstanceOf(ActiveQuery::class, $query);
        $this->assertEquals(['alias' => 'old'], $query->getFrom());
    }

    public function testFilterWhereRecursively(): void
    {
        $query = new ActiveQuery(Dummy::class, $this->redisConnection);

        $query->filterWhere(
            ['and', ['like', 'name', ''], ['like', 'title', ''], ['id' => 1], ['not', ['like', 'name', '']]]
        );
        $this->assertEquals(['and', ['id' => 1]], $query->getWhere());
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

        $categoryQuery = new ActiveQuery(Category::class, $this->redisConnection);

        $categories = $categoryQuery->with('orders')->indexBy('id')->all();

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

        $categoryQuery = new ActiveQuery(Category::class, $this->redisConnection);
        $categories = $categoryQuery->findOne(1);
        $this->assertNotNull($categories);

        $orders = $categories->orders;
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);

        $ids = [$orders[0]->id, $orders[1]->id];
        sort($ids);
        $this->assertEquals([1, 3], $ids);

        $categoryQuery = new ActiveQuery(Category::class, $this->redisConnection);
        $categories = $categoryQuery->findOne(2);
        $this->assertNotNull($categories);

        $orders = $categories->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertInstanceOf(Order::class, $orders[0]);
    }

    public function testOutdatedRelationsAreResetForExistingRecords(): void
    {
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->redisConnection);
        $orderItems = $orderItemQuery->findOne(1);
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

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $orders = $orderQuery->findOne(1);
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

    public function testStatisticalFind(): void
    {
        $this->customerData();
        $this->orderItemData();

        // find count, sum, average, min, max, scalar
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $this->assertEquals(3, $customerQuery->count());
        $this->assertEquals(6, $customerQuery->sum('id'));
        $this->assertEquals(2, $customerQuery->average('id'));
        $this->assertEquals(1, $customerQuery->min('id'));
        $this->assertEquals(3, $customerQuery->max('id'));

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->redisConnection);
        $this->assertEquals(7, $orderItemQuery->count());
        $this->assertEquals(8, $orderItemQuery->sum('quantity'));
    }

    public function testUpdatePk(): void
    {
        $this->orderItemData();

        /** updateCounters */
        $pk = ['order_id' => 2, 'item_id' => 4];

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->redisConnection);
        $orderItem = $orderItemQuery->findOne($pk);
        $this->assertEquals(2, $orderItem->order_id);
        $this->assertEquals(4, $orderItem->item_id);

        $orderItem->order_id = 2;
        $orderItem->item_id = 10;
        $orderItem->save();

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->redisConnection);
        $this->assertNull($orderItemQuery->findOne($pk));

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->redisConnection);
        $this->assertNotNull($orderItemQuery->findOne(['order_id' => 2, 'item_id' => 10]));
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

    public function testEscapeData(): void
    {
        $customer = new Customer($this->redisConnection);

        $customer->email = "the People's Republic of China";
        $customer->save();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $c = $customerQuery->findOne(['email' => "the People's Republic of China"]);
        $this->assertSame("the People's Republic of China", $c->email);
    }

    public function testEmulateExecution(): void
    {
        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);

        $rows = $orderQuery->emulateExecution()->all();
        $this->assertSame([], $rows);

        $row = $orderQuery->emulateExecution()->one();
        $this->assertNull($row);

        $exists = $orderQuery->emulateExecution()->exists();
        $this->assertFalse($exists);

        $count = $orderQuery->emulateExecution()->count();
        $this->assertSame(0, $count);

        $sum = $orderQuery->emulateExecution()->sum('id');
        $this->assertSame(0, $sum);

        $sum = $orderQuery->emulateExecution()->average('id');
        $this->assertSame(0, $sum);

        $max = $orderQuery->emulateExecution()->max('id');
        $this->assertNull($max);

        $min = $orderQuery->emulateExecution()->min('id');
        $this->assertNull($min);

        /** withAttribute() only needed for column() and scalar(). */
        $scalar = $orderQuery->withAttribute('id')->emulateExecution()->scalar();
        $this->assertNull($scalar);

        /** withAttribute() only needed for column() and scalar(). */
        $column = $orderQuery->withAttribute('id')->emulateExecution()->column();
        $this->assertSame([], $column);
    }

    public function testBuildKey(): void
    {
        $this->orderItemData();

        $orderItemInstance = new OrderItem($this->redisConnection);

        $pk = ['order_id' => 3, 'item_id' => 'nostr'];
        $key = $orderItemInstance->buildKey($pk);

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->redisConnection);
        $orderItem = $orderItemQuery->findOne($pk);
        $this->assertNotNull($orderItem);

        $pk = ['order_id' => $orderItem->order_id, 'item_id' => $orderItem->item_id];
        $this->assertEquals($key, $orderItemInstance->buildKey($pk));
    }

    public function testNotCondition(): void
    {
        $this->orderData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);

        $orders = $orderQuery->where(['not', ['customer_id' => 2]])->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(1, $orders[0]['customer_id']);
    }

    public function testBetweenCondition(): void
    {
        $this->orderData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);

        $orders = $orderQuery->where(['between', 'total', 30, 50])->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]['customer_id']);
        $this->assertEquals(2, $orders[1]['customer_id']);

        $orders = $orderQuery->where(['not between', 'total', 30, 50])->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(1, $orders[0]['customer_id']);
    }

    public function testInCondition(): void
    {
        $this->orderData();

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);

        $orders = $orderQuery->where(['in', 'customer_id', [1, 2]])->all();
        $this->assertCount(3, $orders);

        $orders = $orderQuery->where(['not in', 'customer_id', [1, 2]])->all();
        $this->assertCount(0, $orders);

        $orders = $orderQuery->where(['in', 'customer_id', [1]])->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(1, $orders[0]['customer_id']);

        $orders = $orderQuery->where(['in', 'customer_id', [2]])->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]['customer_id']);
        $this->assertEquals(2, $orders[1]['customer_id']);
    }

    public function testCountQuery(): void
    {
        $this->itemData();

        $itemQuery = new ActiveQuery(Item::class, $this->redisConnection);

        $this->assertEquals(5, $itemQuery->count());

        $query = $itemQuery->where(['category_id' => 1]);
        $this->assertEquals(2, $query->count());

        /** negative values deactivate limit and offset (in case they were set before) */
        $query = $itemQuery->where(['category_id' => 1])->limit(-1)->offset(-1);
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
                    'false or 1=' => 1,
                ],
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
        $itemQuery = new ActiveQuery(Item::class, $this->redisConnection);

        $query = $itemQuery->where($filterWithInjection['id']);

        $lua = new LuaScriptBuilder();

        $script = $lua->buildOne($query);

        foreach ($expectedStrings as $string) {
            $this->assertStringContainsString($string, $script);
        }

        foreach ($unexpectedStrings as $string) {
            $this->assertStringNotContainsString($string, $script);
        }
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

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(1);
        $this->assertTrue($customer->hasAttribute('id'));
        $this->assertTrue($customer->hasAttribute('email'));
        $this->assertFalse($customer->hasAttribute(0));
        $this->assertFalse($customer->hasAttribute(null));
        $this->assertFalse($customer->hasAttribute(42));
    }

    public function testEquals(): void
    {
        $this->customerData();
        $this->itemData();

        $customerA = (new ActiveQuery(Customer::class, $this->redisConnection))->findOne(1);
        $customerB = (new ActiveQuery(Customer::class, $this->redisConnection))->findOne(2);
        $this->assertFalse($customerA->equals($customerB));

        $customerB = (new ActiveQuery(Customer::class, $this->redisConnection))->findOne(1);
        $this->assertTrue($customerA->equals($customerB));

        $customerA = (new ActiveQuery(Customer::class, $this->redisConnection))->findOne(1);
        $customerB = (new ActiveQuery(Item::class, $this->redisConnection))->findOne(1);
        $this->assertFalse($customerA->equals($customerB));
    }

    public function testExists(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);

        $this->assertTrue($customerQuery->where(['id' => 2])->exists());
        $this->assertFalse($customerQuery->where(['id' => 5])->exists());
        $this->assertTrue($customerQuery->where(['name' => 'user1'])->exists());
        $this->assertFalse($customerQuery->where(['name' => 'user5'])->exists());

        $this->assertTrue($customerQuery->where(['id' => [2, 3]])->exists());
        $this->assertTrue($customerQuery->where(['id' => [2, 3]])->offset(1)->exists());
        $this->assertFalse($customerQuery->where(['id' => [2, 3]])->offset(2)->exists());
    }

    public function testUnlink(): void
    {
        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();
        $this->orderItemWithNullFkData();
        $this->orderWithNullFKData();

        /** has many without delete */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(2);
        $this->assertCount(2, $customer->ordersWithNullFK);

        $customer->unlink('ordersWithNullFK', $customer->ordersWithNullFK[1], false);
        $this->assertCount(1, $customer->ordersWithNullFK);

        $orderWithNullFKQuery = new ActiveQuery(OrderWithNullFK::class, $this->redisConnection);
        $orderWithNullFK = $orderWithNullFKQuery->findOne(3);
        $this->assertEquals(3, $orderWithNullFK->id);
        $this->assertNull($orderWithNullFK->customer_id);

        /** has many with delete */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(2);
        $this->assertCount(2, $customer->orders);

        $customer->unlink('orders', $customer->orders[1], true);
        $this->assertCount(1, $customer->orders);

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $this->assertNull($orderQuery->findOne(3));

        /** via model with delete */
        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $order = $orderQuery->findOne(2);
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

        /** in this test all orders are owned by customer 1 */
        $orderWithNullFKInstance = new OrderWithNullFK($this->redisConnection);
        $orderWithNullFKInstance->updateAll(['customer_id' => 1], ['not', ['id' => 0]]);

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(1);
        $this->assertCount(3, $customer->ordersWithNullFK);
        $this->assertCount(1, $customer->expensiveOrdersWithNullFK);

        $orderWithNullFKQuery = new ActiveQuery(OrderWithNullFK::class, $this->redisConnection);
        $this->assertEquals(3, $orderWithNullFKQuery->count());

        $customer->unlinkAll('expensiveOrdersWithNullFK');
        $this->assertCount(3, $customer->ordersWithNullFK);
        $this->assertCount(0, $customer->expensiveOrdersWithNullFK);
        $this->assertEquals(3, $orderWithNullFKQuery->count());

        $customer = $customerQuery->findOne(1);
        $this->assertCount(2, $customer->ordersWithNullFK);
        $this->assertCount(0, $customer->expensiveOrdersWithNullFK);
    }

    public function testUnlinkAllAndConditionDelete(): void
    {
        $this->customerData();
        $this->orderData();

        /** in this test all orders are owned by customer 1 */
        $orderInstance = new Order($this->redisConnection);
        $orderInstance->updateAll(['customer_id' => 1], ['not', ['id' => 0]]);

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(1);
        $this->assertCount(3, $customer->orders);
        $this->assertCount(1, $customer->expensiveOrders);

        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $this->assertEquals(3, $orderQuery->count());

        $customer->unlinkAll('expensiveOrders', true);
        $this->assertCount(3, $customer->orders);
        $this->assertCount(0, $customer->expensiveOrders);
        $this->assertEquals(2, $orderQuery->count());

        $customer = $customerQuery->findOne(1);
        $this->assertCount(2, $customer->orders);
        $this->assertCount(0, $customer->expensiveOrders);
    }

    public function testUpdate(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);
        $this->assertFalse($customer->isNewRecord);
        $this->assertEmpty($customer->dirtyAttributes);

        $customer->name = 'user2x';
        $customer->save();

        $this->assertEquals('user2x', $customer->name);
        $this->assertFalse($customer->isNewRecord);

        $customer2 = $customerQuery->findOne(2);
        $this->assertEquals('user2x', $customer2->name);

        /** updateAll */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(3);
        $this->assertEquals('user3', $customer->name);

        $customerInstance = new Customer($this->redisConnection);
        $ret = $customerInstance->updateAll(['name' => 'temp'], ['id' => 3]);
        $this->assertEquals(1, $ret);

        $customer = $customerQuery->findOne(3);
        $this->assertEquals('temp', $customer->name);

        $ret = $customerInstance->updateAll(['name' => 'tempX'], ['not', ['id' => 0]]);
        $this->assertEquals(3, $ret);

        $ret = $customerInstance->updateAll(['name' => 'temp'], ['name' => 'user6']);
        $this->assertEquals(0, $ret);
    }

    public function testUpdateAttributes(): void
    {
        $this->customerData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);
        $this->assertFalse($customer->isNewRecord);

        $customer->updateAttributes(['name' => 'user2x']);
        $this->assertEquals('user2x', $customer->name);
        $this->assertFalse($customer->isNewRecord);

        $customer2 = $customerQuery->findOne(2);
        $this->assertEquals('user2x', $customer2->name);

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(1);
        $this->assertEquals('user1', $customer->name);
        $this->assertEquals(1, $customer->status);

        $customer->name = 'user1x';
        $customer->status = 2;
        $customer->updateAttributes(['name']);
        $this->assertEquals('user1x', $customer->name);
        $this->assertEquals(2, $customer->status);

        $customer = $customerQuery->findOne(1);
        $this->assertEquals('user1x', $customer->name);
        $this->assertEquals(1, $customer->status);
    }

    public function testUpdateCounters(): void
    {
        $this->customerData();
        $this->orderData();
        $this->orderItemData();

        /** updateCounters */
        $pk = ['order_id' => 2, 'item_id' => 4];

        $orderQuery = new ActiveQuery(OrderItem::class, $this->redisConnection);
        $orderItem = $orderQuery->findOne($pk);
        $this->assertEquals(1, $orderItem->quantity);

        $ret = $orderItem->updateCounters(['quantity' => -1]);
        $this->assertEquals(1, $ret);
        $this->assertEquals(0, $orderItem->quantity);

        $orderItem = $orderQuery->findOne($pk);
        $this->assertEquals(0, $orderItem->quantity);

        /** updateAllCounters */
        $pk = ['order_id' => 1, 'item_id' => 2];

        $orderQuery = new ActiveQuery(OrderItem::class, $this->redisConnection);
        $orderItem = $orderQuery->findOne($pk);
        $this->assertEquals(2, $orderItem->quantity);

        $orderInstance = new OrderItem($this->redisConnection);
        $ret = $orderInstance->updateAllCounters(['quantity' => 3, 'subtotal' => -10], $pk);
        $this->assertEquals(1, $ret);

        $orderItem = $orderQuery->findOne($pk);
        $this->assertEquals(5, $orderItem->quantity);
        $this->assertEquals(30, $orderItem->subtotal);
    }

    public function testDelete(): void
    {
        $this->customerData();

        /** delete */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(2);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);

        $customer->delete();

        $customer = $customerQuery->findOne(2);
        $this->assertNull($customer);

        /** deleteAll */
        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->all();
        $this->assertCount(2, $customers);

        $customerInstance = new Customer($this->redisConnection);
        $ret = $customerInstance->deleteAll();
        $this->assertEquals(2, $ret);

        $customers = $customerQuery->all();
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

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customers = $customerQuery->where(['status' => true])->all();
        $this->assertCount(2, $customers);

        $customers = $customerQuery->where(['status' => false])->all();
        $this->assertCount(1, $customers);
    }

    public function testLink(): void
    {
        $this->customerData();
        $this->itemData();
        $this->orderData();
        $this->orderItemData();

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(2);
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

        $customerQuery = new ActiveQuery(Customer::class, $this->redisConnection);
        $customer = $customerQuery->findOne(1);
        $this->assertNull($order->customer);

        $order->link('customer', $customer);
        $this->assertFalse($order->isNewRecord);
        $this->assertEquals(1, $order->customer_id);
        $this->assertEquals(1, $order->customer->primaryKey);

        /** via active query */
        $orderQuery = new ActiveQuery(Order::class, $this->redisConnection);
        $order = $orderQuery->findOne(1);
        $this->assertCount(2, $order->items);
        $this->assertCount(2, $order->orderItems);

        $orderItem = $orderQuery->findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertNull($orderItem);

        $itemQuery = new ActiveQuery(Item::class, $this->redisConnection);
        $item = $itemQuery->findOne(3);
        $order->link('items', $item, ['quantity' => 10, 'subtotal' => 100]);
        $this->assertCount(3, $order->items);
        $this->assertCount(3, $order->orderItems);

        $orderItemQuery = new ActiveQuery(OrderItem::class, $this->redisConnection);
        $orderItem = $orderItemQuery->findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertInstanceOf(OrderItem::class, $orderItem);
        $this->assertEquals(10, $orderItem->quantity);
        $this->assertEquals(100, $orderItem->subtotal);
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
        $orderWithNullFKData->setAttributes(['customer_id' => 1, 'created_at' => 1325282384, 'total' => 110.0]);
        $orderWithNullFKData->save();

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
