<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new PgsqlHelper())->createConnection();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $pgsqlHelper = new PgsqlHelper();
        $this->arFactory = $pgsqlHelper->createARFactory($this->db());
    }

    protected function tearDown(): void
    {
        unset($this->arFactory);

        parent::tearDown();
    }
}
