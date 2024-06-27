<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    public function setUp(): void
    {
        parent::setUp();

        $pgsqlHelper = new PgsqlHelper();
        ConnectionProvider::set($pgsqlHelper->createConnection());
        $this->arFactory = $pgsqlHelper->createARFactory($this->db());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        unset($this->arFactory);

        ConnectionProvider::unset();
    }
}
