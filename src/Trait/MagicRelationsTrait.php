<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Error;
use ReflectionException;
use ReflectionMethod;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Exception\InvalidArgumentException;

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
     * Returns the relation object with the specified name.
     *
     * A relation is defined by a getter method which returns an {@see ActiveQueryInterface} object.
     *
     * It can be declared in either the Active Record class itself or one of its behaviors.
     *
     * @param string $name the relation name, e.g. `orders` for a relation defined via `getOrders()` method
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

        try {
            /** the relation could be defined in a behavior */
            $relation = $this->$getter();
        } catch (Error) {
            if ($throwException) {
                throw new InvalidArgumentException(static::class . ' has no relation named "' . $name . '".');
            }

            return null;
        }

        if (!$relation instanceof ActiveQueryInterface) {
            if ($throwException) {
                throw new InvalidArgumentException(static::class . ' has no relation named "' . $name . '".');
            }

            return null;
        }

        if (method_exists($this, $getter)) {
            /** relation name is case sensitive, trying to validate it when the relation is defined within this class */
            $method = new ReflectionMethod($this, $getter);
            $realName = lcfirst(substr($method->getName(), 3));

            if ($realName !== $name) {
                if ($throwException) {
                    throw new InvalidArgumentException(
                        'Relation names are case sensitive. ' . static::class
                        . " has a relation named \"$realName\" instead of \"$name\"."
                    );
                }

                return null;
            }
        }

        return $relation;
    }
}
