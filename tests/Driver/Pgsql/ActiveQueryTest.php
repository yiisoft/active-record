<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Pgsql;

use Yiisoft\ActiveRecord\Tests\Support\PgsqlHelper;

final class ActiveQueryTest extends \Yiisoft\ActiveRecord\Tests\ActiveQueryTest
{
    public function setUp(): void
    {
        parent::setUp();

        $pgsqlHelper = new PgsqlHelper();
        $this->db = $pgsqlHelper->createConnection();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->db->close();

        unset($this->db);
    }
}
