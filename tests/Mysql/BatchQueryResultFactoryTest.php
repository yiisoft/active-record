<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Mysql;

use Yiisoft\ActiveRecord\Tests\BatchQueryResultFactoryTest as AbstractBatchQueryResultFactoryTest;

/**
 * @group mysql
 */
final class BatchQueryResultFactoryTest extends AbstractBatchQueryResultFactoryTest
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
