<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFindFactoryTest as AbstractActiveQueryFindFactoryTest;

/**
 * @group mysql
 */
final class ActiveQueryFindFactoryTest extends AbstractActiveQueryFindFactoryTest
{
    protected string $driverName = 'mysql';

    public function setUp(): void
    {
        parent::setUp();

        $this->arFactory->withConnection($this->mysqlConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->mysqlConnection->close();

        unset($this->arFactory, $this->mysqlConnection);
    }
}
