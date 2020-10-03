<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Redis;

use Yiisoft\ActiveRecord\Tests\RedisActiveQueryFindTest as AbstractRedisActiveQueryFindTest;

/**
 * @group redis
 */
final class ActiveQueryFindTest extends AbstractRedisActiveQueryFindTest
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
