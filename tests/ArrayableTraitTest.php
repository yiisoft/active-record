<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use ArrayIterator;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerClosureField;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerForArrayable;

abstract class ArrayableTraitTest extends TestCase
{
    public function testFields(): void
    {
        $customerQuery = CustomerForArrayable::query();

        $fields = $customerQuery->findByPk(1)->fields();

        $this->assertEquals(
            [
                'id' => 'id',
                'email' => 'email',
                'name' => 'name',
                'address' => 'address',
                'status' => 'status',
                'bool_status' => 'bool_status',
                'registered_at' => 'registered_at',
                'profile_id' => 'profile_id',
                'item' => 'item',
                'items' => 'items',
            ],
            $fields,
        );
    }

    public function testToArray(): void
    {
        $customerQuery = Customer::query();
        $customer = $customerQuery->findByPk(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 1,
                'bool_status' => true,
                'registered_at' => '2011-01-01T01:01:01.111111+01:00',
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }

    public function testToArrayWithClosure(): void
    {
        $customerQuery = CustomerClosureField::query();
        $customer = $customerQuery->findByPk(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 'active',
                'bool_status' => true,
                'registered_at' => '2011-01-01T01:01:01.111111+01:00',
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }

    public function testToArrayForArrayable(): void
    {
        $customerQuery = CustomerForArrayable::query();

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

    public function testPopulateRecordFromAnotherRecord(): void
    {
        $customer1 = Customer::query()->findByPk(1);
        $customer2 = new Customer();
        $customer2->populateRecord($customer1);

        $this->assertSame(1, $customer2->getId());
        $this->assertSame('user1@example.com', $customer2->getEmail());
        $this->assertSame('user1', $customer2->getName());
    }

    public function testPopulateRecordFromTraversable(): void
    {
        $customer = new Customer();
        $customer->populateRecord(
            new ArrayIterator(['email' => 'test@example.com', 'name' => 'Vasya']),
        );

        $this->assertNull($customer->getId());
        $this->assertSame('test@example.com', $customer->getEmail());
        $this->assertSame('Vasya', $customer->getName());
    }

    public function testPopulateRecordFromCustomObject(): void
    {
        $customer = new Customer();
        $customer->populateRecord(
            new class {
                private int $id = 1;
                public string $email = 'test@example.com';
                public string $name = 'Vasya';
            },
        );

        $this->assertNull($customer->getId());
        $this->assertSame('test@example.com', $customer->getEmail());
        $this->assertSame('Vasya', $customer->getName());
    }
}
