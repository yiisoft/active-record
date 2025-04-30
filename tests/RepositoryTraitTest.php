<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerClosureField;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerForArrayable;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithRepositoryTrait;

abstract class RepositoryTraitTest extends TestCase
{
    public function testFind(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(new CustomerWithRepositoryTrait());

        $this->assertEquals(
            $customerQuery->find(['id' => 1]),
            CustomerWithRepositoryTrait::find(['id' => 1]),
        );
    }

    public function testFindOne(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(new CustomerWithRepositoryTrait());

        $this->assertEquals(
            $customerQuery->findOne(['id' => 1]),
            CustomerWithRepositoryTrait::findOne(['id' => 1]),
        );
    }

    public function testFindAll(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(new CustomerWithRepositoryTrait());

        $this->assertEquals(
            $customerQuery->all(),
            CustomerWithRepositoryTrait::findAll(),
        );

        $this->assertEquals(
            $customerQuery->findAll(['id' => 1]),
            CustomerWithRepositoryTrait::findAll(['id' => 1]),
        );
    }

    public function testFindBySql(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(new CustomerWithRepositoryTrait());

        $this->assertEquals(
            $customerQuery->findBySql('SELECT * FROM {{customer}}'),
            CustomerWithRepositoryTrait::findBySql('SELECT * FROM {{customer}}'),
        );
    }
}
