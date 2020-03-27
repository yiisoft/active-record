<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Yiisoft\ActiveRecord\Tests\Stubs\Connection\MysqlConnection;
use Yiisoft\ActiveRecord\Tests\Stubs\Connection\PgsqlConnection;
use Yiisoft\ActiveRecord\Tests\Stubs\Connection\SqliteConnection;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Connection\Connection;
use Yiisoft\Db\Connection\ConnectionPool;
use Yiisoft\Db\Helper\Dsn;
use Yiisoft\Log\Target\File\FileRotator;
use Yiisoft\Log\Target\File\FileRotatorInterface;
use Yiisoft\Log\Target\File\FileTarget;
use Yiisoft\Log\Logger;
use Yiisoft\Profiler\Profiler;

return [
    Aliases::class => [
        '@root' => dirname(__DIR__, 1),
        '@fixtures' => '@root/tests/data/fixtures',
        '@runtime' => '@root/tests/data/runtime',
    ],

    CacheInterface::class => static function (ContainerInterface $container) {
        return new Cache(new ArrayCache());
    },

    FileRotatorInterface::class => static function () {
        return new FileRotator(10);
    },

    LoggerInterface::class => static function (ContainerInterface $container) {
        $aliases = $container->get(Aliases::class);
        $fileRotator = $container->get(FileRotatorInterface::class);

        $fileTarget = new FileTarget(
            $aliases->get('@runtime/logs/app.log'),
            $fileRotator
        );

        $fileTarget->setLevels(
            [
                LogLevel::EMERGENCY,
                LogLevel::ERROR,
                LogLevel::WARNING,
                LogLevel::INFO,
                LogLevel::DEBUG
            ]
        );

        return new Logger([
            'file' => $fileTarget,
        ]);
    },

    Profiler::class => static function (ContainerInterface $container) {
        return new Profiler($container->get(LoggerInterface::class));
    },

    MysqlConnection::class => static function (ContainerInterface $container) {
        $aliases = $container->get(Aliases::class);
        $cache = $container->get(CacheInterface::class);
        $logger = $container->get(LoggerInterface::class);
        $profiler = $container->get(Profiler::class);
        $dsn = new Dsn('mysql', '127.0.0.1', 'yiitest', '3306');

        $db = new MysqlConnection($cache, $logger, $profiler, $dsn->getDsn());

        $db->setUsername('root');
        $db->setPassword('root');
        $db->fixture($aliases->get('@fixtures') . '/mysql.sql');

        ConnectionPool::setConnectionsPool('mysql', $db);

        return $db;
    },

    PgsqlConnection::class => static function (ContainerInterface $container) {
        $aliases = $container->get(Aliases::class);
        $cache = $container->get(CacheInterface::class);
        $logger = $container->get(LoggerInterface::class);
        $profiler = $container->get(Profiler::class);
        $dsn = new Dsn('pgsql', '127.0.0.1', 'yiitest', '5432');

        $db = new PgsqlConnection($cache, $logger, $profiler, $dsn->getDsn());

        $db->setUsername('root');
        $db->setPassword('root');
        $db->fixture($aliases->get('@fixtures') . '/pgsql.sql');

        ConnectionPool::setConnectionsPool('pgsql', $db);

        return $db;
    },

    SqliteConnection::class => static function (ContainerInterface $container) {
        $aliases = $container->get(Aliases::class);
        $cache = $container->get(CacheInterface::class);
        $logger = $container->get(LoggerInterface::class);
        $profiler = $container->get(Profiler::class);


        $db = new SqliteConnection($cache, $logger, $profiler, 'sqlite:' . $aliases->get('@runtime') . '/yiitest.sq3');
        $db->fixture($aliases->get('@fixtures') . '/sqlite.sql');

        ConnectionPool::setConnectionsPool('sqlite', $db);

        return $db;
    },
];
