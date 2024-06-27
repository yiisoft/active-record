<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Mssql;

use Yiisoft\ActiveRecord\Tests\Support\MssqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new MssqlHelper())->createConnection();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $mssqlHelper = new MssqlHelper();
        $this->arFactory = $mssqlHelper->createARFactory($this->db());
    }

    protected function tearDown(): void
    {
        unset($this->arFactory);

        parent::tearDown();
    }
}
