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
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\StaleObjectException;

use function ucfirst;

abstract class ActiveRecordTest extends TestCase
{
    use ActiveRecordTestTrait;

    public function testCustomColumns(): void
    {
        $this->loadFixture(Customer::getConnection());

        /** find custom column */
        if ($this->driverName === 'oci') {
            $customer = Customer::find()->select(['{{customer}}.*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->one();
        } else {
            $customer = Customer::find()->select(['*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->one();
        }

        $this->assertEquals(3, $customer->id);
        $this->assertEquals(4, $customer->status2);
    }

    public function testCallFind(): void
    {
        /** find count, sum, average, min, max, scalar */
        $this->assertEquals(3, Customer::find()->count());
        $this->assertEquals(2, Customer::find()->where('[[id]]=1 OR [[id]]=2')->count());
        $this->assertEquals(6, Customer::find()->sum('[[id]]'));
        $this->assertEquals(2, Customer::find()->average('[[id]]'));
        $this->assertEquals(1, Customer::find()->min('[[id]]'));
        $this->assertEquals(3, Customer::find()->max('[[id]]'));
        $this->assertEquals(3, Customer::find()->select('COUNT(*)')->scalar());
    }

    public function testFindAll(): void
    {
        $this->assertCount(1, Customer::findAll(3));
        $this->assertCount(1, Customer::findAll(['id' => 1]));
        $this->assertCount(3, Customer::findAll(['id' => [1, 2, 3]]));
    }

    public function testFindScalar(): void
    {
        /** query scalar */
        $customerName = Customer::find()->where(['[[id]]' => 2])->select('[[name]]')->scalar();

        $this->assertEquals('user2', $customerName);
    }

    public function testFindExists(): void
    {
        $this->assertTrue(Customer::find()->where(['[[id]]' => 2])->exists());
        $this->assertTrue(Customer::find()->where(['[[id]]' => 2])->select('[[name]]')->exists());

        $this->assertFalse(Customer::find()->where(['[[id]]' => 42])->exists());
        $this->assertFalse(Customer::find()->where(['[[id]]' => 42])->select('[[name]]')->exists());
    }

    public function testFindColumn(): void
    {
        $this->assertEquals(
            ['user1', 'user2', 'user3'],
            Customer::find()->select('[[name]]')->column()
        );

        $this->assertEquals(
            ['user3', 'user2', 'user1'],
            Customer::find()->orderBy(['[[name]]' => SORT_DESC])->select('[[name]]')->column()
        );
    }

    public function testFindBySql(): void
    {
        /** find one */
        $customer = Customer::findBySql('SELECT * FROM {{customer}} ORDER BY [[id]] DESC')->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user3', $customer->name);

        /** find all */
        $customers = Customer::findBySql('SELECT * FROM {{customer}}')->all();
        $this->assertCount(3, $customers);

        /** find with parameter binding */
        $customer = Customer::findBySql('SELECT * FROM {{customer}} WHERE [[id]]=:id', [':id' => 2])->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->name);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/8593}
     */
    public function testCountWithFindBySql(): void
    {
        $query = Customer::findBySql('SELECT * FROM {{customer}}');
        $this->assertEquals(3, $query->count());

        $query = Customer::findBySql('SELECT * FROM {{customer}} WHERE  [[id]]=:id', [':id' => 2]);
        $this->assertEquals(1, $query->count());
    }

    public function testFindLazyViaTable(): void
    {
        $order = Order::findOne(2);

        $this->assertCount(0, $order->books);
        $this->assertEquals(2, $order->id);

        $order = Order::find()->where(['id' => 1])->asArray()->one();
        $this->assertIsArray($order);
    }

    public function testFindEagerViaTable(): void
    {
        $orders = Order::find()->with('books')->orderBy('id')->all();
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
        $orders = Order::find()->with('books')->orderBy('id')->asArray()->all();
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
        /** @var $customer Customer */
        $customer = Customer::findOne(1);
        $this->assertNotNull($customer);

        $items = $customer->orderItems;

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
        /** @var $category Category */
        $category = Category::findOne(1);
        $this->assertNotNull($category);

        $orders = $category->orders;
        $this->assertCount(2, $orders);
        $this->assertInstanceOf(Order::class, $orders[0]);
        $this->assertInstanceOf(Order::class, $orders[1]);

        $ids = [$orders[0]->id, $orders[1]->id];
        sort($ids);
        $this->assertEquals([1, 3], $ids);

        $category = Category::findOne(2);
        $this->assertNotNull($category);

        $orders = $category->orders;
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertInstanceOf(Order::class, $orders[0]);
    }

    public function testStoreNull(): void
    {
        $record = new NullValues();

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
        $record = new NullValues();

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
        $this->assertTrue(Customer::isPrimaryKey(['id']));
        $this->assertFalse(Customer::isPrimaryKey([]));
        $this->assertFalse(Customer::isPrimaryKey(['id', 'name']));
        $this->assertFalse(Customer::isPrimaryKey(['name']));
        $this->assertFalse(Customer::isPrimaryKey(['name', 'email']));

        $this->assertTrue(OrderItem::isPrimaryKey(['order_id', 'item_id']));
        $this->assertFalse(OrderItem::isPrimaryKey([]));
        $this->assertFalse(OrderItem::isPrimaryKey(['order_id']));
        $this->assertFalse(OrderItem::isPrimaryKey(['item_id']));
        $this->assertFalse(OrderItem::isPrimaryKey(['quantity']));
        $this->assertFalse(OrderItem::isPrimaryKey(['quantity', 'subtotal']));
        $this->assertFalse(OrderItem::isPrimaryKey(['order_id', 'item_id', 'quantity']));
    }

    public function testJoinWith(): void
    {
        /** left join and eager loading */
        $orders = Order::find()->joinWith('customer')->orderBy('customer.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** inner join filtering and eager loading */
        $orders = Order::find()->innerJoinWith([
            'customer' => function ($query) {
                $query->where('{{customer}}.[[id]]=2');
            },
        ])->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering, eager loading, conditions on both primary and relation */
        $orders = Order::find()->innerJoinWith([
            'customer' => function ($query) {
                $query->where(['customer.id' => 2]);
            },
        ])->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading */
        $orders = Order::find()->innerJoinWith([
            'customer' => static function ($query) {
                $query->where('{{customer}}.[[id]]=2');
            },
        ], false)->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading, conditions on both primary and relation */
        $orders = Order::find()->innerJoinWith([
            'customer' => static function ($query) {
                $query->where(['customer.id' => 2]);
            },
        ], false)->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));

        /** join with via-relation */
        $orders = Order::find()->innerJoinWith('books')->orderBy('order.id')->all();
        $this->assertCount(2, $orders);
        $this->assertCount(2, $orders[0]->books);
        $this->assertCount(1, $orders[1]->books);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));

        /** join with sub-relation */
        $orders = Order::find()->innerJoinWith([
            'items' => function ($q) {
                $q->orderBy('item.id');
            },
            'items.category' => function ($q) {
                $q->where('{{category}}.[[id]] = 2');
            },
        ])->orderBy('order.id')->all();
        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));

        /** join with table alias */
        $orders = Order::find()->joinWith([
            'customer' => function ($q) {
                $q->from('customer c');
            },
        ])->orderBy('c.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** join with table alias */
        $orders = Order::find()->joinWith('customer as c')->orderBy('c.id DESC, order.id')->all();
        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** join with table alias sub-relation */
        $orders = Order::find()->innerJoinWith([
            'items as t' => function ($q) {
                $q->orderBy('t.id');
            },
            'items.category as c' => function ($q) {
                $q->where('{{c}}.[[id]] = 2');
            },
        ])->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));


        /** join with ON condition */
        $orders = Order::find()->joinWith('books2')->orderBy('order.id')->all();
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
        $order = Order::findOne(1);
        $this->assertCount(2, $order->books2);

        $order = Order::findOne(2);
        $this->assertCount(0, $order->books2);

        $order = Order::findOne(3);
        $this->assertCount(1, $order->books2);

        /** eager loading with ON condition */
        $orders = Order::find()->with('books2')->all();
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
        $query = Order::find()->joinWith('customer');

        $count = $query->count();
        $this->assertEquals(3, $count);

        $orders = $query->all();
        $this->assertCount(3, $orders);

        /** {@see https://github.com/yiisoft/yii2/issues/2880} */
        $query = Order::findOne(1);

        $customer = $query->getCustomer()
            ->joinWith([
                'orders' => static function ($q) {
                    $q->orderBy([]);
                },
            ])
            ->one();
        $this->assertEquals(1, $customer->id);

        $order = Order::find()->joinWith([
            'items' => static function ($q) {
                $q->from(['items' => 'item'])
                    ->orderBy('items.id');
            },
        ])->orderBy('order.id')->one();

        /** join with sub-relation called inside Closure */
        $orders = Order::find()
            ->joinWith([
                'items' => static function ($q) {
                    $q->orderBy('item.id');
                    $q->joinWith([
                        'category' => static function ($q) {
                            $q->where('{{category}}.[[id]] = 2');
                        },
                    ]);
                },
            ])
            ->orderBy('order.id')
            ->all();
        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
    }

    public function testJoinWithAndScope(): void
    {
        /**  hasOne inner join */
        $customers = Customer::find()->active()->innerJoinWith('profile')->orderBy('customer.id')->all();
        $this->assertCount(1, $customers);
        $this->assertEquals(1, $customers[0]->id);
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));

        /** hasOne outer join */
        $customers = Customer::find()->active()->joinWith('profile')->orderBy('customer.id')->all();

        $this->assertCount(2, $customers);
        $this->assertEquals(1, $customers[0]->id);
        $this->assertEquals(2, $customers[1]->id);
        $this->assertInstanceOf(Profile::class, $customers[0]->profile);
        $this->assertNull($customers[1]->profile);
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));
        $this->assertTrue($customers[1]->isRelationPopulated('profile'));

        /** hasMany */
        $customers = Customer::find()->active()->joinWith([
            'orders' => static function ($q) {
                $q->orderBy('order.id');
            },
        ])->orderBy('customer.id DESC, order.id')->all();
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
        Order::getConnection()->getQueryBuilder()->setSeparator("\n");

        $rows = Order::find()->joinWith('itemsInOrder1')->joinWith([
            'items' => static function ($q) {
                $q->orderBy('item.id');
            },
        ])->all();
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
        /** left join and eager loading */
        $query = Order::find()->joinWith(['customer c']);

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
        $query = Order::find()->innerJoinWith(['customer c']);

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
        $query = Order::find()->innerJoinWith(['customer c'], false);

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
        $query = Order::find()->innerJoinWith(['books b']);

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
        $query = Order::find()->innerJoinWith([
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
            },
        ]);

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

            $orders = Order::find()->joinWith(["$relationName b"])->orderBy('order.id')->all();

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

            $orders = Order::find()->joinWith([(string)$relationName])->orderBy('order.id')->all();

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

        /**
         * join with count and query
         *
         * @var $query ActiveQuery
         */
        $query = Order::find()->joinWith(['customer c']);

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

        /**
         * relational query
         *
         * @var $order Order
         */
        $order = Order::findOne(1);

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
        $orders = Order::find()->joinWith([
            'items' => static function ($q) use ($aliasMethod) {
                /* @var $q ActiveQuery */
                $q->orderBy('item.id');
                $q->joinWith(['category c']);

                if ($aliasMethod === 'explicit') {
                    $q->where('{{c}}.[[id]] = 2');
                } elseif ($aliasMethod === 'querysyntax') {
                    $q->where('{{@category}}.[[id]] = 2');
                } elseif ($aliasMethod === 'applyAlias') {
                    $q->where([$q->applyAlias('category', 'id') => 2]);
                }
            },
        ])->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
    }

    public function testJoinWithSameTable(): void
    {
        /**
         * join with the same table but different aliases alias is defined in the relation definition without eager
         * loading
         */
        $query = Order::find()
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
        $query = Order::find()
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
        $query = Order::find()
            ->joinWith([
                'itemsIndexed books' => static function ($q) {
                    $q->onCondition('books.category_id = 1');
                },
            ], false)
            ->joinWith([
                'itemsIndexed movies' => static function ($q) {
                    $q->onCondition('movies.category_id = 2');
                },
            ], false)
            ->where(['movies.name' => 'Toy Story']);

        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true)
        );
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, only for one relation as it would be overwritten otherwise. */
        $query = Order::find()
            ->joinWith([
                'itemsIndexed books' => static function ($q) {
                    $q->onCondition('books.category_id = 1');
                },
            ], false)
            ->joinWith([
                'itemsIndexed movies' => static function ($q) {
                    $q->onCondition('movies.category_id = 2');
                },
            ], true)
            ->where(['movies.name' => 'Toy Story']);

        $orders = $query->all();
        $this->assertCount(1, $orders, $query->createCommand()->getRawSql() . print_r($orders, true));
        $this->assertCount(3, $orders[0]->itemsIndexed);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, and the other relation */
        $query = Order::find()
            ->joinWith([
                'itemsIndexed books' => static function ($q) {
                    $q->onCondition('books.category_id = 1');
                },
            ], true)
            ->joinWith([
                'itemsIndexed movies' => static function ($q) {
                    $q->onCondition('movies.category_id = 2');
                },
            ], false)
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
        /** @var $orderItem OrderItem */
        $orderItem = OrderItem::findOne([1, 1]);

        $orderItemNoJoin = $orderItem->orderItemCompositeNoJoin;

        $this->assertInstanceOf(OrderItem::class, $orderItemNoJoin);

        $orderItemWithJoin = $orderItem->orderItemCompositeWithJoin;
        $this->assertInstanceOf(OrderItem::class, $orderItemWithJoin);
    }

    public function testFindSimpleRelationWithJoin(): void
    {
        /** @var $order Order */
        $order = Order::findOne(1);

        $customerNoJoin = $order->customer;
        $this->assertInstanceOf(Customer::class, $customerNoJoin);

        $customerWithJoin = $order->customerJoinedWithProfile;
        $this->assertInstanceOf(Customer::class, $customerWithJoin);

        $customerWithJoinIndexOrdered = $order->customerJoinedWithProfileIndexOrdered;
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
     * @throws InvalidConfigException
     */
    public function testRelationWhereParams(string $orderTableName, string $orderItemTableName): void
    {
        /** @var $order Order */
        $order = Order::findOne(1);
        $itemsSQL = $order->getOrderitems()->createCommand()->getRawSql();
        $expectedSQL = $this->replaceQuotes('SELECT * FROM [[order_item]] WHERE [[order_id]]=1');
        $this->assertEquals($expectedSQL, $itemsSQL);

        $order = Order::findOne(1);
        $itemsSQL = $order->getOrderItems()->joinWith('item')->createCommand()->getRawSql();
        $expectedSQL = $this->replaceQuotes(
            'SELECT [[order_item]].* FROM [[order_item]] LEFT JOIN [[item]] ON [[order_item]].[[item_id]] = [[item]].[[id]] WHERE [[order_item]].[[order_id]]=1'
        );
        $this->assertEquals($expectedSQL, $itemsSQL);

        Order::$tableName = null;
        OrderItem::$tableName = null;
    }

    public function testOutdatedRelationsAreResetForNewRecords(): void
    {
        $orderItem = new OrderItem();

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
        $orderItem = OrderItem::findOne(1);

        $this->assertEquals(1, $orderItem->order->id);
        $this->assertEquals(1, $orderItem->item->id);

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
        $dossier = Dossier::findOne([
            'department_id' => 1,
            'employee_id' => 1,
        ]);

        $this->assertEquals('John Doe', $dossier->employee->fullName);

        $dossier->department_id = 2;
        $this->assertEquals('Ann Smith', $dossier->employee->fullName);

        $dossier->employee_id = 2;
        $this->assertEquals('Will Smith', $dossier->employee->fullName);

        unset($dossier->employee_id);
        $this->assertNull($dossier->employee);

        $dossier = new Dossier();
        $this->assertNull($dossier->employee);

        $dossier->employee_id = 1;
        $dossier->department_id = 2;
        $this->assertEquals('Ann Smith', $dossier->employee->fullName);

        $dossier->employee_id = 2;
        $this->assertEquals('Will Smith', $dossier->employee->fullName);
    }

    public function testOutdatedViaTableRelationsAreReset(): void
    {
        $order = Order::findOne(1);
        $orderItemIds = ArrayHelper::getColumn($order->items, 'id');
        sort($orderItemIds);
        $this->assertSame([1, 2], $orderItemIds);

        $order->id = 2;
        sort($orderItemIds);
        $orderItemIds = ArrayHelper::getColumn($order->items, 'id');
        $this->assertSame([3, 4, 5], $orderItemIds);

        unset($order->id);
        $this->assertSame([], $order->items);

        $order = new Order();
        $this->assertSame([], $order->items);

        $order->id = 3;
        $orderItemIds = ArrayHelper::getColumn($order->items, 'id');
        $this->assertSame([2], $orderItemIds);
    }

    public function testAlias(): void
    {
        $query = Order::find();
        $this->assertNull($query->getFrom());

        $query = Order::find()->alias('o');
        $this->assertEquals(['o' => Order::tableName()], $query->getFrom());

        $query = Order::find()->alias('o')->alias('ord');
        $this->assertEquals(['ord' => Order::tableName()], $query->getFrom());

        $query = Order::find()->from([
            'users',
            'o' => Order::tableName(),
        ])->alias('ord');
        $this->assertEquals([
            'users',
            'ord' => Order::tableName(),
        ], $query->getFrom());
    }

    public function testInverseOf(): void
    {
        /** eager loading: find one and all */
        $customer = Customer::find()->with('orders2')->where(['id' => 1])->one();
        $this->assertSame($customer->orders2[0]->customer2, $customer);

        $customers = Customer::find()->with('orders2')->where(['id' => [1, 3]])->all();
        $this->assertEmpty($customers[1]->orders2);
        $this->assertSame($customers[0]->orders2[0]->customer2, $customers[0]);

        /** lazy loading */
        $customer = Customer::findOne(2);
        $orders = $customer->orders2;
        $this->assertCount(2, $orders);
        $this->assertSame($customer->orders2[0]->customer2, $customer);
        $this->assertSame($customer->orders2[1]->customer2, $customer);

        /** ad-hoc lazy loading */
        $customer = Customer::findOne(2);
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
        $customer = Customer::find()->with('orders2')->where(['id' => 1])->asArray()->one();
        $this->assertSame($customer['orders2'][0]['customer2']['id'], $customer['id']);

        $customers = Customer::find()->with('orders2')->where(['id' => [1, 3]])->asArray()->all();
        $this->assertSame($customer['orders2'][0]['customer2']['id'], $customers[0]['id']);
        $this->assertEmpty($customers[1]['orders2']);

        $orders = Order::find()->with('customer2')->where(['id' => 1])->all();
        $this->assertSame($orders[0]->customer2->orders2, [$orders[0]]);

        $order = Order::find()->with('customer2')->where(['id' => 1])->one();
        $this->assertSame($order->customer2->orders2, [$order]);

        $orders = Order::find()->with('customer2')->where(['id' => 1])->asArray()->all();
        $this->assertSame($orders[0]['customer2']['orders2'][0]['id'], $orders[0]['id']);

        $order = Order::find()->with('customer2')->where(['id' => 1])->asArray()->one();
        $this->assertSame($order['customer2']['orders2'][0]['id'], $orders[0]['id']);

        $orders = Order::find()->with('customer2')->where(['id' => [1, 3]])->all();
        $this->assertSame($orders[0]->customer2->orders2, [$orders[0]]);
        $this->assertSame($orders[1]->customer2->orders2, [$orders[1]]);

        $orders = Order::find()->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->all();
        $this->assertSame($orders[0]->customer2->orders2, $orders);
        $this->assertSame($orders[1]->customer2->orders2, $orders);

        $orders = Order::find()->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->asArray()->all();
        $this->assertSame($orders[0]['customer2']['orders2'][0]['id'], $orders[0]['id']);
        $this->assertSame($orders[0]['customer2']['orders2'][1]['id'], $orders[1]['id']);
        $this->assertSame($orders[1]['customer2']['orders2'][0]['id'], $orders[0]['id']);
        $this->assertSame($orders[1]['customer2']['orders2'][1]['id'], $orders[1]['id']);
    }

    public function testInverseOfDynamic(): void
    {
        $customer = Customer::findOne(1);

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
        $model = new Type();

        $model->loadDefaultValues();

        $this->assertEquals(1, $model->int_col2);
        $this->assertEquals('something', $model->char_col2);
        $this->assertEquals(1.23, $model->float_col2);
        $this->assertEquals(33.22, $model->numeric_col);
        $this->assertEquals(true, $model->bool_col2);
        $this->assertEquals('2002-01-01 00:00:00', $model->time);

        $model = new Type();
        $model->char_col2 = 'not something';

        $model->loadDefaultValues();
        $this->assertEquals('not something', $model->char_col2);

        $model = new Type();
        $model->char_col2 = 'not something';

        $model->loadDefaultValues(false);
        $this->assertEquals('something', $model->char_col2);
    }

    public function testUnlinkAllViaTable(): void
    {
        /** @var $orderClass ActiveRecordInterface */
        $orderClass = Order::class;

        /** @var $orderItemClass ActiveRecordInterface */
        $orderItemClass = OrderItem::class;

        /** @var $itemClass ActiveRecordInterface */
        $itemClass = Item::class;

        /** @var $orderItemsWithNullFKClass ActiveRecordInterface */
        $orderItemsWithNullFKClass = OrderItemWithNullFK::class;

        /**
         * via table with delete.
         *
         * @var $order  Order
         */
        $order = $orderClass::findOne(1);
        $this->assertCount(2, $order->booksViaTable);
        $orderItemCount = $orderItemClass::find()->count();
        $this->assertEquals(5, $itemClass::find()->count());
        $order->unlinkAll('booksViaTable', true);
        $this->assertEquals(5, $itemClass::find()->count());
        $this->assertEquals($orderItemCount - 2, $orderItemClass::find()->count());
        $this->assertCount(0, $order->booksViaTable);

        /** via table without delete */
        $this->assertCount(2, $order->booksWithNullFKViaTable);
        $orderItemCount = $orderItemsWithNullFKClass::find()->count();
        $this->assertEquals(5, $itemClass::find()->count());
        $order->unlinkAll('booksWithNullFKViaTable', false);
        $this->assertCount(0, $order->booksWithNullFKViaTable);
        $this->assertEquals(2, $orderItemsWithNullFKClass::find()->where(
            ['AND', ['item_id' => [1, 2]], ['order_id' => null]]
        )->count());
        $this->assertEquals($orderItemCount, $orderItemsWithNullFKClass::find()->count());
        $this->assertEquals(5, $itemClass::find()->count());
    }

    public function testCastValues(): void
    {
        $model = new Type();

        $model->int_col = 123;
        $model->int_col2 = 456;
        $model->smallint_col = 42;
        $model->char_col = '1337';
        $model->char_col2 = 'test';
        $model->char_col3 = 'test123';
        $model->float_col = 3.742;
        $model->float_col2 = 42.1337;
        $model->bool_col = true;
        $model->bool_col2 = false;
        $model->save();

        /** @var $model Type */
        $model = Type::find()->one();

        $this->assertSame(123, $model->int_col);
        $this->assertSame(456, $model->int_col2);
        $this->assertSame(42, $model->smallint_col);
        $this->assertSame('1337', trim($model->char_col));
        $this->assertSame('test', $model->char_col2);
        $this->assertSame('test123', $model->char_col3);
    }

    public function testIssues(): void
    {
        $this->loadFixture(Category::getConnection());

        /** {@see https://github.com/yiisoft/yii2/issues/4938} */
        $category = Category::findOne(2);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals(3, $category->getItems()->count());
        $this->assertEquals(1, $category->getLimitedItems()->count());
        $this->assertEquals(1, $category->getLimitedItems()->distinct(true)->count());

        /** {@see https://github.com/yiisoft/yii2/issues/3197} */
        $orders = Order::find()->with('orderItems')->orderBy('id')->all();

        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->orderItems);
        $this->assertCount(3, $orders[1]->orderItems);
        $this->assertCount(1, $orders[2]->orderItems);

        $orders = Order::find()
            ->with([
                'orderItems' => static function ($q) {
                    $q->indexBy('item_id');
                },
            ])
            ->orderBy('id')
            ->all();

        $this->assertCount(3, $orders);
        $this->assertCount(2, $orders[0]->orderItems);
        $this->assertCount(3, $orders[1]->orderItems);
        $this->assertCount(1, $orders[2]->orderItems);

        /** {@see https://github.com/yiisoft/yii2/issues/8149} */
        $model = new Customer();

        $model->name = 'test';
        $model->email = 'test';
        $model->save();
        $model->updateCounters(['status' => 1]);
        $this->assertEquals(1, $model->status);
    }

    public function testPopulateRecordCallWhenQueryingOnParentClass(): void
    {
        (new Cat())->save();
        (new Dog())->save();

        $animal = Animal::find()->where(['type' => Dog::class])->one();
        $this->assertEquals('bark', $animal->getDoes());

        $animal = Animal::find()->where(['type' => Cat::class])->one();
        $this->assertEquals('meow', $animal->getDoes());
    }

    public function testSaveEmpty(): void
    {
        $record = new NullValues();

        $this->assertTrue($record->save());
        $this->assertEquals(1, $record->id);
    }

    public function testOptimisticLock(): void
    {
        /** @var $record Document */
        $record = Document::findOne(1);

        $record->content = 'New Content';
        $record->save();
        $this->assertEquals(1, $record->version);

        $record = Document::findOne(1);

        $record->content = 'Rewrite attempt content';
        $record->version = 0;
        $this->expectException(StaleObjectException::class);
        $record->save();
    }

    public function testPopulateWithoutPk(): void
    {
        /** tests with single pk asArray */
        $aggregation = Customer::find()
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
        $aggregation = Customer::find()
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
        $aggregation = OrderItem::find()
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
        $aggregation = OrderItem::find()
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
        $falseBit = BitValues::findOne(1);
        $this->assertEquals(false, $falseBit->val);

        $trueBit = BitValues::findOne(2);
        $this->assertEquals(true, $trueBit->val);
    }

    public function testLinkWhenRelationIsIndexed2(): void
    {
        $order = Order::find()->with('orderItems2')->where(['id' => 1])->one();

        $orderItem = new OrderItem();

        $orderItem->order_id = $order->id;
        $orderItem->item_id = 3;
        $orderItem->quantity = 1;
        $orderItem->subtotal = 10.0;

        $order->link('orderItems2', $orderItem);
        $this->assertTrue(isset($order->orderItems2['3']));
    }

    public function testUpdateAttributes(): void
    {
        $this->loadFixture(Customer::getConnection());

        $order = Order::findOne(1);

        $newTotal = 978;
        $this->assertSame(1, $order->updateAttributes(['total' => $newTotal]));
        $this->assertEquals($newTotal, $order->total);

        $order = Order::findOne(1);

        $this->assertEquals($newTotal, $order->total);

        /** @see https://github.com/yiisoft/yii2/issues/12143 */
        $newOrder = new Order();

        $this->assertTrue($newOrder->getIsNewRecord());

        $newTotal = 200;
        $this->assertSame(0, $newOrder->updateAttributes(['total' => $newTotal]));
        $this->assertTrue($newOrder->getIsNewRecord());
        $this->assertEquals($newTotal, $newOrder->total);
    }

    public function testEmulateExecution(): void
    {
        $this->assertGreaterThan(0, Customer::find()->count());

        $rows = Customer::find()->emulateExecution()->all();
        $this->assertSame([], $rows);

        $row = Customer::find()->emulateExecution()->one();
        $this->assertNull($row);

        $exists = Customer::find()->emulateExecution()->exists();
        $this->assertFalse($exists);

        $count = Customer::find()->emulateExecution()->count();
        $this->assertSame(0, $count);

        $sum = Customer::find()->emulateExecution()->sum('id');
        $this->assertSame(0, $sum);

        $sum = Customer::find()->emulateExecution()->average('id');
        $this->assertSame(0, $sum);

        $max = Customer::find()->emulateExecution()->max('id');
        $this->assertNull($max);

        $min = Customer::find()->emulateExecution()->min('id');
        $this->assertNull($min);

        $scalar = Customer::find()->select(['id'])->emulateExecution()->scalar();
        $this->assertNull($scalar);

        $column = Customer::find()->select(['id'])->emulateExecution()->column();
        $this->assertSame([], $column);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/12213}
     */
    public function testUnlinkAllOnCondition(): void
    {
        /** @var Category $categoryClass */
        $categoryClass = Category::class;

        /** @var Item $itemClass */
        $itemClass = Item::class;

        /** Ensure there are three items with category_id = 2 in the Items table */
        $itemsCount = $itemClass::find()->where(['category_id' => 2])->count();
        $this->assertEquals(3, $itemsCount);

        $categoryQuery = $categoryClass::find()->with('limitedItems')->where(['id' => 2]);

        /**
         * Ensure that limitedItems relation returns only one item (category_id = 2 and id in (1,2,3))
         */
        $category = $categoryQuery->one();
        $this->assertCount(1, $category->limitedItems);

        /** Unlink all items in the limitedItems relation */
        $category->unlinkAll('limitedItems', true);

        /** Make sure that only one item was unlinked */
        $itemsCount = $itemClass::find()->where(['category_id' => 2])->count();
        $this->assertEquals(2, $itemsCount);

        /** Call $categoryQuery again to ensure no items were found */
        $this->assertCount(0, $categoryQuery->one()->limitedItems);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/12213}
     */
    public function testUnlinkAllOnConditionViaTable(): void
    {
        $this->loadFixture(Order::getConnection());

        /** @var Order $orderClass */
        $orderClass = Order::class;

        /** @var Item $itemClass */
        $itemClass = Item::class;

        /** Ensure there are three items with category_id = 2 in the Items table */
        $itemsCount = $itemClass::find()->where(['category_id' => 2])->count();
        $this->assertEquals(3, $itemsCount);

        $orderQuery = $orderClass::find()->with('limitedItems')->where(['id' => 2]);

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
        $this->assertEquals(3, $itemClass::find()->where(['category_id' => 2])->count());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/pull/13891}
     */
    public function testIndexByAfterLoadingRelations(): void
    {
        $orderClass = Order::class;

        $orderClass::find()->with('customer')->indexBy(function (Order $order) {
            $this->assertTrue($order->isRelationPopulated('customer'));
            $this->assertNotEmpty($order->customer->id);

            return $order->customer->id;
        })->all();

        $orders = $orderClass::find()->with('customer')->indexBy('customer.id')->all();

        foreach ($orders as $customer_id => $order) {
            $this->assertEquals($customer_id, $order->customer_id);
        }
    }

    /**
     * Verify that {{}} are not going to be replaced in parameters.
     */
    public function testNoTablenameReplacement(): void
    {
        $customer = new Customer();

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
        CustomerQuery::$joinWithProfile = true;

        $model = Customer::findOne(1);

        $this->assertTrue($model->refresh());

        CustomerQuery::$joinWithProfile = false;
    }

    public function testFindOneByColumnName(): void
    {
        $model = Customer::findOne(['id' => 1]);
        $this->assertEquals(1, $model->id);

        CustomerQuery::$joinWithProfile = true;

        $model = Customer::findOne(['customer.id' => 1]);

        $this->assertEquals(1, $model->id);

        CustomerQuery::$joinWithProfile = false;
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
        $query = Customer::find()->from($fromParams);

        $aliases = $this->invokeMethod(new Customer(), 'filterValidAliases', [$query]);

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
        $query = $this->invokeMethod(new $modelClassName(), 'findByCondition', [$validFilter]);

        Customer::getConnection()->getQueryBuilder()->build($query);

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
     *
     * @throws Exception
     * @throws InvalidConfigException
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
        $query = $this->invokeMethod(new $modelClassName(), 'findByCondition', $filterWithInjection);

        Customer::getConnection()->getQueryBuilder()->build($query);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/5786}
     *
     * @depends testJoinWith
     */
    public function testFindWithConstructors(): void
    {
        /** @var OrderWithConstructor[] $orders */
        $orders = OrderWithConstructor::find()->with(['customer.profile', 'orderItems'])->orderBy('id')->all();

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
        $orders = OrderWithConstructor::find()
            ->with(['customer.profile', 'orderItems'])
            ->orderBy('id')
            ->asArray(true)
            ->all();
        $this->assertCount(3, $orders);
    }

    public function testCustomARRelation(): void
    {
        $this->loadFixture(Customer::getConnection());

        $orderItem = OrderItem::findOne(1);

        $this->assertInstanceOf(Order::class, $orderItem->custom);
    }


    public function testRefreshQuerySetAliasFindRecord(): void
    {
        $customer = new CustomerWithAlias();

        $customer->id = 1;
        $customer->refresh();

        $this->assertEquals(1, $customer->id);
    }

    public function testResetNotSavedRelation(): void
    {
        $order = new Order();

        $order->customer_id = 1;
        $order->created_at = 1325502201;
        $order->total = 0;

        $orderItem = new OrderItem();

        $order->orderItems;

        $order->populateRelation('orderItems', [$orderItem]);

        $order->save();

        $this->assertCount(1, $order->orderItems);
    }

    public function testIssetException(): void
    {
        $cat = new Cat();

        $this->assertFalse(isset($cat->exception));
    }

    public function testIssetThrowable(): void
    {
        $cat = new Cat();

        $this->assertFalse(isset($cat->throwable));
    }
}
