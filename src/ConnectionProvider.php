<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * ConnectionProvider is used to manage DB connections.
 */
final class ConnectionProvider
{
    public const DEFAULT = 'default';

    /** @var ConnectionInterface[] $connections */
    private static array $connections = [];

    /**
     * Returns all connections.
     *
     * @return ConnectionInterface[]
     */
    public static function all(): array
    {
        return self::$connections;
    }

    /**
     * Returns a connection by name.
     */
    public static function get(string $name = self::DEFAULT): ConnectionInterface
    {
        return self::$connections[$name];
    }

    /**
     * Checks if a connection name exists.
     */
    public static function has(string $name = self::DEFAULT): bool
    {
        return isset(self::$connections[$name]);
    }

    /**
     * Sets a connection by name.
     */
    public static function set(ConnectionInterface $connection, string $name = self::DEFAULT): void
    {
        self::$connections[$name] = $connection;
    }
}
