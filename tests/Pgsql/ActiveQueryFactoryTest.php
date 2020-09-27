<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Pgsql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFactoryTest as AbstractActiveQueryFactoryTest;

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
}
