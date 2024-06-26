<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveRecordFactory;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\ArrayAndJsonTypes;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithConstructor;
use Yiisoft\ActiveRecord\Tests\Support\Assert;

abstract class ActiveRecordFactoryTest extends TestCase
{
    protected ActiveRecordFactory $arFactory;

    public function testCreateAR(): void
    {
        $this->assertInstanceOf(Customer::class, $this->arFactory->createAR(Customer::class));
    }

    public function testCreateARWithConnection(): void
    {
        $customerAR = $this->arFactory->createAR(arClass: Customer::class, db: $this->db);
        $db = Assert::invokeMethod($customerAR, 'db');

        $this->assertSame($this->db, $db);
        $this->assertSame('customer', $customerAR->getTableName());
        $this->assertInstanceOf(Customer::class, $customerAR);
    }

    public function testCreateARWithTableName(): void
    {
        $model = $this->arFactory->createAR(ArrayAndJsonTypes::class);

        $this->assertSame('{{%array_and_json_types}}', $model->getTableName());
        $this->assertInstanceOf(ArrayAndJsonTypes::class, $model);

        $model = $model->withTableName('array_and_json_types');

        $this->assertSame('array_and_json_types', $model->getTableName());
        $this->assertInstanceOf(ArrayAndJsonTypes::class, $model);
    }

    public function testCreateQueryTo(): void
    {
        /** example create active query */
        $customerQuery = $this->arFactory->createQueryTo(Customer::class);

        $this->assertInstanceOf(ActiveQuery::class, $customerQuery);

        /** example create active query custom */
        $customerQuery = $this->arFactory->createQueryTo(arClass: Customer::class, queryClass: CustomerQuery::class);

        $this->assertInstanceOf(CustomerQuery::class, $customerQuery);
    }

    public function testCreateQueryToWithConnection(): void
    {
        /** example create active query */
        $customerQuery = $this->arFactory->createQueryTo(
            arClass: Customer::class,
            queryClass: CustomerQuery::class,
            db: $this->db,
        );
        $db = Assert::inaccessibleProperty($customerQuery, 'db');

        $this->assertSame($this->db, $db);
        $this->assertInstanceOf(ActiveQuery::class, $customerQuery);
    }

    public function testCreateQueryToWithTableName(): void
    {
        /** example create active query */
        $modelQuery = $this->arFactory->createQueryTo(
            arClass: fn (): ActiveRecordInterface => $this->arFactory
                ->createAR(ArrayAndJsonTypes::class)
                ->withTableName('array_and_json_types'),
        );
        $tableName = $modelQuery->getARInstance()->getTableName();

        $this->assertSame('array_and_json_types', $tableName);
        $this->assertInstanceOf(ActiveQuery::class, $modelQuery);
    }

    public function testGetArInstanceWithConstructor(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $query = $this->arFactory->createQueryTo(CustomerWithConstructor::class);
        $customer = $query->onePopulate();

        $this->assertNotNull($customer->getProfile());
    }
}
