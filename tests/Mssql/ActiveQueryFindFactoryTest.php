<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mssql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFindFactoryTest as AbstractActiveQueryFindFactoryTest;

/**
 * @group mssql
 */
final class ActiveQueryFindFactoryTest extends AbstractActiveQueryFindFactoryTest
{
    protected string $driverName = 'mssql';

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
