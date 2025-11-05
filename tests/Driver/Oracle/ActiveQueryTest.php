<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use PHPUnit\Framework\Attributes\DataProvider;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\Tests\Driver\Oracle\Stubs\Order;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveQueryTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryTest
{
    /**
     * Tests the alias syntax for joinWith: 'alias' => 'relation'.
     *
     * @param string $aliasMethod whether alias is specified explicitly or using the query syntax {{@tablename}}
     */
    #[DataProvider('aliasMethodProvider')]
    public function testJoinWithAlias(string $aliasMethod): void
    {
        $orders = [];
        /** left join and eager loading */
        $query = Order::query()->joinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $orders = $query->orderBy('c.id DESC, order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->orderBy('{{@customer}}.id DESC, {{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->orderBy(
                $query->applyAlias('customer', 'id') . ' DESC,' . $query->applyAlias('order', 'id'),
            )->all();
        }

        $this->assertCount(3, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertEquals(1, $orders[2]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        /** inner join filtering and eager loading */
        $query = Order::query()->innerJoinWith(['customer c']);

        if ($aliasMethod === 'explicit') {
            $orders = $query->andWhere('{{c}}.[[id]]=2')->orderBy('order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->andWhere('{{@customer}}.[[id]]=2')->orderBy('{{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->andWhere(
                [$query->applyAlias('customer', 'id') => 2],
            )->orderBy($query->applyAlias('order', 'id'))->all();
        }

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        /** inner join filtering without eager loading */
        $query = Order::query()->innerJoinWith(['customer c'], false);

        if ($aliasMethod === 'explicit') {
            $orders = $query->andWhere('{{c}}.[[id]]=2')->orderBy('order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->andWhere('{{@customer}}.[[id]]=2')->orderBy('{{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->andWhere(
                [$query->applyAlias('customer', 'id') => 2],
            )->orderBy($query->applyAlias('order', 'id'))->all();
        }

        $this->assertCount(2, $orders);
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        /** join with via-relation */
        $query = Order::query()->innerJoinWith(['books b']);

        if ($aliasMethod === 'explicit') {
            $orders = $query->andWhere(
                ['b.name' => 'Yii3 Cookbook'],
            )->orderBy('order.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->andWhere(
                ['{{@item}}.name' => 'Yii3 Cookbook'],
            )->orderBy('{{@order}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->andWhere(
                [$query->applyAlias('book', 'name') => 'Yii3 Cookbook'],
            )->orderBy($query->applyAlias('order', 'id'))->all();
        }

        $this->assertCount(2, $orders);
        $this->assertCount(2, $orders[0]->getBooks());
        $this->assertCount(1, $orders[1]->getBooks());
        $this->assertEquals(1, $orders[0]->getId());
        $this->assertEquals(3, $orders[1]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));

        /** joining sub relations */
        $query = Order::query()->innerJoinWith(
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
            ],
        );

        if ($aliasMethod === 'explicit') {
            $orders = $query->orderBy('{{i}}.id')->all();
        } elseif ($aliasMethod === 'querysyntax') {
            $orders = $query->orderBy('{{@item}}.id')->all();
        } elseif ($aliasMethod === 'applyAlias') {
            $orders = $query->orderBy($query->applyAlias('item', 'id'))->all();
        }

        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));

        /** join with ON condition */
        if ($aliasMethod === 'explicit' || $aliasMethod === 'querysyntax') {
            $relationName = 'books' . ucfirst($aliasMethod);

            $orders = Order::query()->joinWith(["$relationName b"])->orderBy('order.id')->all();

            $this->assertCount(3, $orders);
            $this->assertCount(2, $orders[0]->relation($relationName));
            $this->assertCount(0, $orders[1]->relation($relationName));
            $this->assertCount(1, $orders[2]->relation($relationName));
            $this->assertEquals(1, $orders[0]->getId());
            $this->assertEquals(2, $orders[1]->getId());
            $this->assertEquals(3, $orders[2]->getId());
            $this->assertTrue($orders[0]->isRelationPopulated($relationName));
            $this->assertTrue($orders[1]->isRelationPopulated($relationName));
            $this->assertTrue($orders[2]->isRelationPopulated($relationName));
        }

        /** join with ON condition and alias in relation definition */
        if ($aliasMethod === 'explicit' || $aliasMethod === 'querysyntax') {
            $relationName = 'books' . ucfirst($aliasMethod) . 'A';

            $orders = Order::query()->joinWith([$relationName])->orderBy('order.id')->all();

            $this->assertCount(3, $orders);
            $this->assertCount(2, $orders[0]->relation($relationName));
            $this->assertCount(0, $orders[1]->relation($relationName));
            $this->assertCount(1, $orders[2]->relation($relationName));
            $this->assertEquals(1, $orders[0]->getId());
            $this->assertEquals(2, $orders[1]->getId());
            $this->assertEquals(3, $orders[2]->getId());
            $this->assertTrue($orders[0]->isRelationPopulated($relationName));
            $this->assertTrue($orders[1]->isRelationPopulated($relationName));
            $this->assertTrue($orders[2]->isRelationPopulated($relationName));
        }

        /** join with count and query */
        $query = Order::query()->joinWith(['customer c']);

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
        $order = Order::query()->findByPk(1);

        $customerQuery = $order->getCustomerQuery()->innerJoinWith(['orders o'], false);

        if ($aliasMethod === 'explicit') {
            $customer = $customerQuery->where(['{{o}}.[[id]]' => 1])->one();
        } elseif ($aliasMethod === 'querysyntax') {
            $customer = $customerQuery->where(['{{@order}}.id' => 1])->one();
        } elseif ($aliasMethod === 'applyAlias') {
            $customer = $customerQuery->where([$query->applyAlias('order', 'id') => 1])->one();
        }

        $this->assertEquals(1, $customer->getId());
        $this->assertNotNull($customer);

        /** join with sub-relation called inside Closure */
        $orders = Order::query()->joinWith(
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
            ],
        )->orderBy('order.id')->all();

        $this->assertCount(1, $orders);
        $this->assertCount(3, $orders[0]->getItems());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertEquals(2, $orders[0]->getItems()[0]->getCategory()->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertTrue($orders[0]->getItems()[0]->isRelationPopulated('category'));
    }

    /**
     * @depends testJoinWith
     */
    public function testJoinWithSameTable(): void
    {
        /**
         * join with the same table but different aliases alias is defined in the relation definition without eager
         * loading
         */
        $query = Order::query()
            ->joinWith('bookItems', false)
            ->joinWith('movieItems', false)
            ->andWhere(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true),
        );
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertFalse($orders[0]->isRelationPopulated('bookItems'));
        $this->assertFalse($orders[0]->isRelationPopulated('movieItems'));

        /** with eager loading */
        $query = Order::query()
            ->joinWith('bookItems')
            ->joinWith('movieItems')
            ->andWhere(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true),
        );
        $this->assertCount(0, $orders[0]->getBookItems());
        $this->assertCount(3, $orders[0]->getMovieItems());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('bookItems'));
        $this->assertTrue($orders[0]->isRelationPopulated('movieItems'));

        /**
         * join with the same table but different aliases alias is defined in the call to joinWith() without eager
         * loading
         */
        $query = Order::query()
            ->joinWith(
                [
                    'itemsIndexed books' => static function (ActiveQueryInterface $q) {
                        $q->on('{{books}}.[[category_id]] = 1');
                    },
                ],
                false,
            )->joinWith(
                [
                    'itemsIndexed movies' => static function (ActiveQueryInterface $q) {
                        $q->on('{{movies}}.[[category_id]] = 2');
                    },
                ],
                false,
            )->andWhere(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(
            1,
            $orders,
            $query->createCommand()->getRawSql() . print_r($orders, true),
        );
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertFalse($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, only for one relation as it would be overwritten otherwise. */
        $query = Order::query()
            ->joinWith(
                [
                    'itemsIndexed books' => static function (ActiveQueryInterface $q) {
                        $q->on('{{books}}.[[category_id]] = 1');
                    },
                ],
                false,
            )
            ->joinWith(
                [
                    'itemsIndexed movies' => static function (ActiveQueryInterface $q) {
                        $q->on('{{movies}}.[[category_id]] = 2');
                    },
                ],
                true,
            )->andWhere(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(1, $orders, $query->createCommand()->getRawSql() . print_r($orders, true));
        $this->assertCount(3, $orders[0]->getItemsIndexed());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));

        /** with eager loading, and the other relation */
        $query = Order::query()
            ->joinWith(
                [
                    'itemsIndexed books' => static function (ActiveQueryInterface $q) {
                        $q->on('{{books}}.[[category_id]] = 1');
                    },
                ],
                true,
            )
            ->joinWith(
                [
                    'itemsIndexed movies' => static function (ActiveQueryInterface $q) {
                        $q->on('{{movies}}.[[category_id]] = 2');
                    },
                ],
                false,
            )
            ->andWhere(['{{movies}}.[[name]]' => 'Toy Story']);
        $orders = $query->all();
        $this->assertCount(1, $orders, $query->createCommand()->getRawSql() . print_r($orders, true));
        $this->assertCount(0, $orders[0]->getItemsIndexed());
        $this->assertEquals(2, $orders[0]->getId());
        $this->assertTrue($orders[0]->isRelationPopulated('itemsIndexed'));
    }
    protected static function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }
}
