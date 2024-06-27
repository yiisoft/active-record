<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mysql;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\MysqlHelper;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    public function setUp(): void
    {
        parent::setUp();

        $mysqlHelper = new MysqlHelper();
        ConnectionProvider::set($mysqlHelper->createConnection());
        $this->arFactory = $mysqlHelper->createARFactory($this->db());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        unset($this->arFactory);

        ConnectionProvider::unset();
    }
}
