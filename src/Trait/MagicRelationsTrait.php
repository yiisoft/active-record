<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use ReflectionException;
use ReflectionMethod;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Exception\InvalidArgumentException;

use function is_a;
use function lcfirst;
use function method_exists;
use function substr;
use function ucfirst;

/**
 * Trait to define {@see ActiveRecordInterface::relationQuery()} method to access relation queries of an ActiveRecord
 * instance.
 */
trait MagicRelationsTrait
{
    /**
     * @inheritdoc
     *
     * A relation is defined by a getter method which has prefix `get` and suffix `Query` and returns an object
     * implementing the {@see ActiveQueryInterface}. Normally this would be a relational {@see ActiveQuery} object.
     *
     * For example, a relation named `orders` is defined using the following getter method:
     *
     * ```php
     * public function getOrders(): ActiveQueryInterface
     * {
     *    return $this->hasMany(Order::class, ['customer_id' => 'id']);
     * }
     * ```
     *
     * @param string $name The relation name, for example `orders` for a relation defined via `getOrdersQuery()` method
     * (case-sensitive).
     * @param bool $throwException whether to throw exception if the relation does not exist.
     *
     * @throws InvalidArgumentException if the named relation does not exist.
     * @throws ReflectionException
     *
     * @return ActiveQueryInterface|null the relational query object. If the relation does not exist and
     * `$throwException` is `false`, `null` will be returned.
     */
    public function relationQuery(string $name, bool $throwException = true): ActiveQueryInterface|null
    {
        $getter = 'get' . ucfirst($name);

        if (!method_exists($this, $getter)) {
            if (!$throwException) {
                return null;
            }

            throw new InvalidArgumentException(static::class . ' has no relation named "' . $name . '".');
        }

        $method = new ReflectionMethod($this, $getter);
        $type = $method->getReturnType();

        if (
            $type === null
            || !is_a('\\' . $type->getName(), ActiveQueryInterface::class, true)
        ) {
            if (!$throwException) {
                return null;
            }

            $typeName = $type === null ? 'mixed' : $type->getName();

            throw new InvalidArgumentException(
                'Relation query method "' . static::class . '::' . $getter . '()" should return type "'
                . ActiveQueryInterface::class . '", but  returns "' . $typeName . '" type.'
            );
        }

        /** relation name is case sensitive, trying to validate it when the relation is defined within this class */
        $realName = lcfirst(substr($method->getName(), 3));

        if ($realName !== $name) {
            if (!$throwException) {
                return null;
            }

            throw new InvalidArgumentException(
                'Relation names are case sensitive. ' . static::class
                . " has a relation named \"$realName\" instead of \"$name\"."
            );
        }

        return $this->$getter();
    }
}
