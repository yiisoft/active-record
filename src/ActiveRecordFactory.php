<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Closure;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Definitions\Exception\NotInstantiableException;
use Yiisoft\Factory\Factory;
use Yiisoft\Factory\NotFoundException;

/**
 * @psalm-import-type ARClass from ActiveQueryInterface
 */
final class ActiveRecordFactory
{
    public function __construct(private Factory $factory)
    {
    }

    /**
     * Allows you to create an active record instance through the factory.
     *
     * @param string $arClass active record class.
     * @param ConnectionInterface|null $db the database connection used for creating active record instances.
     *
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws NotInstantiableException
     * @return ActiveRecordInterface
     *
     * @psalm-template T of ActiveRecordInterface
     * @psalm-param class-string<T> $arClass
     * @psalm-return T
     */
    public function createAR(
        string $arClass,
        ConnectionInterface $db = null
    ): ActiveRecordInterface {
        $params = [];
        $params['class'] = $arClass;

        if ($db !== null) {
            $params['__construct()']['db'] = $db;
        }

        return $this->factory->create($params);
    }

    /**
     * Allows you to create an active query instance through the factory.
     *
     * @param string|ActiveRecordInterface|Closure $arClass the active record class, active record instance or closure
     * returning active record instance.
     * @param string $queryClass custom query active query class.
     * @param ConnectionInterface|null $db the database connection used for creating active query instances.
     *
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws NotInstantiableException
     *
     * @psalm-param ARClass $arClass
     */
    public function createQueryTo(
        string|ActiveRecordInterface|Closure $arClass,
        string $queryClass = ActiveQuery::class,
        ConnectionInterface $db = null
    ): ActiveQueryInterface {
        $params = [
            'class' => $queryClass,
            '__construct()' => [
                'arClass' => $arClass,
            ],
        ];

        if ($db !== null) {
            $params['__construct()']['db'] = $db;
        }

        return $this->factory->create($params);
    }
}
