<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Oracle;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\ActiveQueryTest as AbstractActiveQueryTest;
use Yiisoft\ActiveRecord\Tests\Oracle\Stubs\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BitValues;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group oci
 */
final class ActiveQueryTest extends AbstractActiveQueryTest
{
    protected string $driverName = 'oci';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->ociConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->ociConnection->close();

        unset($this->ociConnection);
    }

    /**
     * @depends testJoinWith
     *
     * Tests the alias syntax for joinWith: 'alias' => 'relation'.
     *
     * @dataProvider aliasMethodProvider
     *
     * @param string $aliasMethod whether alias is specified explicitly or using the query syntax {{@tablename}}
     *
     * @throws Exception|InvalidConfigException|Throwable
     */
    public function testJoinWithAlias(string $aliasMethod): void
    {
        $this->checkFixture($this->db, 'order');

        /** left join and eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->joinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $orders = $query
                ->orderBy('c.id DESC, order.id')
                ->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query
                ->orderBy('{{@customer}}.id DESC, {{@order}}.id')
                ->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query
                ->orderBy(
                    $query->applyAlias('customer', 'id') . ' DESC,' . $query->applyAlias('order', 'id')
                )
                ->all();
        }

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** inner join filtering and eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->innerJoinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $orders = $query
                ->where('{{c}}.[[id]]=2')
                ->orderBy('order.id')
                ->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query
                ->where('{{@customer}}.[[id]]=2')
                ->orderBy('{{@order}}.id')
                ->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query
                ->where([$query->applyAlias('customer', 'id') => 2])
                ->orderBy($query->applyAlias('order', 'id'))
                ->all();
        }

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->innerJoinWith(['customer c'], false);

        if ($aliasMethod === 'explicit') {
            $orders = $query
                ->where('{{c}}.[[id]]=2')
                ->orderBy('order.id')
                ->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query
                ->where('{{@customer}}.[[id]]=2')
                ->orderBy('{{@order}}.id')
                ->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query
                ->where([$query->applyAlias('customer', 'id') => 2])
                ->orderBy($query->applyAlias('order', 'id'))
                ->all();
        }

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        /** join with via-relation */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->innerJoinWith(['books b']);

        if ($aliasMethod === 'explicit') {
            $orders = $query
                ->where(['b.name' => 'Yii 1.1 Application Development Cookbook'])
                ->orderBy('order.id')
                ->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query
                ->where(['{{@item}}.name' => 'Yii 1.1 Application Development Cookbook'])
                ->orderBy('{{@order}}.id')
                ->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query
                ->where([$query->applyAlias('book', 'name') => 'Yii 1.1 Application Development Cookbook'])
                ->orderBy($query->applyAlias('order', 'id'))
                ->all();
        }

        $this->assertCount(2, $orders);
        $this->assertCount(2, $orders[0]->books);
        $this->assertCount(1, $orders[1]->books);
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));

        /** joining sub relations */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->innerJoinWith(
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
                },
            ]
        );

        if ($aliasMethod === 'explicit') {
            $orders = $query
                ->orderBy('{{i}}.id')
                ->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query
                ->orderBy('{{@item}}.id')
                ->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query
                ->orderBy($query->applyAlias('item', 'id'))
                ->all();
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

            $orderQuery = new ActiveQuery(Order::class, $this->db);
            $orders = $orderQuery
                ->joinWith(["$relationName b"])
                ->orderBy('order.id')
                ->all();

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

            $orderQuery = new ActiveQuery(Order::class, $this->db);
            $orders = $orderQuery
                ->joinWith([(string)$relationName])
                ->orderBy('order.id')
                ->all();

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
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $query = $orderQuery->joinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $count = $query->count('{{c}}.[[id]]');
        } elseif ($aliasMethod === 'querysyntax') {
            $count = $query->count('{{@customer}}.id');
        } elseif ($aliasMethod === 'applyAlias') {
            $count = $query->count($query->applyAlias('customer', 'id'));
        }

        $this->assertEquals(3, $count);

        $orders = $query->all();
        $this->assertCount(3, $orders);

        /** relational query */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(1);

        $customerQuery = $order
            ->getCustomer()
            ->innerJoinWith(['orders o'], false);

        if ($aliasMethod === 'explicit') {
            $customer = $customerQuery
                ->where(['{{o}}.[[id]]' => 1])
                ->one();
        } elseif ($aliasMethod === 'querysyntax') {
            $customer = $customerQuery
                ->where(['{{@order}}.id' => 1])
                ->one();
        } elseif ($aliasMethod === 'applyAlias') {
            $customer = $customerQuery
                ->where([$query->applyAlias('order', 'id') => 1])
                ->one();
        }

        $this->assertEquals(1, $customer->id);
        $this->assertNotNull($customer);

        /** join with sub-relation called inside Closure */
        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $orders = $orderQuery
            ->joinWith(
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
                    },
                ]
            )
            ->orderBy('order.id')
            ->all();

        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->items);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithSameTable(): void
    {
        $this->checkFixture($this->db, 'order');

        /**
         * join with the same table but different aliases alias is defined in the relation definition without eager
         * loading
         */
        $query = new ActiveQuery(Order::class, $this->db);
        $query
            ->joinWith('bookItems', false)
            ->joinWith('movieItems', false)
            ->where(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query
                ->createCommand()
                ->getRawSql() . print_r($orders, true)
        );
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('bookItems'));
        $this->assertFalse($orders[0]->isRelationPopulated('movieItems'));

        /** with eager loading */
        $query = new ActiveQuery(Order::class, $this->db);
        $query
            ->joinWith('bookItems', true)
            ->joinWith('movieItems', true)
            ->where(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query
                ->createCommand()
                ->getRawSql() . print_r($orders, true)
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
        $query = new ActiveQuery(Order::class, $this->db);
        $query
            ->joinWith(
                [
                    'itemsIndexed books' => static function ($q) {
                        $q->onCondition('{{books}}.[[category_id]] = 1');
                    },
                ],
                false
            )
            ->joinWith(
                [
                    'itemsIndexed movies' => static function ($q) {
                        $q->onCondition('{{movies}}.[[category_id]] = 2');
                    },
                ],
                false
            )
            ->where(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query
                ->createCommand()
                ->getRawSql() . print_r($orders, true)
        );
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, only for one relation as it would be overwritten otherwise. */
        $query = new ActiveQuery(Order::class, $this->db);
        $query
            ->joinWith(
                [
                    'itemsIndexed books' => static function ($q) {
                        $q->onCondition('{{books}}.[[category_id]] = 1');
                    },
                ],
                false
            )
            ->joinWith(
                [
                    'itemsIndexed movies' => static function ($q) {
                        $q->onCondition('{{movies}}.[[category_id]] = 2');
                    },
                ],
                true
            )
            ->where(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(1, $orders, $query
                ->createCommand()
                ->getRawSql() . print_r($orders, true));
        $this->assertCount(3, $orders[0]->itemsIndexed);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, and the other relation */
        $query = new ActiveQuery(Order::class, $this->db);
        $query
            ->joinWith(
                [
                    'itemsIndexed books' => static function ($q) {
                        $q->onCondition('{{books}}.[[category_id]] = 1');
                    },
                ],
                true
            )
            ->joinWith(
                [
                    'itemsIndexed movies' => static function ($q) {
                        $q->onCondition('{{movies}}.[[category_id]] = 2');
                    },
                ],
                false
            )
            ->where(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(1, $orders, $query
                ->createCommand()
                ->getRawSql() . print_r($orders, true));
        $this->assertCount(0, $orders[0]->itemsIndexed);
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/9006
     */
    public function testBit(): void
    {
        $this->checkFixture($this->db, 'bit_values');

        $bitValueQuery = new ActiveQuery(BitValues::class, $this->db);
        $falseBit = $bitValueQuery->findOne(1);
        $this->assertEquals('0', $falseBit->val);

        $bitValueQuery = new ActiveQuery(BitValues::class, $this->db);
        $trueBit = $bitValueQuery->findOne(2);
        $this->assertEquals('1', $trueBit->val);
    }
}
