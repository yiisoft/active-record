<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\Tests\ActiveDataProviderFactoryTest as AbstractActiveDataProviderFactoryTest;

/**
 * @group mysql
 */
final class ActiveDataProviderFactoryTest extends AbstractActiveDataProviderFactoryTest
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

        unset($this->mysqlConnection);
    }
}
