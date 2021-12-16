<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\ActiveRecord\Redis\ActiveQuery as RedisActiveQuery;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Factory\Factory;

final class ActiveRecordFactory
{
    /**
     * @var Factory
     */
    private Factory $factory;

    public function __construct(Factory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Allows you to create an active record instance through the factory.
     *
     * @param string $arClass active record class.
     * @param ConnectionInterface|null $db the database connection used for creating active record instances.
     *
     * @throws InvalidConfigException
     *
     * @return ActiveRecordInterface
     */
    public function createAR(string $arClass, ConnectionInterface $db = null): ActiveRecordInterface
    {
        if ($db === null) {
            $activeRecord = $this->factory->create(['class' => $arClass]);
        } else {
            $activeRecord = $this->factory->create(['class' => $arClass, '__construct()' => ['db' => $db]]);
        }

        return $activeRecord;
    }

    /**
     * Allows you to create an active query instance through the factory.
     *
     * @param string $arClass active record class.
     * @param string|null $queryClass custom query active query class.
     * @param ConnectionInterface $connection the database connection used for creating active query instances.
     *
     * @throws InvalidConfigException
     *
     * @return ActiveQueryInterface
     */
    public function createQueryTo(
        string $arClass,
        string $queryClass = null,
        ConnectionInterface $db = null
    ): ActiveQueryInterface {
        if ($db === null) {
            $activeQuery = $this->factory->create(
                [
                    'class' => $queryClass ?? ActiveQuery::class,
                    '__construct()' => [
                        $arClass,
                    ],
                ],
            );
        } else {
            $activeQuery = $this->factory->create(
                [
                    'class' => $queryClass ?? ActiveQuery::class,
                    '__construct()' => [
                        $arClass,
                        $db,
                    ],
                ],
            );
        }

        return $activeQuery;
    }

    /**
     * Allows you to create an redis active query instance through the factory.
     *
     * @param string $arClass active record class.
     * @param string|null $queryClass custom query active query class.
     * @param ConnectionInterface $connection the database connection used for creating active query instances.
     *
     * @throws InvalidConfigException
     *
     * @return ActiveQueryInterface
     */
    public function createRedisQueryTo(
        string $arClass,
        string $queryClass = null,
        ConnectionInterface $db = null,
    ): ActiveQueryInterface {
        if ($db === null) {
            $activeQuery = $this->factory->create(
                [
                    'class' => $queryClass ?? RedisActiveQuery::class,
                    '__construct()' => [
                        $arClass,
                    ],
                ],
            );
        } else {
            $activeQuery = $this->factory->create(
                [
                    'class' => $queryClass ?? RedisActiveQuery::class,
                    '__construct()' => [
                        $arClass,
                        $db,
                    ],
                ],
            );
        }

        return $activeQuery;
    }
}
