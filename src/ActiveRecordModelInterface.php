<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;

interface ActiveRecordModelInterface
{
    /**
     * Returns the ActiveRecord instance.
     */
    public function activeRecord(): ActiveRecordInterface;

    /**
     * Returns the database connection used by the Active Record instance.
     */
    public function db(): ConnectionInterface;

    /**
     * Returns the relation query object with the specified name.
     *
     * A relation is defined by a getter method which returns an object implementing the {@see ActiveQueryInterface}
     * (normally this would be a relational {@see ActiveQuery} object).
     *
     * Relations can be defined using {@see ActiveRecordInterface::hasOne()} and {@see ActiveRecordInterface::hasMany()}
     * methods. For example:
     *
     * ```php
     * public function relationQuery(string $name): ActiveQueryInterface
     * {
     *     return match ($name) {
     *         'orders' => $this->activeRecord()->hasMany(Order::class, ['customer_id' => 'id']),
     *         'country' => $this->activeRecord()->hasOne(Country::class, ['id' => 'country_id']),
     *         default => parent::relationQuery($name),
     *     };
     * }
     * ```
     *
     * @param string $name The relation name, for example, `orders` (case-sensitive).
     *
     * @throws InvalidArgumentException
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function relationQuery(string $name): ActiveQueryInterface;

    /**
     * Return the name of the table associated with this AR class.
     *
     * ```php
     * final class User extends ActiveRecordModel
     * {
     *     public string const TABLE_NAME = 'user';
     *
     *     public function tableName(): string
     *     {
     *          return self::TABLE_NAME;
     *     }
     * }
     */
    public function tableName(): string;

    /**
     * Sets the value of the named property.
     *
     * @param string $name The property name.
     * @param mixed $value The property value.
     *
     * @internal This method is for internal use only.
     */
    public function populateProperty(string $name, mixed $value): void;

    /**
     * Returns property values.
     *
     * @throws Exception
     * @throws InvalidConfigException
     *
     * @return array Property values (name => value).
     * @psalm-return array<string, mixed>
     *
     * @internal This method is for internal use only.
     */
    public function propertyValues(): array;
}
