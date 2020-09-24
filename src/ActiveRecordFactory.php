<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\ActiveRecord\Redis\ActiveQuery as RedisActiveQuery;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Factory\Factory;

final class ActiveRecordFactory extends Factory
{
    private ConnectionInterface $connection;

    /**
     * Allows you to create an active record instance through the factory.
     *
     * @param string $class active record class.
     *
     * @return ActiveRecordInterface
     */
    public function createAR(string $class): ActiveRecordInterface
    {
        return parent::create(
            [
                '__class' => $class
            ]
        );
    }

    /**
     * Allows you to create an active query instance through the factory.
     *
     * @param string $class active query class.
     *
     * @return ActiveQueryInterface
     */
    public function createQueryTo(string $class): ActiveQueryInterface
    {
        return parent::create(
            [
                '__class' => ActiveQuery::class,
                '__construct()' => [
                    $class
                ]
            ]
        );
    }

    /**
     * Allows you to create an redis active query instance through the factory.
     *
     * @param string $class active query class.
     *
     * @return ActiveQueryInterface
     */
    public function createRedisQueryTo(string $class): ActiveQueryInterface
    {
        return parent::create(
            [
                '__class' => RedisActiveQuery::class,
                '__construct()' => [
                    $class
                ]
            ]
        );
    }

    /**
     * Allows you to configure the connection that will be used in the factory, through
     * {@see ConnectionInterface::class}.
     *
     * @param ConnectionInterface $connection connection defined in container-di.
     */
    public function withConnection(ConnectionInterface $connection): void
    {
        $this->set(ConnectionInterface::class, $connection);
    }

    /**
     * Returns the active connection at the factory.
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->get(ConnectionInterface::class);
    }
}
