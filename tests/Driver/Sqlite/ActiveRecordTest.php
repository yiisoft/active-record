<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Support\SqliteHelper;

final class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordTest
{
    public function setUp(): void
    {
        parent::setUp();

        $sqliteHelper = new SqliteHelper();
        ConnectionProvider::set($sqliteHelper->createConnection());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        ConnectionProvider::unset();
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customer = new Customer();

        $customer->setId(1337);
        $customer->setEmail('user1337@example.com');
        $customer->setName('user1337');
        $customer->setAddress('address1337');

        $this->assertTrue($customer->getIsNewRecord());
        $customer->save();

        $this->assertEquals(1337, $customer->getId());
        $this->assertFalse($customer->getIsNewRecord());
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/15482}
     */
    public function testEagerLoadingUsingStringIdentifiers(): void
    {
        $this->checkFixture($this->db(), 'beta');

        $betaQuery = new ActiveQuery(Beta::class, $this->db());

        $betas = $betaQuery->with('alpha')->all();

        $this->assertNotEmpty($betas);

        $alphaIdentifiers = [];

        /** @var Beta[] $betas */
        foreach ($betas as $beta) {
            $this->assertNotNull($beta->getAlpha());
            $this->assertEquals($beta->getAlphaStringIdentifier(), $beta->getAlpha()->getStringIdentifier());
            $alphaIdentifiers[] = $beta->getAlpha()->getStringIdentifier();
        }

        $this->assertEquals(['1', '01', '001', '001', '2', '2b', '2b', '02'], $alphaIdentifiers);
    }
}
