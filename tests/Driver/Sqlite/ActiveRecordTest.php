<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Support\SqliteHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Factory\Factory;

final class ActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordTest
{
    public function testExplicitPkOnAutoIncrement(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();

        $customer->setId(1337);
        $customer->setEmail('user1337@example.com');
        $customer->setName('user1337');
        $customer->setAddress('address1337');

        $this->assertTrue($customer->isNew());
        $customer->save();

        $this->assertEquals(1337, $customer->getId());
        $this->assertFalse($customer->isNew());
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/15482
     */
    public function testEagerLoadingUsingStringIdentifiers(): void
    {
        $betaQuery = Beta::query();

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
    protected static function createConnection(): ConnectionInterface
    {
        return (new SqliteHelper())->createConnection();
    }

    protected function createFactory(): Factory
    {
        return (new SqliteHelper())->createFactory($this->db());
    }
}
