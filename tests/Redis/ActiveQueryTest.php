<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Redis;

use Yiisoft\ActiveRecord\Tests\RedisActiveQueryTest as AbstractRedisActiveQueryTest;

/**
 * @group redis
 */
final class ActiveQueryTest extends AbstractRedisActiveQueryTest
{
    protected string $driverName = 'redis';

    public function setUp(): void
    {
        parent::setUp();

        $this->redisConnection->open();
        $this->redisConnection->flushdb();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->redisConnection->close();

        unset($this->redisConnection);
    }
}
