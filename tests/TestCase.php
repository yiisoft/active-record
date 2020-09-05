<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use PHPUnit\Framework\TestCase as AbstractTestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionPool;
use Yiisoft\Db\Mssql\Connection as MssqlConnection;
use Yiisoft\Db\Mysql\Connection as MysqlConnection;
use Yiisoft\Db\Pgsql\Connection as PgsqlConnection;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Di\Container;
use Yiisoft\Db\Helper\Dsn;
use Yiisoft\Log\Target\File\FileRotator;
use Yiisoft\Log\Target\File\FileRotatorInterface;
use Yiisoft\Log\Target\File\FileTarget;
use Yiisoft\Log\Logger;
use Yiisoft\Profiler\Profiler;

class TestCase extends AbstractTestCase
{
    protected ?MssqlConnection $mssqlConnection = null;
    protected ?MysqlConnection $mysqlConnection = null;
    protected ?PgsqlConnection $pgsqlConnection = null;
    protected ?SqliteConnection $sqliteConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configContainer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function configContainer(): void
    {
        $this->container = new Container($this->config());

        $this->mysqlConnection = $this->container->get(MysqlConnection::class);
        $this->pgsqlConnection = $this->container->get(PgsqlConnection::class);
        $this->sqliteConnection = $this->container->get(SqliteConnection::class);
    }

    /**
     * Invokes a inaccessible method.
     *
     * @param $object
     * @param $method
     * @param array $args
     * @param bool $revoke whether to make method inaccessible after execution
     *
     * @return mixed
     */
    protected function invokeMethod($object, $method, $args = [], $revoke = true)
    {
        $reflection = new \ReflectionObject($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        $result = $method->invokeArgs($object, $args);

        if ($revoke) {
            $method->setAccessible(false);
        }

        return $result;
    }

    protected function loadFixture(ConnectionInterface $db): void
    {
        switch ($this->driverName) {
            case 'mysql':
                $fixture = $this->params()['yiisoft/db-mysql']['fixture'];
                break;
            case 'pgsql':
                $fixture = $this->params()['yiisoft/db-pgsql']['fixture'];
                break;
            case 'sqlite':
                $fixture = $this->params()['yiisoft/db-sqlite']['fixture'];
                break;
        }

        if ($db->isActive()) {
            $db->close();
        }

        $db->open();

        if ($this->driverName === 'oci') {
            [$drops, $creates] = explode('/* STATEMENTS */', file_get_contents($fixture), 2);
            [$statements, $triggers, $data] = explode('/* TRIGGERS */', $creates, 3);
            $lines = array_merge(
                explode('--', $drops),
                explode(';', $statements),
                explode('/', $triggers),
                explode(';', $data)
            );
        } else {
            $lines = explode(';', file_get_contents($fixture));
        }

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $db->getPDO()->exec($line);
            }
        }
    }

    /**
     * Adjust dbms specific escaping.
     *
     * @param $sql
     *
     * @return mixed
     */
    protected function replaceQuotes($sql)
    {
        switch ($this->driverName) {
            case 'mysql':
            case 'sqlite':
                return str_replace(['[[', ']]'], '`', $sql);
            case 'oci':
                return str_replace(['[[', ']]'], '"', $sql);
            case 'pgsql':
                // more complex replacement needed to not conflict with postgres array syntax
                return str_replace(['\\[', '\\]'], ['[', ']'], preg_replace('/(\[\[)|((?<!(\[))\]\])/', '"', $sql));
            case 'sqlsrv':
                return str_replace(['[[', ']]'], ['[', ']'], $sql);
            default:
                return $sql;
        }
    }

    private function config(): array
    {
        $params = $this->params();

        return [
            Aliases::class => [
                '@root' => dirname(__DIR__, 1),
                '@fixtures' => '@root/tests/Data/Fixtures',
                '@runtime' => '@root/tests/Data/Runtime',
            ],

            CacheInterface::class => static function (ContainerInterface $container) {
                return new Cache(new ArrayCache());
            },

            FileRotatorInterface::class => static function () {
                return new FileRotator(10);
            },

            LoggerInterface::class => Logger::class,

            Profiler::class => static function (ContainerInterface $container) {
                return new Profiler($container->get(LoggerInterface::class));
            },

            MysqlConnection::class => static function (ContainerInterface $container) use ($params) {
                $aliases = $container->get(Aliases::class);
                $cache = $container->get(CacheInterface::class);
                $logger = $container->get(LoggerInterface::class);
                $profiler = $container->get(Profiler::class);

                $dsn = new Dsn(
                    $params['yiisoft/db-mysql']['dsn']['driver'],
                    $params['yiisoft/db-mysql']['dsn']['host'],
                    $params['yiisoft/db-mysql']['dsn']['dbname'],
                    $params['yiisoft/db-mysql']['dsn']['port'],
                );

                $db = new MysqlConnection($cache, $logger, $profiler, $dsn->getDsn());

                $db->setUsername('root');
                $db->setPassword('root');

                ConnectionPool::setConnectionsPool('mysql', $db);

                return $db;
            },

            PgsqlConnection::class => static function (ContainerInterface $container) {
                $aliases = $container->get(Aliases::class);
                $cache = $container->get(CacheInterface::class);
                $logger = $container->get(LoggerInterface::class);
                $profiler = $container->get(Profiler::class);

                $dsn = new Dsn(
                    $params['yiisoft/db-pgsql']['dsn']['driver'],
                    $params['yiisoft/db-pgsql']['dsn']['host'],
                    $params['yiisoft/db-pgsql']['dsn']['dbname'],
                    $params['yiisoft/db-pgsql']['dsn']['port'],
                );

                $db = new PgsqlConnection($cache, $logger, $profiler, $dsn->getDsn());

                $db->setUsername('root');
                $db->setPassword('root');

                ConnectionPool::setConnectionsPool('pgsql', $db);

                return $db;
            },

            SqliteConnection::class => static function (ContainerInterface $container) use ($params) {
                $aliases = $container->get(Aliases::class);
                $cache = $container->get(CacheInterface::class);
                $logger = $container->get(LoggerInterface::class);
                $profiler = $container->get(Profiler::class);


                $db = new SqliteConnection(
                    $cache,
                    $logger,
                    $profiler,
                    'sqlite:' . $aliases->get('@runtime/yiitest.sq3')
                );

                ConnectionPool::setConnectionsPool('sqlite', $db);

                return $db;
            },
        ];
    }

    private function params(): array
    {
        return [
            'yiisoft/db-mssql' => [
                'dsn' => [
                    'driver' => 'sqlsrv',
                    'server' => '127.0.0.1',
                    'database' => 'yiitest',
                    'port' => '1433'
                ],
                'username' => 'SA',
                'password' => 'YourStrong!Passw0rd',
                'fixture' => __DIR__ . '/Data/mssql.sql',
            ],
            'yiisoft/db-mysql' => [
                'dsn' => [
                    'driver' => 'mysql',
                    'host' => '127.0.0.1',
                    'dbname' => 'yiitest',
                    'port' => '3306'
                ],
                'username' => 'root',
                'password' => 'root',
                'fixture' => __DIR__ . '/Data/mysql.sql',
            ],
            'yiisoft/db-pgsql' => [
                'dsn' => [
                    'driver' => 'pgsql',
                    'host' => '127.0.0.1',
                    'dbname' => 'yiitest',
                    'port' => '5432'
                ],
                'username' => 'root',
                'password' => 'root',
                'fixture' => __DIR__ . '/Data/postgres.sql',
            ],
            'yiisoft/db-sqlite' => [
                'fixture' => __DIR__ . '/Data/sqlite.sql'
            ]
        ];
    }
}
