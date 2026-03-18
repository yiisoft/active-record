<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use InvalidArgumentException;
use Yiisoft\Factory\Factory;
use Yiisoft\Factory\StrictFactory;

/**
 * ActiveRecordFactory is a factory class for creating active record instances.
 * It is used by {@see FactoryTrait} trait.
 */
final class ActiveRecordFactory
{
    private const DEFAULT = '';

    /** @var (Factory|StrictFactory)[] $factories */
    private static array $factories = [];

    /**
     * Returns all factories. If the default factory is set, it will be returned with an empty string key.
     *
     * @return (Factory|StrictFactory)[]
     */
    public static function all(): array
    {
        return self::$factories;
    }

    /**
     * Clear all registered factories.
     */
    public static function clear(): void
    {
        self::$factories = [];
    }

    /**
     * Creates an active record instance.
     *
     * @param class-string<ActiveRecordInterface> $className The class name of the active record to be created.
     */
    public static function create(string $className): ActiveRecordInterface
    {
        return self::get($className)->create($className);
    }

    /**
     * Returns the factory for the given class name or the default factory if none is found.
     *
     * @param ?class-string<ActiveRecordInterface> $className The class name of the active record to be checked
     * or `null` to get the default factory.
     */
    public static function get(?string $className = null): Factory|StrictFactory
    {
        if ($className !== null) {
            return self::$factories[$className]
                ?? self::$factories[self::DEFAULT]
                ?? throw new InvalidArgumentException("Factory for class '$className' not found");
        }

        return self::$factories[self::DEFAULT]
            ?? throw new InvalidArgumentException("Default factory not found");
    }

    /**
     * Checks if a factory for the given class name exists.
     *
     * @param ?class-string<ActiveRecordInterface> $className The class name of the active record to be checked
     * or `null` to check default factory.
     */
    public static function has(?string $className = null): bool
    {
        return isset(self::$factories[$className ?? self::DEFAULT]);
    }

    /**
     * Removes a factory by name.
     *
     * @param ?class-string<ActiveRecordInterface> $className The class name of the active record to be removed
     * or `null` to remove default factory.
     */
    public static function remove(?string $className = null): void
    {
        unset(self::$factories[$className ?? self::DEFAULT]);
    }

    /**
     * Sets a factory by name.
     *
     * @param Factory|StrictFactory $factory The factory to be set.
     * @param ?class-string<ActiveRecordInterface> $className The class name of the active record to be set
     * or `null` to set default factory.
     */
    public static function set(Factory|StrictFactory $factory, ?string $className = null): void
    {
        self::$factories[$className ?? self::DEFAULT] = $factory;
    }
}
