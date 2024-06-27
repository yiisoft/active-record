<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mssql;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\MssqlHelper;

final class ActiveQueryTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryTest
{
    public function setUp(): void
    {
        parent::setUp();

        $mssqlHelper = new MssqlHelper();
        ConnectionProvider::set($mssqlHelper->createConnection());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        ConnectionProvider::unset();
    }
}
