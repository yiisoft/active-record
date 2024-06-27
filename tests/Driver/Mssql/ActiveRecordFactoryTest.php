<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mssql;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\MssqlHelper;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    public function setUp(): void
    {
        parent::setUp();

        $mssqlHelper = new MssqlHelper();
        ConnectionProvider::set($mssqlHelper->createConnection());

        $this->arFactory = $mssqlHelper->createARFactory(ConnectionProvider::get());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        unset($this->arFactory);

        ConnectionProvider::unset();
    }
}
