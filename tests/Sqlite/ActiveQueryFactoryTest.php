<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFactoryTest as AbstractActiveQueryFactoryTest;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Beta;

/**
 * @group sqlite
 */
final class ActiveQueryFactoryTest extends AbstractActiveQueryFactoryTest
{
    protected string $driverName = 'sqlite';

    public function setUp(): void
    {
        parent::setUp();

        $this->arFactory->withConnection($this->sqliteConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->sqliteConnection->close();

        unset($this->arFactory, $this->sqliteConnection);
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
}
