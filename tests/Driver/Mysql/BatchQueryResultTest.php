<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mysql;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\MysqlHelper;

final class BatchQueryResultTest extends \Yiisoft\ActiveRecord\Tests\BatchQueryResultTest
{
    public function setUp(): void
    {
        parent::setUp();

        $mysqlHelper = new MysqlHelper();
        ConnectionProvider::set($mysqlHelper->createConnection());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        ConnectionProvider::unset();
    }
}
