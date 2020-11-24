<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Oracle;

use Yiisoft\ActiveRecord\Tests\BatchQueryResultTest as AbstractBatchQueryResultTest;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @group oci
 */
final class BatchQueryResultTest extends AbstractBatchQueryResultTest
{
    protected string $driverName = 'oci';
    protected ConnectionInterface $db;

    public function setUp(): void
    {
        parent::setUp();

        $this->db = $this->ociConnection;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->ociConnection->close();

        unset($this->ociConnection);
    }
}
