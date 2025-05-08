<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerClosureField;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerForArrayable;

abstract class ArrayableTraitTest extends TestCase
{
    public function testFields(): void
    {
        $customerQuery = new ActiveQuery(CustomerForArrayable::class);

        $fields = $customerQuery->findOne(['id' => 1])->fields();

        $this->assertEquals(
            [
                'id' => 'id',
                'email' => 'email',
                'name' => 'name',
                'address' => 'address',
                'status' => 'status',
                'bool_status' => 'bool_status',
                'profile_id' => 'profile_id',
                'item' => 'item',
                'items' => 'items',
            ],
            $fields,
        );
    }

    public function testToArray(): void
    {
        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findByPk(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 1,
                'bool_status' => true,
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }

    public function testToArrayWithClosure(): void
    {
        $customerQuery = new ActiveQuery(CustomerClosureField::class);
        $customer = $customerQuery->findByPk(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 'active',
                'bool_status' => true,
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }

    public function testToArrayForArrayable(): void
    {
        $customerQuery = new ActiveQuery(CustomerForArrayable::class);

        /** @var CustomerForArrayable $customer */
        $customer = $customerQuery->findByPk(1);
        /** @var CustomerForArrayable $customer2 */
        $customer2 = $customerQuery->findByPk(2);
        /** @var CustomerForArrayable $customer3 */
        $customer3 = $customerQuery->findByPk(3);

        $customer->setItem($customer2);
        $customer->setItems($customer3);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 'active',
                'item' => [
                    'id' => 2,
                    'email' => 'user2@example.com',
                    'name' => 'user2',
                    'status' => 'active',
                ],
                'items' => [
                    [
                        'id' => 3,
                        'email' => 'user3@example.com',
                        'name' => 'user3',
                        'status' => 'inactive',
                    ],
                ],
            ],
            $customer->toArray([
                'id',
                'name',
                'email',
                'address',
                'status',
                'item.id',
                'item.name',
                'item.email',
                'items.0.id',
                'items.0.name',
                'items.0.email',
            ]),
        );
    }
}
