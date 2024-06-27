<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Oracle;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\OracleHelper;

final class ActiveRecordFactoryTest extends \Yiisoft\ActiveRecord\Tests\ActiveRecordFactoryTest
{
    public function setUp(): void
    {
        parent::setUp();

        $oracleHelper = new OracleHelper();
        ConnectionProvider::set($oracleHelper->createConnection());
        $this->arFactory = $oracleHelper->createARFactory($this->db());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db()->close();

        unset($this->arFactory);

        ConnectionProvider::unset();
    }
}
