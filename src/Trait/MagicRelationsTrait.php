<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use ReflectionException;
use ReflectionMethod;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use InvalidArgumentException;

use function get_class_methods;
use function is_a;
use function lcfirst;
use function method_exists;
use function str_ends_with;
use function str_starts_with;
use function substr;
use function ucfirst;

/**
 * Trait to define {@see ActiveRecordInterface::relationQuery()} method to access relation queries of an ActiveRecord
 * instance. Also, it defines {@see ActiveRecordInterface::relationNames()} method to get names of all relations
 * defined in the ActiveRecord class.
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
     * public function getOrdersQuery(): ActiveQueryInterface
     * {
     *    return $this->hasMany(Order::class, ['customer_id' => 'id']);
     * }
     * ```
     *
     * @throws InvalidArgumentException If the named relation doesn't exist.
     * @throws ReflectionException
     */
    public function relationQuery(string $name): ActiveQueryInterface
    {
        $getter = 'get' . ucfirst($name) . 'Query';

        if (!method_exists($this, $getter)) {
            throw new InvalidArgumentException(static::class . ' has no relation named "' . $name . '".');
        }

        $method = new ReflectionMethod($this, $getter);
        $type = $method->getReturnType();

        if (
            $type === null
            || !is_a('\\' . $type->getName(), ActiveQueryInterface::class, true)
        ) {
            $typeName = $type === null ? 'mixed' : $type->getName();

            throw new InvalidArgumentException(
                'Relation query method "' . static::class . '::' . $getter . '()" should return type "'
                . ActiveQueryInterface::class . '", but  returns "' . $typeName . '" type.'
            );
        }

        /** Relation name is case-sensitive, trying to validate it when the relation is defined within this class. */
        $realName = lcfirst(substr($method->getName(), 3, -5));

        if ($realName !== $name) {
            throw new InvalidArgumentException(
                'Relation names are case sensitive. ' . static::class
                . " has a relation named \"$realName\" instead of \"$name\"."
            );
        }

        return $this->$getter();
    }

    /**
     * Returns names of all relations defined in the ActiveRecord class using getter methods with `get` prefix and
     * `Query` suffix.
     *
     * @throws ReflectionException
     * @return string[]
     */
    public function relationNames(): array
    {
        $methods = get_class_methods($this);

        $relations = [];

        foreach ($methods as $method) {
            if (str_starts_with($method, 'get') && str_ends_with($method, 'Query')) {
                $reflection = new ReflectionMethod($this, $method);
                $type = $reflection->getReturnType();

                if (
                    $type === null
                    || !is_a('\\' . $type->getName(), ActiveQueryInterface::class, true)
                ) {
                    continue;
                }

                $relations[] = lcfirst(substr($method, 3, -5));
            }
        }

        return $relations;
    }
}
