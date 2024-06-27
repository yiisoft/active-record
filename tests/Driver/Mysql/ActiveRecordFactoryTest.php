<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mysql;

use Yiisoft\ActiveRecord\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new MysqlHelper())->createConnection();
    }

    public function setUp(): void
    {
        parent::setUp();

        $mysqlHelper = new MysqlHelper();
        $this->arFactory = $mysqlHelper->createARFactory($this->db());
    }

    protected function tearDown(): void
    {
        unset($this->arFactory);

        parent::tearDown();
    }
}
