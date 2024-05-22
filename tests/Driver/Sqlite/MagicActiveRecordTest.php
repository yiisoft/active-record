<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Support\SqliteHelper;

final class MagicActiveRecordTest extends \Yiisoft\ActiveRecord\Tests\MagicActiveRecordTest
{
    public function setUp(): void
    {
        parent::setUp();

        $sqliteHelper = new SqliteHelper();
        $this->db = $sqliteHelper->createConnection();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db->close();

        unset($this->db);
    }

    public function testExplicitPkOnAutoIncrement(): void
    {
        $this->checkFixture($this->db, 'customer', true);

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
