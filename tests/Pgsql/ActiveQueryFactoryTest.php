<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFactoryTest as AbstractActiveQueryFactoryTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\BoolAR;

/**
 * @group pgsql
 */
final class ActiveQueryFactoryTest extends AbstractActiveQueryFactoryTest
{
    protected string $driverName = 'pgsql';

    public function setUp(): void
    {
        parent::setUp();

        $this->arFactory->withConnection($this->pgsqlConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pgsqlConnection->close();

        unset($this->arFactory, $this->pgsqlConnection);
    }

    /**
     * {@see https://github.com/yiisoft/yii2/issues/15482}
     */
    public function testEagerLoadingUsingStringIdentifiers(): void
    {
        $betaQuery = $this->arFactory->createQueryTo(Beta::class);

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

    public function testBooleanValues(): void
    {
        $this->loadFixture($this->pgsqlConnection);

        $command = $this->pgsqlConnection->createCommand();
        $command->batchInsert('bool_values', ['bool_col'], [[true], [false]])->execute();

        $boolARQuery = $this->arFactory->createQueryTo(BoolAR::class);

        $this->assertTrue($boolARQuery->where(['bool_col' => true])->one()->bool_col);
        $this->assertFalse($boolARQuery->where(['bool_col' => false])->one()->bool_col);

        $this->assertEquals(1, $boolARQuery->where('bool_col = TRUE')->count('*'));
        $this->assertEquals(1, $boolARQuery->where('bool_col = FALSE')->count('*'));
        $this->assertEquals(2, $boolARQuery->where('bool_col IN (TRUE, FALSE)')->count('*'));

        $this->assertEquals(1, $boolARQuery->where(['bool_col' => true])->count('*'));
        $this->assertEquals(1, $boolARQuery->where(['bool_col' => false])->count('*'));
        $this->assertEquals(2, $boolARQuery->where(['bool_col' => [true, false]])->count('*'));

        $this->assertEquals(1, $boolARQuery->where('bool_col = :bool_col', ['bool_col' => true])->count('*'));
        $this->assertEquals(1, $boolARQuery->where('bool_col = :bool_col', ['bool_col' => false])->count('*'));
    }
}
