<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mssql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFactoryTest as AbstractActiveQueryFactoryTest;

/**
 * @group mssql
 */
final class ActiveQueryFactoryTest extends AbstractActiveQueryFactoryTest
{
    protected ?string $driverName = 'mssql';

    public function setUp(): void
    {
        parent::setUp();

        $this->arFactory->withConnection($this->mssqlConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->mssqlConnection->close();

        unset($this->arFactory, $this->mssqlConnection);
    }
}
