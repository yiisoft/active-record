<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\ActiveRecord\Redis\ActiveQuery as RedisActiveQuery;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Factory\Exceptions\InvalidConfigException;
use Yiisoft\Factory\Factory;

final class ActiveRecordFactory extends Factory
{
    /**
     * Allows you to create an active record instance through the factory.
     *
     * @param string $arClass active record class.
     *
     * @throws InvalidConfigException
     *
     * @return ActiveRecordInterface
     */
    public function createAR(string $arClass): ActiveRecordInterface
    {
        return $this->create(
            [
                '__class' => $arClass,
            ]
        );
    }

    /**
     * Allows you to create an active query instance through the factory.
     *
     * @param string $arClass active record class.
     * @param string|null $queryClass custom query active query class.
     *
     * @throws InvalidConfigException
     *
     * @return ActiveQueryInterface
     */
    public function createQueryTo(string $arClass, string $queryClass = null): ActiveQueryInterface
    {
        return $this->create(
            [
                '__class' => $queryClass ?? ActiveQuery::class,
                '__construct()' => [
                    $arClass,
                ],
            ]
        );
    }

    /**
     * Allows you to create an redis active query instance through the factory.
     *
     * @param string $arClass active record class.
     * @param string|null $queryClass custom query active query class.
     *
     * @throws InvalidConfigException
     *
     * @return ActiveQueryInterface
     */
    public function createRedisQueryTo(string $arClass, string $queryClass = null): ActiveQueryInterface
    {
        return $this->create(
            [
                '__class' => $queryClass ?? RedisActiveQuery::class,
                '__construct()' => [
                    $arClass,
                ],
            ]
        );
    }

    /**
     * Allows you to configure the connection that will be used in the factory, through
     * {@see ConnectionInterface::class}.
     *
     * @param ConnectionInterface $connection connection defined in container-di.
     *
     * @throws InvalidConfigException
     */
    public function withConnection(ConnectionInterface $connection): void
    {
        $this->set(ConnectionInterface::class, $connection);
    }

    /**
     * Returns the active connection at the factory.
     *
     * @throws InvalidConfigException
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->create(ConnectionInterface::class);
    }
}
