<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Definitions\Exception\NotInstantiableException;
use Yiisoft\Factory\Factory;
use Yiisoft\Factory\NotFoundException;

final class ActiveRecordFactory
{
    public function __construct(private Factory $factory)
    {
    }

    /**
     * Allows you to create an active record instance through the factory.
     *
     * @param class-string $arClass active record class.
     * @param string $tableName The name of the table associated with this ActiveRecord class, if its empty string the
     * name will be generated automatically by calling {@see getTableName()} in the active record class.
     * @param ConnectionInterface|null $db the database connection used for creating active record instances.
     *
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws NotInstantiableException
     * @return ActiveRecordInterface
     *
     * @template T of ActiveRecordInterface
     * @template-typeof T $arClass
     * @psalm-template T of ActiveRecordInterface
     * @psalm-param class-string<T> $arClass
     * @psalm-assert class-string<T> $arClass
     * @psalm-return T
     */
    public function createAR(
        string $arClass,
        string $tableName = '',
        ConnectionInterface $db = null
    ): ActiveRecordInterface {
        $params = [];
        $params['class'] = $arClass;

        if ($tableName !== '') {
            $params['__construct()']['tableName'] = $tableName;
        }

        if ($db !== null) {
            $params['__construct()']['db'] = $db;
        }

        return $this->factory->create($params);
    }

    /**
     * Allows you to create an active query instance through the factory.
     *
     * @param string $arClass active record class.
     * @param string $tableName The name of the table associated with this ActiveRecord class, if its empty string the
     * name will be generated automatically by calling {@see getTableName()} in the active record class.
     * @param string $queryClass custom query active query class.
     * @param ConnectionInterface|null $db the database connection used for creating active query instances.
     *
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws NotInstantiableException
     */
    public function createQueryTo(
        string $arClass,
        string $tableName = '',
        string $queryClass = ActiveQuery::class,
        ConnectionInterface $db = null
    ): ActiveQueryInterface {
        $params = [
            'class' => $queryClass,
            '__construct()' => [
                'arClass' => $arClass,
            ],
        ];

        if ($tableName !== '') {
            $params['__construct()']['tableName'] = $tableName;
        }

        if ($db !== null) {
            $params['__construct()']['db'] = $db;
        }

        return $this->factory->create($params);
    }
}
