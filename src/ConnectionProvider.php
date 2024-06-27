<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Db\Connection\ConnectionInterface;

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
     * Returns a connection by key.
     */
    public static function get(string $key = self::DEFAULT): ConnectionInterface
    {
        return self::$connections[$key];
    }

    /**
     * Sets a connection by key.
     */
    public static function set(ConnectionInterface $connection, string $key = self::DEFAULT): void
    {
        self::$connections[$key] = $connection;
    }

    /**
     * Unsets a connection by key.
     */
    public static function unset(string $key = self::DEFAULT): void
    {
        unset(self::$connections[$key]);
    }
}
