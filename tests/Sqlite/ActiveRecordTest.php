<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\ActiveRecordTest as AbstractActiveRecordTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group sqlite
 */
class ActiveRecordTest extends AbstractActiveRecordTest
{
    protected string $driverName = 'sqlite';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->sqliteConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->sqliteConnection->close();

        unset($this->sqliteConnection);
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $this->loadFixture($this->db);

        $customer = new Customer($this->db);

        $customer->id = 1337;
        $customer->email = 'user1337@example.com';
        $customer->name = 'user1337';
        $customer->address = 'address1337';

        $this->assertTrue($customer->isNewRecord);
        $customer->save();

        $this->assertEquals(1337, $customer->id);
        $this->assertFalse($customer->isNewRecord);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/15482}
     */
    public function testEagerLoadingUsingStringIdentifiers(): void
    {
        $this->checkFixture($this->db, 'beta');

        $betaQuery = new ActiveQuery(Beta::class, $this->db);

        $betas = $betaQuery->with('alpha')->all();

        $this->assertNotEmpty($betas);

        $alphaIdentifiers = [];

        /** @var Beta[] $betas */
        foreach ($betas as $beta) {
            $this->assertNotNull($beta->alpha);
            $this->assertEquals($beta->alpha_string_identifier, $beta->alpha->string_identifier);
            $alphaIdentifiers[] = $beta->alpha->string_identifier;
        }

        $this->assertEquals(['1', '01', '001', '001', '2', '2b', '2b', '02'], $alphaIdentifiers);
    }
}
