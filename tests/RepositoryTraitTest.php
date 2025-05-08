<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;

abstract class RepositoryTraitTest extends TestCase
{
    public function testFind(): void
    {
        $customerQuery = new ActiveQuery(new Customer());

        $this->assertEquals(
            $customerQuery->setWhere(['id' => 1]),
            Customer::find(['id' => 1]),
        );
    }

    public function testFindOne(): void
    {
        $customerQuery = new ActiveQuery(new Customer());

        $this->assertEquals(
            $customerQuery->where(['id' => 1])->one(),
            Customer::findOne(['id' => 1]),
        );

        $customer = Customer::findOne(['customer.id' => 1]);
        $this->assertEquals(1, $customer->getId());

        $customer = Customer::findOne(['id' => [5, 6, 1]]);
        $this->assertInstanceOf(Customer::class, $customer);

        $customer = Customer::findOne(['id' => 2, 'name' => 'user2']);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('user2', $customer->getName());

        $customer = Customer::findOne(['id' => 2, 'name' => 'user1']);
        $this->assertNull($customer);

        $customer = Customer::findOne(['name' => 'user5']);
        $this->assertNull($customer);
    }

    public function testFindAll(): void
    {
        $customerQuery = new ActiveQuery(new Customer());

        $this->assertEquals(
            $customerQuery->all(),
            Customer::findAll(),
        );

        $this->assertEquals(
            $customerQuery->where(['id' => 1])->all(),
            Customer::findAll(['id' => 1]),
        );

        $this->assertCount(1, Customer::findAll(['id' => 1]));
        $this->assertCount(3, Customer::findAll(['id' => [1, 2, 3]]));
    }

    public function testFindByPk(): void
    {
        $customerQuery = new ActiveQuery(new Customer());

        $this->assertEquals(
            $customerQuery->where(['id' => 1])->one(),
            Customer::findByPk(1),
        );

        $customer = Customer::findByPk(5);
        $this->assertNull($customer);
    }

    public function testFindBySql(): void
    {
        $customerQuery = new ActiveQuery(new Customer());

        $this->assertEquals(
            $customerQuery->sql('SELECT * FROM {{customer}}'),
            Customer::findBySql('SELECT * FROM {{customer}}'),
        );

        $customer = Customer::findBySql('SELECT * FROM {{customer}} ORDER BY [[id]] DESC')->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('user3', $customer->get('name'));

        $customers = Customer::findBySql('SELECT * FROM {{customer}}')->all();
        $this->assertCount(3, $customers);

        /** find with parameter binding */
        $customer = Customer::findBySql('SELECT * FROM {{customer}} WHERE [[id]]=:id', [':id' => 2])->one();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('user2', $customer->get('name'));

        /** @link https://github.com/yiisoft/yii2/issues/8593 */
        $query = Customer::findBySql('SELECT * FROM {{customer}}');
        $this->assertEquals(3, $query->count());

        $query = Customer::findBySql('SELECT * FROM {{customer}} WHERE  [[id]]=:id', [':id' => 2]);
        $this->assertEquals(1, $query->count());
    }
}
