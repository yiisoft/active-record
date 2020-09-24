<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Animal;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BitValues;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Cat;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Category;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithAlias;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithConstructor;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Document;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Dog;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Dossier;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\NullValues;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItem;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Profile;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\ProfileWithConstructor;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItemWithConstructor;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderWithConstructor;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItemWithNullFK;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Type;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\StaleObjectException;

use function ucfirst;

abstract class ActiveRecordTest extends TestCase
{
    use ActiveRecordTestTrait;

    public function testCustomColumns(): void
    {
        $customer = new Customer($this->db);

        /** find custom column */
        if ($this->driverName === 'oci') {
            $customers = $customer->find()->select(['{{customer}}.*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->one();
        } else {
            $customers = $customer->find()->select(['*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->one();
        }

        $this->assertEquals(3, $customers->id);
        $this->assertEquals(4, $customers->status2);
    }

    public function testCallFind(): void
    {
        $this->loadFixture($this->db);

        $customer = new Customer($this->db);

        /** find count, sum, average, min, max, scalar */
        $this->assertEquals(3, $customer->find()->count());
        $this->assertEquals(2, $customer->find()->where('[[id]]=1 OR [[id]]=2')->count());
        $this->assertEquals(6, $customer->find()->sum('[[id]]'));
        $this->assertEquals(2, $customer->find()->average('[[id]]'));
        $this->assertEquals(1, $customer->find()->min('[[id]]'));
        $this->assertEquals(3, $customer->find()->max('[[id]]'));
        $this->assertEquals(3, $customer->find()->select('COUNT(*)')->scalar());
    }

    public function testFindAll(): void
    {
        $customer = new Customer($this->db);

        $this->assertCount(1, $customer->findAll(3));
        $this->assertCount(1, $customer->findAll(['id' => 1]));
        $this->assertCount(3, $customer->findAll(['id' => [1, 2, 3]]));
    }

    public function testFindScalar(): void
    {
        $customer = new Customer($this->db);

        /** query scalar */
        $customerName = $customer->find()->where(['[[id]]' => 2])->select('[[name]]')->scalar();

        $this->assertEquals('user2', $customerName);
    }

    public function testFindExists(): void
    {
        $customer = new Customer($this->db);

        $this->assertTrue($customer->find()->where(['[[id]]' => 2])->exists());
        $this->assertTrue($customer->find()->where(['[[id]]' => 2])->select('[[name]]')->exists());

        $this->assertFalse($customer->find()->where(['[[id]]' => 42])->exists());
        $this->assertFalse($customer->find()->where(['[[id]]' => 42])->select('[[name]]')->exists());
    }

    public function testFindColumn(): void
    {
        $customer = new Customer($this->db);

        $this->assertEquals(
            ['user1', 'user2', 'user3'],
            $customer->find()->select('[[name]]')->column()
        );

        $this->assertEquals(
            ['user3', 'user2', 'user1'],
            $customer->find()->orderBy(['[[name]]' => SORT_DESC])->select('[[name]]')->column()
        );
    }

    public function testFindBySql(): void
    {
        $customer = new Customer($this->db);

        /** find one */
        $customer = $customer->findBySql('SELECT * FROM {{customer}} ORDER BY [[id]] DESC')->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user3', $customer->name);

        /** find all */
        $customers = $customer->findBySql('SELECT * FROM {{customer}}')->all();
        $this->assertCount(3, $customers);

        /** find with parameter binding */
        $customer = $customer->findBySql('SELECT * FROM {{customer}} WHERE [[id]]=:id', [':id' => 2])->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/8593}
     */
    public function testCountWithFindBySql(): void
    {
        $customer = new Customer($this->db);

        $query = $customer->findBySql('SELECT * FROM {{customer}}');
        $this->assertEquals(3, $query->count());

        $query = $customer->findBySql('SELECT * FROM {{customer}} WHERE  [[id]]=:id', [':id' => 2]);
        $this->assertEquals(1, $query->count());
    }

    public function testFindLazyViaTable(): void
    {
        $order = new Order($this->db);

        $order = $order->findOne(2);

        $this->assertCount(0, $order->books);
        $this->assertEquals(2, $order->id);

        $order = $order->find()->where(['id' => 1])->asArray()->one();
        $this->assertIsArray($order);
    }

    public function testFindEagerViaTable(): void
    {
        $order = new Order($this->db);

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

    public function testDeeplyNestedTableRelation(): void
    {
        $customer = new Customer($this->db);

        $customers = $customer->findOne(1);
        $this->assertNotNull($customer);

        $items = $customers->orderItems;

        $this->assertCount(2, $items);
        $this->assertEquals(1, $items[0]->id);
        $this->assertEquals(2, $items[1]->id);
        $this->assertInstanceOf(Item::class, $items[0]);
        $this->assertInstanceOf(Item::class, $items[1]);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/5341}
     *
     * Issue: Plan 1 -- * Account * -- * User
     * Our Tests: Category 1 -- * Item * -- * Order
     */
    public function testDeeplyNestedTableRelation2(): void
    {
        $category = new Category($this->db);

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
        $record = new NullValues($this->db);

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
        $record = new NullValues($this->db);

        /** this is to simulate empty html form submission */
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
        $customer = new Customer($this->db);
        $orderItem = new OrderItem($this->db);

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

    public function testJoinWith(): void
    {
        $order = new Order($this->db);

        /** left join and eager loading */
        $orders = $order->find()->joinWith('customer')->orderBy('customer.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** inner join filtering and eager loading */
        $orders = $order->find()->innerJoinWith(
            [
                'customer' => function ($query) {
                    $query->where('{{customer}}.[[id]]=2');
                }
            ]
        )->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering, eager loading, conditions on both primary and relation */
        $orders = $order->find()->innerJoinWith(
            [
                'customer' => function ($query) {
                    $query->where(['customer.id' => 2]);
                }
            ]
        )->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading */
        $orders = $order->find()->innerJoinWith(
            [
                'customer' => static function ($query) {
                    $query->where('{{customer}}.[[id]]=2');
                },
            ],
            false
        )->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading, conditions on both primary and relation */
        $orders = $order->find()->innerJoinWith(
            [
                'customer' => static function ($query) {
                    $query->where(['customer.id' => 2]);
                },
            ],
            false
        )->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));

        /** join with via-relation */
        $orders = $order->find()->innerJoinWith('books')->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertCount(2, $orders[0]->books);
        $this->assertCount(1, $orders[1]->books);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));

        /** join with sub-relation */
        $orders = $order->find()->innerJoinWith(
            [
                'items' => function ($q) {
                    $q->orderBy('item.id');
                },
                'items.category' => function ($q) {
                    $q->where('{{category}}.[[id]] = 2');
                }
            ]
        )->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));

        /** join with table alias */
        $orders = $order->find()->joinWith(
            [
                'customer' => function ($q) {
                    $q->from('customer c');
                },
            ]
        )->orderBy('c.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** join with table alias */
        $orders = $order->find()->joinWith('customer as c')->orderBy('c.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** join with table alias sub-relation */
        $orders = $order->find()->innerJoinWith(
            [
                'items as t' => function ($q) {
                    $q->orderBy('t.id');
                },
                'items.category as c' => function ($q) {
                    $q->where('{{c}}.[[id]] = 2');
                },
            ]
        )->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));


        /** join with ON condition */
        $orders = $order->find()->joinWith('books2')->orderBy('order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->books2);
        $this->assertCount(0, $orders[1]->books2);
        $this->assertCount(1, $orders[2]->books2);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(2, $orders[1]->id);
        $this->assertEquals(3, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books2'));
        $this->assertTrue($orders[1]->isRelationPopulated('books2'));
        $this->assertTrue($orders[2]->isRelationPopulated('books2'));

        /** lazy loading with ON condition */
        $order = $order->findOne(1);
        $this->assertCount(2, $order->books2);

        $order = $order->findOne(2);
        $this->assertCount(0, $order->books2);

        $order = $order->findOne(3);
        $this->assertCount(1, $order->books2);

        /** eager loading with ON condition */
        $orders = $order->find()->with('books2')->all();
        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->books2);
        $this->assertCount(0, $orders[1]->books2);
        $this->assertCount(1, $orders[2]->books2);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(2, $orders[1]->id);
        $this->assertEquals(3, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books2'));
        $this->assertTrue($orders[1]->isRelationPopulated('books2'));
        $this->assertTrue($orders[2]->isRelationPopulated('books2'));

        /** join with count and query */
        $query = $order->find()->joinWith('customer');
        $count = $query->count();
        $this->assertEquals(3, $count);

        $orders = $query->all();
        $this->assertCount(3, $orders);

        /** {@see https://github.com/yiisoft/yii2/issues/2880} */
        $query = $order->findOne(1);
        $customer = $query->getCustomer()->joinWith(
            [
                'orders' => static function ($q) {
                    $q->orderBy([]);
                },
            ]
        )->one();
        $this->assertEquals(1, $customer->id);

        $order = $order->find()->joinWith(
            [
                'items' => static function ($q) {
                    $q->from(['items' => 'item'])->orderBy('items.id');
                },
            ]
        )->orderBy('order.id')->one();

        /** join with sub-relation called inside Closure */
        $orders = $order->find()->joinWith(
            [
                'items' => static function ($q) {
                    $q->orderBy('item.id');
                    $q->joinWith([
                        'category' => static function ($q) {
                            $q->where('{{category}}.[[id]] = 2');
                        },
                    ]);
                },
            ]
        )->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
    }

    public function testJoinWithAndScope(): void
    {
        $customer = new Customer($this->db);

        /**  hasOne inner join */
        $customers = $customer->find()->active()->innerJoinWith('profile')->orderBy('customer.id')->all();
        $this->assertCount(1, $customers);
        $this->assertEquals(1, $customers[0]->id);
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));

        /** hasOne outer join */
        $customers = $customer->find()->active()->joinWith('profile')->orderBy('customer.id')->all();
        $this->assertCount(2, $customers);
        $this->assertEquals(1, $customers[0]->id);
        $this->assertEquals(2, $customers[1]->id);
        $this->assertInstanceOf(Profile::class, $customers[0]->profile);
        $this->assertNull($customers[1]->profile);
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));
        $this->assertTrue($customers[1]->isRelationPopulated('profile'));

        /** hasMany */
        $customers = $customer->find()->active()->joinWith(
            [
                'orders' => static function ($q) {
                    $q->orderBy('order.id');
                },
            ]
        )->orderBy('customer.id DESC, order.id')->all();
        $this->assertCount(2, $customers);
        $this->assertEquals(2, $customers[0]->id);
        $this->assertEquals(1, $customers[1]->id);
        $this->assertTrue($customers[0]->isRelationPopulated('orders'));
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
    }

    /**
     * This query will do the same join twice, ensure duplicated JOIN gets removed.
     *
     * {@see https://github.com/yiisoft/yii2/pull/2650}
     */
    public function testJoinWithVia(): void
    {
        $order = new Order($this->db);

        $this->db->getQueryBuilder()->setSeparator("\n");

        $rows = $order->find()->joinWith('itemsInOrder1')->joinWith(
            [
                'items' => static function ($q) {
                    $q->orderBy('item.id');
                },
            ]
        )->all();
        $this->assertNotEmpty($rows);
    }

    public function aliasMethodProvider(): array
    {
        return [
            ['explicit']
        ];
    }

    /**
     * Tests the alias syntax for joinWith: 'alias' => 'relation'.
     *
     * @dataProvider aliasMethodProvider
     *
     * @param string $aliasMethod whether alias is specified explicitly or using the query syntax {{@tablename}}
     */
    public function testJoinWithAlias(string $aliasMethod): void
    {
        $order = new Order($this->db);

        /** left join and eager loading */
        $query = $order->find()->joinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $orders = $query->orderBy('c.id DESC, order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->orderBy('{{@customer}}.id DESC, {{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->orderBy(
                $query->applyAlias('customer', 'id') . ' DESC,' . $query->applyAlias('order', 'id')
            )->all();
        }

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** inner join filtering and eager loading */
        $query = $order->find()->innerJoinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $orders = $query->where('{{c}}.[[id]]=2')->orderBy('order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->where('{{@customer}}.[[id]]=2')->orderBy('{{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->where(
                [$query->applyAlias('customer', 'id') => 2]
            )->orderBy($query->applyAlias('order', 'id'))->all();
        }

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading */
        $query = $order->find()->innerJoinWith(['customer c'], false);

        if ($aliasMethod === 'explicit') {
            $orders = $query->where('{{c}}.[[id]]=2')->orderBy('order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->where('{{@customer}}.[[id]]=2')->orderBy('{{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->where(
                [$query->applyAlias('customer', 'id') => 2]
            )->orderBy($query->applyAlias('order', 'id'))->all();
        }

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        /** join with via-relation */
        $query = $order->find()->innerJoinWith(['books b']);

        if ($aliasMethod === 'explicit') {
            $orders = $query->where(
                ['b.name' => 'Yii 1.1 Application Development Cookbook']
            )->orderBy('order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->where(
                ['{{@item}}.name' => 'Yii 1.1 Application Development Cookbook']
            )->orderBy('{{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->where(
                [$query->applyAlias('book', 'name') => 'Yii 1.1 Application Development Cookbook']
            )->orderBy($query->applyAlias('order', 'id'))->all();
        }

        $this->assertCount(2, $orders);
        $this->assertCount(2, $orders[0]->books);
        $this->assertCount(1, $orders[1]->books);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));


        /** joining sub relations */
        $query = $order->find()->innerJoinWith(
            [
                'items i' => static function ($q) use ($aliasMethod) {
                    /** @var $q ActiveQuery */
                    if ($aliasMethod === 'explicit') {
                        $q->orderBy('{{i}}.id');
                    } elseif ($aliasMethod === 'querysyntax') {
                        $q->orderBy('{{@item}}.id');
                    } elseif ($aliasMethod === 'applyAlias') {
                        $q->orderBy($q->applyAlias('item', 'id'));
                    }
                },
                'items.category c' => static function ($q) use ($aliasMethod) {
                    /**  @var $q ActiveQuery */
                    if ($aliasMethod === 'explicit') {
                        $q->where('{{c}}.[[id]] = 2');
                    } elseif ($aliasMethod === 'querysyntax') {
                        $q->where('{{@category}}.[[id]] = 2');
                    } elseif ($aliasMethod === 'applyAlias') {
                        $q->where([$q->applyAlias('category', 'id') => 2]);
                    }
                }
            ]
        );

        if ($aliasMethod === 'explicit') {
            $orders = $query->orderBy('{{i}}.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->orderBy('{{@item}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->orderBy($query->applyAlias('item', 'id'))->all();
        }

        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));

        /** join with ON condition */
        if ($aliasMethod === 'explicit' || $aliasMethod === 'querysyntax') {
            $relationName = 'books' . ucfirst($aliasMethod);

            $orders = $order->find()->joinWith(["$relationName b"])->orderBy('order.id')->all();

            $this->assertCount(3, $orders);
            $this->assertCount(2, $orders[0]->$relationName);
            $this->assertCount(0, $orders[1]->$relationName);
            $this->assertCount(1, $orders[2]->$relationName);
            $this->assertEquals(1, $orders[0]->id);
            $this->assertEquals(2, $orders[1]->id);
            $this->assertEquals(3, $orders[2]->id);
            $this->assertTrue($orders[0]->isRelationPopulated($relationName));
            $this->assertTrue($orders[1]->isRelationPopulated($relationName));
            $this->assertTrue($orders[2]->isRelationPopulated($relationName));
        }

        /** join with ON condition and alias in relation definition */
        if ($aliasMethod === 'explicit' || $aliasMethod === 'querysyntax') {
            $relationName = 'books' . ucfirst($aliasMethod) . 'A';

            $orders = $order->find()->joinWith([(string)$relationName])->orderBy('order.id')->all();

            $this->assertCount(3, $orders);
            $this->assertCount(2, $orders[0]->$relationName);
            $this->assertCount(0, $orders[1]->$relationName);
            $this->assertCount(1, $orders[2]->$relationName);
            $this->assertEquals(1, $orders[0]->id);
            $this->assertEquals(2, $orders[1]->id);
            $this->assertEquals(3, $orders[2]->id);
            $this->assertTrue($orders[0]->isRelationPopulated($relationName));
            $this->assertTrue($orders[1]->isRelationPopulated($relationName));
            $this->assertTrue($orders[2]->isRelationPopulated($relationName));
        }

        /** join with count and query */
        $query = $order->find()->joinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $count = $query->count('c.id');
        } elseif ($aliasMethod === 'querysyntax') {
            $count = $query->count('{{@customer}}.id');
        } elseif ($aliasMethod === 'applyAlias') {
            $count = $query->count($query->applyAlias('customer', 'id'));
        }

        $this->assertEquals(3, $count);

        $orders = $query->all();
        $this->assertCount(3, $orders);

        /** relational query */
        $order = $order->findOne(1);

        $customerQuery = $order->getCustomer()->innerJoinWith(['orders o'], false);

        if ($aliasMethod === 'explicit') {
            $customer = $customerQuery->where(['o.id' => 1])->one();
        } elseif ($aliasMethod === 'querysyntax') {
            $customer = $customerQuery->where(['{{@order}}.id' => 1])->one();
        } elseif ($aliasMethod === 'applyAlias') {
            $customer = $customerQuery->where([$query->applyAlias('order', 'id') => 1])->one();
        }

        $this->assertEquals(1, $customer->id);
        $this->assertNotNull($customer);

        /** join with sub-relation called inside Closure */
        $orders = $order->find()->joinWith(
            [
                'items' => static function ($q) use ($aliasMethod) {
                    /** @var $q ActiveQuery */
                    $q->orderBy('item.id');
                    $q->joinWith(['category c']);

                    if ($aliasMethod === 'explicit') {
                        $q->where('{{c}}.[[id]] = 2');
                    } elseif ($aliasMethod === 'querysyntax') {
                        $q->where('{{@category}}.[[id]] = 2');
                    } elseif ($aliasMethod === 'applyAlias') {
                        $q->where([$q->applyAlias('category', 'id') => 2]);
                    }
                }
            ]
        )->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
    }

    public function testJoinWithSameTable(): void
    {
        $order = new Order($this->db);

        /**
         * join with the same table but different aliases alias is defined in the relation definition without eager
         * loading
         */
        $query = $order->find()
            ->joinWith('bookItems', false)
            ->joinWith('movieItems', false)
            ->where(['movies.name' => 'Toy Story']);

        $orders = $query->all();

        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true)
        );
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('bookItems'));
        $this->assertFalse($orders[0]->isRelationPopulated('movieItems'));

        /** with eager loading */
        $query = $order->find()
            ->joinWith('bookItems', true)
            ->joinWith('movieItems', true)
            ->where(['movies.name' => 'Toy Story']);

        $orders = $query->all();

        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true)
        );
        $this->assertCount(0, $orders[0]->bookItems);
        $this->assertCount(3, $orders[0]->movieItems);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('bookItems'));
        $this->assertTrue($orders[0]->isRelationPopulated('movieItems'));


        /**
         * join with the same table but different aliases alias is defined in the call to joinWith() without eager
         * loading
         */
        $query = $order->find()
            ->joinWith(
                [
                    'itemsIndexed books' => static function ($q) {
                        $q->onCondition('books.category_id = 1');
                    },
                ],
                false
            )->joinWith(
                [
                    'itemsIndexed movies' => static function ($q) {
                        $q->onCondition('movies.category_id = 2');
                    },
                ],
                false
            )->where(['movies.name' => 'Toy Story']);

        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true)
        );
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, only for one relation as it would be overwritten otherwise. */
        $query = $order->find()
            ->joinWith(
                [
                    'itemsIndexed books' => static function ($q) {
                        $q->onCondition('books.category_id = 1');
                    },
                ],
                false
            )
            ->joinWith(
                [
                    'itemsIndexed movies' => static function ($q) {
                        $q->onCondition('movies.category_id = 2');
                    },
                ],
                true
            )->where(['movies.name' => 'Toy Story']);

        $orders = $query->all();
        $this->assertCount(1, $orders, $query->createCommand()->getRawSql() . print_r($orders, true));
        $this->assertCount(3, $orders[0]->itemsIndexed);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, and the other relation */
        $query = $order->find()
            ->joinWith(
                [
                    'itemsIndexed books' => static function ($q) {
                        $q->onCondition('books.category_id = 1');
                    },
                ],
                true
            )
            ->joinWith(
                [
                    'itemsIndexed movies' => static function ($q) {
                        $q->onCondition('movies.category_id = 2');
                    },
                ],
                false
            )
            ->where(['movies.name' => 'Toy Story']);

        $orders = $query->all();
        $this->assertCount(1, $orders, $query->createCommand()->getRawSql() . print_r($orders, true));
        $this->assertCount(0, $orders[0]->itemsIndexed);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/10201}
     * {@see https://github.com/yiisoft/yii2/issues/9047}
     */
    public function testFindCompositeRelationWithJoin(): void
    {
        $orderItem = new OrderItem($this->db);

        /** @var $orderItems OrderItem */
        $orderItems = $orderItem->findOne([1, 1]);

        $orderItemNoJoin = $orderItems->orderItemCompositeNoJoin;
        $this->assertInstanceOf(OrderItem::class, $orderItemNoJoin);

        $orderItemWithJoin = $orderItems->orderItemCompositeWithJoin;
        $this->assertInstanceOf(OrderItem::class, $orderItemWithJoin);
    }

    public function testFindSimpleRelationWithJoin(): void
    {
        $order = new Order($this->db);

        $orders = $order->findOne(1);

        $customerNoJoin = $orders->customer;
        $this->assertInstanceOf(Customer::class, $customerNoJoin);

        $customerWithJoin = $orders->customerJoinedWithProfile;
        $this->assertInstanceOf(Customer::class, $customerWithJoin);

        $customerWithJoinIndexOrdered = $orders->customerJoinedWithProfileIndexOrdered;
        $this->assertArrayHasKey('user1', $customerWithJoinIndexOrdered);
        $this->assertInstanceOf(Customer::class, $customerWithJoinIndexOrdered['user1']);
        $this->assertIsArray($customerWithJoinIndexOrdered);
    }

    public function tableNameProvider(): array
    {
        return [
            ['order', 'order_item'],
            ['order', '{{%order_item}}'],
            ['{{%order}}', 'order_item'],
            ['{{%order}}', '{{%order_item}}'],
        ];
    }

    /**
     * Test whether conditions are quoted correctly in conditions where joinWith is used.
     *
     * {@see https://github.com/yiisoft/yii2/issues/11088}
     *
     * @dataProvider tableNameProvider
     *
     * @param string $orderTableName
     * @param string $orderItemTableName
     *
     * @throws Exception|InvalidConfigException
     */
    public function testRelationWhereParams(string $orderTableName, string $orderItemTableName): void
    {
        $order = new Order($this->db);
        $orderItem = new OrderItem($this->db);

        $order->setTableName($orderTableName);
        $orderItem->setTableName($orderItemTableName);

        $order = $order->findOne(1);
        $itemsSQL = $order->getOrderitems()->createCommand()->getRawSql();
        $expectedSQL = $this->replaceQuotes('SELECT * FROM [[order_item]] WHERE [[order_id]]=1');
        $this->assertEquals($expectedSQL, $itemsSQL);

        $order = $order->findOne(1);
        $itemsSQL = $order->getOrderItems()->joinWith('item')->createCommand()->getRawSql();
        $expectedSQL = $this->replaceQuotes(
            'SELECT [[order_item]].* FROM [[order_item]] LEFT JOIN [[item]] ON [[order_item]].[[item_id]] = [[item]].[[id]] WHERE [[order_item]].[[order_id]]=1'
        );
        $this->assertEquals($expectedSQL, $itemsSQL);

        $order->setTableName(null);
        $orderItem->setTableName(null);
    }

    public function testOutdatedRelationsAreResetForNewRecords(): void
    {
        $orderItem = new OrderItem($this->db);

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
        $orderItem = new OrderItem($this->db);

        $orderItems = $orderItem->findOne(1);
        $this->assertEquals(1, $orderItems->order->id);
        $this->assertEquals(1, $orderItems->item->id);

        /** test `__set()`. */
        $orderItem->order_id = 2;
        $orderItem->item_id = 1;
        $this->assertEquals(2, $orderItem->order->id);
        $this->assertEquals(1, $orderItem->item->id);

        /** Test `setAttribute()`. */
        $orderItem->setAttribute('order_id', 3);
        $orderItem->setAttribute('item_id', 1);
        $this->assertEquals(3, $orderItem->order->id);
        $this->assertEquals(1, $orderItem->item->id);
    }

    public function testOutdatedCompositeKeyRelationsAreReset(): void
    {
        $dossier = new Dossier($this->db);

        $dossiers = $dossier->findOne(['department_id' => 1, 'employee_id' => 1]);
        $this->assertEquals('John Doe', $dossiers->employee->fullName);

        $dossiers->department_id = 2;
        $this->assertEquals('Ann Smith', $dossiers->employee->fullName);

        $dossiers->employee_id = 2;
        $this->assertEquals('Will Smith', $dossiers->employee->fullName);

        unset($dossiers->employee_id);
        $this->assertNull($dossiers->employee);

        $dossier = new Dossier($this->db);
        $this->assertNull($dossier->employee);

        $dossier->employee_id = 1;
        $dossier->department_id = 2;
        $this->assertEquals('Ann Smith', $dossier->employee->fullName);

        $dossier->employee_id = 2;
        $this->assertEquals('Will Smith', $dossier->employee->fullName);
    }

    public function testOutdatedViaTableRelationsAreReset(): void
    {
        $order = new Order($this->db);

        $orders = $order->findOne(1);
        $orderItemIds = ArrayHelper::getColumn($orders->items, 'id');
        sort($orderItemIds);
        $this->assertSame([1, 2], $orderItemIds);

        $orders->id = 2;
        sort($orderItemIds);
        $orderItemIds = ArrayHelper::getColumn($orders->items, 'id');
        $this->assertSame([3, 4, 5], $orderItemIds);

        unset($orders->id);
        $this->assertSame([], $orders->items);

        $order = new Order($this->db);
        $this->assertSame([], $order->items);

        $order->id = 3;
        $orderItemIds = ArrayHelper::getColumn($order->items, 'id');
        $this->assertSame([2], $orderItemIds);
    }

    public function testAlias(): void
    {
        $order = new Order($this->db);

        $query = $order->find();
        $this->assertNull($query->getFrom());

        $query = $order->find()->alias('o');
        $this->assertEquals(['o' => $order->tableName()], $query->getFrom());

        $query = $order->find()->alias('o')->alias('ord');
        $this->assertEquals(['ord' => $order->tableName()], $query->getFrom());

        $query = $order->find()->from(['users', 'o' => $order->tableName()])->alias('ord');
        $this->assertEquals(['users', 'ord' => $order->tableName()], $query->getFrom());
    }

    public function testInverseOf(): void
    {
        $customerInstance = new Customer($this->db);
        $orderInstance = new Order($this->db);

        /** eager loading: find one and all */
        $customer = $customerInstance->find()->with('orders2')->where(['id' => 1])->one();
        $this->assertSame($customer->orders2[0]->customer2, $customer);

        $customers = $customerInstance->find()->with('orders2')->where(['id' => [1, 3]])->all();
        $this->assertEmpty($customers[1]->orders2);
        $this->assertSame($customers[0]->orders2[0]->customer2, $customers[0]);

        /** lazy loading */
        $customer = $customerInstance->findOne(2);
        $orders = $customer->orders2;
        $this->assertCount(2, $orders);
        $this->assertSame($customer->orders2[0]->customer2, $customer);
        $this->assertSame($customer->orders2[1]->customer2, $customer);

        /** ad-hoc lazy loading */
        $customer = $customerInstance->findOne(2);
        $orders = $customer->getOrders2()->all();
        $this->assertCount(2, $orders);
        $this->assertSame($orders[0]->customer2, $customer);
        $this->assertSame($orders[1]->customer2, $customer);
        $this->assertTrue(
            $orders[0]->isRelationPopulated('customer2'),
            'inverse relation did not populate the relation'
        );
        $this->assertTrue(
            $orders[1]->isRelationPopulated('customer2'),
            'inverse relation did not populate the relation'
        );

        /** the other way around */
        $customer = $customerInstance->find()->with('orders2')->where(['id' => 1])->asArray()->one();
        $this->assertSame($customer['orders2'][0]['customer2']['id'], $customer['id']);

        $customers = $customerInstance->find()->with('orders2')->where(['id' => [1, 3]])->asArray()->all();
        $this->assertSame($customer['orders2'][0]['customer2']['id'], $customers[0]['id']);
        $this->assertEmpty($customers[1]['orders2']);

        $orders = $orderInstance->find()->with('customer2')->where(['id' => 1])->all();
        $this->assertSame($orders[0]->customer2->orders2, [$orders[0]]);

        $order = $orderInstance->find()->with('customer2')->where(['id' => 1])->one();
        $this->assertSame($order->customer2->orders2, [$order]);

        $orders = $orderInstance->find()->with('customer2')->where(['id' => 1])->asArray()->all();
        $this->assertSame($orders[0]['customer2']['orders2'][0]['id'], $orders[0]['id']);

        $order = $orderInstance->find()->with('customer2')->where(['id' => 1])->asArray()->one();
        $this->assertSame($order['customer2']['orders2'][0]['id'], $orders[0]['id']);

        $orders = $orderInstance->find()->with('customer2')->where(['id' => [1, 3]])->all();
        $this->assertSame($orders[0]->customer2->orders2, [$orders[0]]);
        $this->assertSame($orders[1]->customer2->orders2, [$orders[1]]);

        $orders = $orderInstance->find()->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->all();
        $this->assertSame($orders[0]->customer2->orders2, $orders);
        $this->assertSame($orders[1]->customer2->orders2, $orders);

        $orders = $orderInstance->find()->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->asArray()->all();
        $this->assertSame($orders[0]['customer2']['orders2'][0]['id'], $orders[0]['id']);
        $this->assertSame($orders[0]['customer2']['orders2'][1]['id'], $orders[1]['id']);
        $this->assertSame($orders[1]['customer2']['orders2'][0]['id'], $orders[0]['id']);
        $this->assertSame($orders[1]['customer2']['orders2'][1]['id'], $orders[1]['id']);
    }

    public function testInverseOfDynamic(): void
    {
        $customerInstance = new Customer($this->db);

        $customer = $customerInstance->findOne(1);

        /** request the inverseOf relation without explicitly (eagerly) loading it */
        $orders2 = $customer->getOrders2()->all();
        $this->assertSame($customer, $orders2[0]->customer2);

        $orders2 = $customer->getOrders2()->one();
        $this->assertSame($customer, $orders2->customer2);

        /**
         * request the inverseOf relation while also explicitly eager loading it (while possible, this is of course
         * redundant)
         */
        $orders2 = $customer->getOrders2()->with('customer2')->all();
        $this->assertSame($customer, $orders2[0]->customer2);

        $orders2 = $customer->getOrders2()->with('customer2')->one();
        $this->assertSame($customer, $orders2->customer2);

        /** request the inverseOf relation as array */
        $orders2 = $customer->getOrders2()->asArray()->all();
        $this->assertEquals($customer['id'], $orders2[0]['customer2']['id']);

        $orders2 = $customer->getOrders2()->asArray()->one();
        $this->assertEquals($customer['id'], $orders2['customer2']['id']);
    }

    public function testDefaultValues(): void
    {
        $arClass = new Type($this->db);

        $arClass->loadDefaultValues();

        $this->assertEquals(1, $arClass->int_col2);
        $this->assertEquals('something', $arClass->char_col2);
        $this->assertEquals(1.23, $arClass->float_col2);
        $this->assertEquals(33.22, $arClass->numeric_col);
        $this->assertEquals(true, $arClass->bool_col2);
        $this->assertEquals('2002-01-01 00:00:00', $arClass->time);

        $arClass = new Type($this->db);
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues();
        $this->assertEquals('not something', $arClass->char_col2);

        $arClass = new Type($this->db);
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues(false);
        $this->assertEquals('something', $arClass->char_col2);
    }

    public function testUnlinkAllViaTable(): void
    {
        $this->loadFixture($this->db);

        /** @var $order ActiveRecordInterface */
        $orderInstance = new Order($this->db);

        /** @var $orderItem ActiveRecordInterface */
        $orderItemInstance = new OrderItem($this->db);

        /** @var $item ActiveRecordInterface */
        $itemInstance = new Item($this->db);

        /** @var $orderItemsWithNullFK ActiveRecordInterface */
        $orderItemsWithNullFKInstance = new OrderItemWithNullFK($this->db);

        /**
         * via table with delete.
         *
         * @var $order  Order
         */
        $order = $orderInstance->findOne(1);
        $this->assertCount(2, $order->booksViaTable);

        $orderItemCount = $orderItemInstance->find()->count();
        $this->assertEquals(5, $itemInstance->find()->count());

        $order->unlinkAll('booksViaTable', true);
        $this->assertEquals(5, $itemInstance->find()->count());
        $this->assertEquals($orderItemCount - 2, $orderItemInstance->find()->count());
        $this->assertCount(0, $order->booksViaTable);

        /** via table without delete */
        $this->assertCount(2, $order->booksWithNullFKViaTable);

        $orderItemCount = $orderItemsWithNullFKInstance->find()->count();
        $this->assertEquals(5, $itemInstance->find()->count());

        $order->unlinkAll('booksWithNullFKViaTable', false);
        $this->assertCount(0, $order->booksWithNullFKViaTable);
        $this->assertEquals(2, $orderItemsWithNullFKInstance->find()->where(
            ['AND', ['item_id' => [1, 2]], ['order_id' => null]]
        )->count());
        $this->assertEquals($orderItemCount, $orderItemsWithNullFKInstance->find()->count());
        $this->assertEquals(5, $itemInstance->find()->count());
    }

    public function testCastValues(): void
    {
        $arClass = new Type($this->db);

        $arClass->int_col = 123;
        $arClass->int_col2 = 456;
        $arClass->smallint_col = 42;
        $arClass->char_col = '1337';
        $arClass->char_col2 = 'test';
        $arClass->char_col3 = 'test123';
        $arClass->float_col = 3.742;
        $arClass->float_col2 = 42.1337;
        $arClass->bool_col = true;
        $arClass->bool_col2 = false;

        $arClass->save();

        /** @var $model Type */
        $arClass =  new Type($this->db);
        $query = $arClass->find()->one();

        $this->assertSame(123, $query->int_col);
        $this->assertSame(456, $query->int_col2);
        $this->assertSame(42, $query->smallint_col);
        $this->assertSame('1337', trim($query->char_col));
        $this->assertSame('test', $query->char_col2);
        $this->assertSame('test123', $query->char_col3);
    }

    public function testIssues(): void
    {
        $this->loadFixture($this->db);

        $category = new Category($this->db);
        $order = new Order($this->db);

        /** {@see https://github.com/yiisoft/yii2/issues/4938} */
        $category = $category->findOne(2);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals(3, $category->getItems()->count());
        $this->assertEquals(1, $category->getLimitedItems()->count());
        $this->assertEquals(1, $category->getLimitedItems()->distinct(true)->count());

        /** {@see https://github.com/yiisoft/yii2/issues/3197} */
        $orders = $order->find()->with('orderItems')->orderBy('id')->all();

        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->orderItems);
        $this->assertCount(3, $orders[1]->orderItems);
        $this->assertCount(1, $orders[2]->orderItems);

        $orders = $order->find()->with(
            [
                'orderItems' => static function ($q) {
                    $q->indexBy('item_id');
                },
            ]
        )->orderBy('id')->all();

        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->orderItems);
        $this->assertCount(3, $orders[1]->orderItems);
        $this->assertCount(1, $orders[2]->orderItems);

        /** {@see https://github.com/yiisoft/yii2/issues/8149} */
        $arClass = new Customer($this->db);

        $arClass->name = 'test';
        $arClass->email = 'test';
        $arClass->save();

        $arClass->updateCounters(['status' => 1]);
        $this->assertEquals(1, $arClass->status);
    }

    public function testPopulateRecordCallWhenQueryingOnParentClass(): void
    {
        $cat = new Cat($this->db);
        $cat->save();

        $dog = new Dog($this->db);
        $dog->save();

        $animal = new Animal($this->db);

        $animals = $animal->find()->where(['type' => Dog::class])->one();
        $this->assertEquals('bark', $animals->getDoes());

        $animals = $animal->find()->where(['type' => Cat::class])->one();
        $this->assertEquals('meow', $animals->getDoes());
    }

    public function testSaveEmpty(): void
    {
        $record = new NullValues($this->db);

        $this->assertTrue($record->save());
        $this->assertEquals(1, $record->id);
    }

    public function testOptimisticLock(): void
    {
        $document = new Document($this->db);

        /** @var $record Document */
        $record = $document->findOne(1);

        $record->content = 'New Content';
        $record->save();
        $this->assertEquals(1, $record->version);

        $record = $document->findOne(1);

        $record->content = 'Rewrite attempt content';
        $record->version = 0;
        $this->expectException(StaleObjectException::class);
        $record->save();
    }

    public function testPopulateWithoutPk(): void
    {
        $customer = new Customer($this->db);
        $orderItem = new OrderItem($this->db);

        /** tests with single pk asArray */
        $aggregation = $customer->find()
            ->select(['{{customer}}.[[status]]', 'SUM({{order}}.[[total]]) AS [[sumtotal]]'])
            ->joinWith('ordersPlain', false)
            ->groupBy('{{customer}}.[[status]]')
            ->orderBy('status')
            ->asArray()
            ->all();

        $expected = [
            [
                'status' => 1,
                'sumtotal' => 183,
            ],
            [
                'status' => 2,
                'sumtotal' => 0,
            ],
        ];

        $this->assertEquals($expected, $aggregation);

        /** tests with single pk with Models */
        $aggregation = $customer->find()
            ->select(['{{customer}}.[[status]]', 'SUM({{order}}.[[total]]) AS [[sumTotal]]'])
            ->joinWith('ordersPlain', false)
            ->groupBy('{{customer}}.[[status]]')
            ->orderBy('status')
            ->all();

        $this->assertCount(2, $aggregation);
        $this->assertContainsOnlyInstancesOf(Customer::class, $aggregation);

        foreach ($aggregation as $item) {
            if ($item->status === 1) {
                $this->assertEquals(183, $item->sumTotal);
            } elseif ($item->status === 2) {
                $this->assertEquals(0, $item->sumTotal);
            }
        }

        /** tests with composite pk asArray */
        $aggregation = $orderItem->find()
            ->select(['[[order_id]]', 'SUM([[subtotal]]) AS [[subtotal]]'])
            ->joinWith('order', false)
            ->groupBy('[[order_id]]')
            ->orderBy('[[order_id]]')
            ->asArray()
            ->all();

        $expected = [
            [
                'order_id' => 1,
                'subtotal' => 70,
            ],
            [
                'order_id' => 2,
                'subtotal' => 33,
            ],
            [
                'order_id' => 3,
                'subtotal' => 40,
            ],
        ];
        $this->assertEquals($expected, $aggregation);

        /** tests with composite pk with Models */
        $aggregation = $orderItem->find()
            ->select(['[[order_id]]', 'SUM([[subtotal]]) AS [[subtotal]]'])
            ->joinWith('order', false)
            ->groupBy('[[order_id]]')
            ->orderBy('[[order_id]]')
            ->all();

        $this->assertCount(3, $aggregation);
        $this->assertContainsOnlyInstancesOf(OrderItem::class, $aggregation);

        foreach ($aggregation as $item) {
            if ($item->order_id === 1) {
                $this->assertEquals(70, $item->subtotal);
            } elseif ($item->order_id === 2) {
                $this->assertEquals(33, $item->subtotal);
            } elseif ($item->order_id === 3) {
                $this->assertEquals(40, $item->subtotal);
            }
        }
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/9006}
     */
    public function testBit(): void
    {
        $bitValue = new BitValues($this->db);

        $falseBit = $bitValue->findOne(1);
        $this->assertEquals(false, $falseBit->val);

        $trueBit = $bitValue->findOne(2);
        $this->assertEquals(true, $trueBit->val);
    }

    public function testLinkWhenRelationIsIndexed2(): void
    {
        $orderInstance = new Order($this->db);
        $order = $orderInstance->find()->with('orderItems2')->where(['id' => 1])->one();

        $orderItem = new OrderItem($this->db);

        $orderItem->order_id = $order->id;
        $orderItem->item_id = 3;
        $orderItem->quantity = 1;
        $orderItem->subtotal = 10.0;

        $order->link('orderItems2', $orderItem);
        $this->assertTrue(isset($order->orderItems2['3']));
    }

    public function testUpdateAttributes(): void
    {
        $this->loadFixture($this->db);

        $orderInstance = new Order($this->db);

        $order = $orderInstance->findOne(1);

        $newTotal = 978;
        $this->assertSame(1, $order->updateAttributes(['total' => $newTotal]));
        $this->assertEquals($newTotal, $order->total);

        $order = $orderInstance->findOne(1);

        $this->assertEquals($newTotal, $order->total);

        /** @see https://github.com/yiisoft/yii2/issues/12143 */
        $newOrder = new Order($this->db);

        $this->assertTrue($newOrder->getIsNewRecord());

        $newTotal = 200;
        $this->assertSame(0, $newOrder->updateAttributes(['total' => $newTotal]));
        $this->assertTrue($newOrder->getIsNewRecord());
        $this->assertEquals($newTotal, $newOrder->total);
    }

    public function testEmulateExecution(): void
    {
        $customer = new Customer($this->db);

        $this->assertGreaterThan(0, $customer->find()->count());

        $rows = $customer->find()->emulateExecution()->all();
        $this->assertSame([], $rows);

        $row = $customer->find()->emulateExecution()->one();
        $this->assertNull($row);

        $exists = $customer->find()->emulateExecution()->exists();
        $this->assertFalse($exists);

        $count = $customer->find()->emulateExecution()->count();
        $this->assertSame(0, $count);

        $sum = $customer->find()->emulateExecution()->sum('id');
        $this->assertSame(0, $sum);

        $sum = $customer->find()->emulateExecution()->average('id');
        $this->assertSame(0, $sum);

        $max = $customer->find()->emulateExecution()->max('id');
        $this->assertNull($max);

        $min = $customer->find()->emulateExecution()->min('id');
        $this->assertNull($min);

        $scalar = $customer->find()->select(['id'])->emulateExecution()->scalar();
        $this->assertNull($scalar);

        $column = $customer->find()->select(['id'])->emulateExecution()->column();
        $this->assertSame([], $column);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/12213}
     */
    public function testUnlinkAllOnCondition(): void
    {
        /** @var Category $categoryClass */
        $categoryClass = new Category($this->db);

        /** @var Item $itemClass */
        $itemClass = new Item($this->db);

        /** Ensure there are three items with category_id = 2 in the Items table */
        $itemsCount = $itemClass->find()->where(['category_id' => 2])->count();
        $this->assertEquals(3, $itemsCount);

        $categoryQuery = $categoryClass->find()->with('limitedItems')->where(['id' => 2]);

        /**
         * Ensure that limitedItems relation returns only one item (category_id = 2 and id in (1,2,3))
         */
        $category = $categoryQuery->one();
        $this->assertCount(1, $category->limitedItems);

        /** Unlink all items in the limitedItems relation */
        $category->unlinkAll('limitedItems', true);

        /** Make sure that only one item was unlinked */
        $itemsCount = $itemClass->find()->where(['category_id' => 2])->count();
        $this->assertEquals(2, $itemsCount);

        /** Call $categoryQuery again to ensure no items were found */
        $this->assertCount(0, $categoryQuery->one()->limitedItems);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/12213}
     */
    public function testUnlinkAllOnConditionViaTable(): void
    {
        $this->loadFixture($this->db);

        /** @var Order $orderClass */
        $orderClass = new Order($this->db);

        /** @var Item $itemClass */
        $itemClass = new Item($this->db);

        /** Ensure there are three items with category_id = 2 in the Items table */
        $itemsCount = $itemClass->find()->where(['category_id' => 2])->count();
        $this->assertEquals(3, $itemsCount);

        $orderQuery = $orderClass->find()->with('limitedItems')->where(['id' => 2]);

        /**
         * Ensure that limitedItems relation returns only one item (category_id = 2 and id in (4, 5)).
         */
        $category = $orderQuery->one();
        $this->assertCount(2, $category->limitedItems);

        /** Unlink all items in the limitedItems relation */
        $category->unlinkAll('limitedItems', true);

        /** Call $orderQuery again to ensure that links are removed */
        $this->assertCount(0, $orderQuery->one()->limitedItems);

        /** Make sure that only links were removed, the items were not removed */
        $this->assertEquals(3, $itemClass->find()->where(['category_id' => 2])->count());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/pull/13891}
     */
    public function testIndexByAfterLoadingRelations(): void
    {
        $orderClass = new Order($this->db);

        $orderClass->find()->with('customer')->indexBy(function (Order $order) {
            $this->assertTrue($order->isRelationPopulated('customer'));
            $this->assertNotEmpty($order->customer->id);

            return $order->customer->id;
        })->all();

        $orders = $orderClass->find()->with('customer')->indexBy('customer.id')->all();

        foreach ($orders as $customer_id => $order) {
            $this->assertEquals($customer_id, $order->customer_id);
        }
    }

    /**
     * Verify that {{}} are not going to be replaced in parameters.
     */
    public function testNoTablenameReplacement(): void
    {
        $customer = new Customer($this->db);

        $customer->name = 'Some {{weird}} name';
        $customer->email = 'test@example.com';
        $customer->address = 'Some {{%weird}} address';
        $customer->insert();
        $customer->refresh();

        $this->assertEquals('Some {{weird}} name', $customer->name);
        $this->assertEquals('Some {{%weird}} address', $customer->address);

        $customer->name = 'Some {{updated}} name';
        $customer->address = 'Some {{%updated}} address';
        $customer->update();

        $this->assertEquals('Some {{updated}} name', $customer->name);
        $this->assertEquals('Some {{%updated}} address', $customer->address);
    }

    /**
     * Ensure no ambiguous column error occurs if ActiveQuery adds a JOIN.
     *
     * {@see https://github.com/yiisoft/yii2/issues/13757}
     */
    public function testAmbiguousColumnFindOne(): void
    {
        $customerQuery = new CustomerQuery(Customer::class, $this->db);

        $customerQuery->joinWithProfile = true;

        $customer = new Customer($this->db);

        $arClass = $customer->findOne(1);

        $this->assertTrue($arClass->refresh());

        $customerQuery->joinWithProfile = false;
    }

    public function testFindOneByColumnName(): void
    {
        $customer = new Customer($this->db);
        $customerQuery = new CustomerQuery(Customer::class, $this->db);

        $arClass = $customer->findOne(['id' => 1]);
        $this->assertEquals(1, $arClass->id);

        $customerQuery->joinWithProfile = true;

        $arClass = $customer->findOne(['customer.id' => 1]);
        $this->assertEquals(1, $arClass->id);

        $customerQuery->joinWithProfile = false;
    }

    public function filterTableNamesFromAliasesProvider(): array
    {
        return [
            'table name as string'         => ['customer', []],
            'table name as array'          => [['customer'], []],
            'table names'                  => [['customer', 'order'], []],
            'table name and a table alias' => [['customer', 'ord' => 'order'], ['ord']],
            'table alias'                  => [['csr' => 'customer'], ['csr']],
            'table aliases'                => [['csr' => 'customer', 'ord' => 'order'], ['csr', 'ord']],
        ];
    }

    /**
     * @dataProvider filterTableNamesFromAliasesProvider
     *
     * @param array|string$fromParams
     * @param $expectedAliases
     */
    public function testFilterTableNamesFromAliases($fromParams, array $expectedAliases): void
    {
        $customer = new Customer($this->db);

        $query = $customer->find()->from($fromParams);

        $aliases = $this->invokeMethod(new Customer($this->db), 'filterValidAliases', [$query]);

        $this->assertEquals($expectedAliases, $aliases);
    }

    public function legalValuesForFindByCondition(): array
    {
        return [
            [Customer::class, ['id' => 1]],
            [Customer::class, ['customer.id' => 1]],
            [Customer::class, ['[[id]]' => 1]],
            [Customer::class, ['{{customer}}.[[id]]' => 1]],
            [Customer::class, ['{{%customer}}.[[id]]' => 1]],
            [CustomerWithAlias::class, ['id' => 1]],
            [CustomerWithAlias::class, ['customer.id' => 1]],
            [CustomerWithAlias::class, ['[[id]]' => 1]],
            [CustomerWithAlias::class, ['{{customer}}.[[id]]' => 1]],
            [CustomerWithAlias::class, ['{{%customer}}.[[id]]' => 1]],
            [CustomerWithAlias::class, ['csr.id' => 1]],
            [CustomerWithAlias::class, ['{{csr}}.[[id]]' => 1]],
        ];
    }

    /**
     * @dataProvider legalValuesForFindByCondition
     *
     * @param string $modelClassName
     * @param array $validFilter
     *
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function testLegalValuesForFindByCondition(string $modelClassName, array $validFilter): void
    {
        /** @var Query $query */
        $query = $this->invokeMethod(new $modelClassName($this->db), 'findByCondition', [$validFilter]);

        $this->db->getQueryBuilder()->build($query);

        $this->assertTrue(true);
    }

    public function illegalValuesForFindByCondition(): array
    {
        return [
            [Customer::class, ['id' => ['`id`=`id` and 1' => 1]]],
            [Customer::class, ['id' => [
                'legal' => 1,
                '`id`=`id` and 1' => 1,
            ]]],
            [Customer::class, ['id' => [
                'nested_illegal' => [
                    'false or 1=' => 1
                ]
            ]]],
            [Customer::class, [['true--' => 1]]],

            [CustomerWithAlias::class, ['csr.id' => ['`csr`.`id`=`csr`.`id` and 1' => 1]]],
            [CustomerWithAlias::class, ['csr.id' => [
                'legal' => 1,
                '`csr`.`id`=`csr`.`id` and 1' => 1,
            ]]],
            [CustomerWithAlias::class, ['csr.id' => [
                'nested_illegal' => [
                    'false or 1=' => 1
                ]
            ]]],
            [CustomerWithAlias::class, [['true--' => 1]]],
        ];
    }

    /**
     * @dataProvider illegalValuesForFindByCondition
     *
     * @param string $modelClassName
     * @param array $filterWithInjection
     */
    public function testValueEscapingInFindByCondition(string $modelClassName, array $filterWithInjection): void
    {
        if (PHP_VERSION_ID >= 80000) {
            $this->markTestSkipped('The test should be fixed in PHP 8.0.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/^Key "(.+)?" is not a column name and can not be used as a filter$/'
        );

        /** @var Query $query */
        $query = $this->invokeMethod(new $modelClassName($this->db), 'findByCondition', $filterWithInjection);

        $this->db->getQueryBuilder()->build($query);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/5786}
     */
    public function testFindWithConstructors(): void
    {
        $orderWithConstructor = new ActiveQuery(OrderWithConstructor::class, $this->db);

        $orders = $orderWithConstructor->with(['customer.profile', 'orderItems'])->orderBy('id')->all();

        $this->assertCount(3, $orders);
        $order = $orders[0];
        $this->assertEquals(1, $order->id);

        $this->assertNotNull($order->customer);
        $this->assertInstanceOf(CustomerWithConstructor::class, $order->customer);
        $this->assertEquals(1, $order->customer->id);

        $this->assertNotNull($order->customer->profile);
        $this->assertInstanceOf(ProfileWithConstructor::class, $order->customer->profile);
        $this->assertEquals(1, $order->customer->profile->id);

        $this->assertNotNull($order->customerJoinedWithProfile);
        $customerWithProfile = $order->customerJoinedWithProfile;
        $this->assertInstanceOf(CustomerWithConstructor::class, $customerWithProfile);
        $this->assertEquals(1, $customerWithProfile->id);

        $this->assertNotNull($customerProfile = $customerWithProfile->profile);
        $this->assertInstanceOf(ProfileWithConstructor::class, $customerProfile);
        $this->assertEquals(1, $customerProfile->id);

        $this->assertCount(2, $order->orderItems);

        $item = $order->orderItems[0];
        $this->assertInstanceOf(OrderItemWithConstructor::class, $item);

        $this->assertEquals(1, $item->item_id);

        /** {@see https://github.com/yiisoft/yii2/issues/15540} */
        $orders = $orderWithConstructor->find()
            ->with(['customer.profile', 'orderItems'])
            ->orderBy('id')
            ->asArray(true)
            ->all();
        $this->assertCount(3, $orders);
    }

    public function testCustomARRelation(): void
    {
        $this->loadFixture($this->db);

        $orderItem = new OrderItem($this->db);

        $orderItem = $orderItem->findOne(1);

        $this->assertInstanceOf(Order::class, $orderItem->custom);
    }


    public function testRefreshQuerySetAliasFindRecord(): void
    {
        $customer = new CustomerWithAlias($this->db);

        $customer->id = 1;
        $customer->refresh();

        $this->assertEquals(1, $customer->id);
    }

    public function testResetNotSavedRelation(): void
    {
        $order = new Order($this->db);

        $order->customer_id = 1;
        $order->created_at = 1325502201;
        $order->total = 0;

        $orderItem = new OrderItem($this->db);

        $order->orderItems;

        $order->populateRelation('orderItems', [$orderItem]);

        $order->save();

        $this->assertCount(1, $order->orderItems);
    }

    public function testIssetException(): void
    {
        $cat = new Cat($this->db);

        $this->assertFalse(isset($cat->exception));
    }

    public function testIssetThrowable(): void
    {
        $cat = new Cat($this->db);

        $this->assertFalse(isset($cat->throwable));
    }
}
