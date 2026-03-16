<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Factory\Factory;
use InvalidArgumentException;

/**
 * ActiveRecordFactory is a factory class for creating active record instances.
 * It is used by {@see FactoryTrait} trait.
 */
final class ActiveRecordFactory
{
    private const DEFAULT = '';

    /** @var Factory[] $factories */
    private static array $factories = [];

    public static function setFactory(Factory $factory, string $className = self::DEFAULT): void
    {
        self::$factories[$className] = $factory;
    }

    public static function create(string $className): ActiveRecordInterface
    {
        return self::getFactory($className)->create($className);
    }

    private static function getFactory(string $className): Factory
    {
        return self::$factories[$className]
            ?? self::$factories[self::DEFAULT]
            ?? throw new InvalidArgumentException("Factory for class '$className' not found");
    }
}
