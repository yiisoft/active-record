<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    protected function createConnection(): ConnectionInterface
    {
        return (new OracleHelper())->createConnection();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $oracleHelper = new OracleHelper();
        $this->arFactory = $oracleHelper->createARFactory($this->db());
    }

    protected function tearDown(): void
    {
        unset($this->arFactory);

        parent::tearDown();
    }
}
