<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use PHPUnit\Framework\TestCase as AbstractTestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use ReflectionException;
use ReflectionObject;
use Yiisoft\ActiveRecord\ActiveRecordFactory;
use Yiisoft\ActiveRecord\Tests\Stubs\Redis\Customer;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Helper\Dsn;
use Yiisoft\Db\Mssql\Connection as MssqlConnection;
use Yiisoft\Db\Mssql\Dsn as MssqlDsn;
use Yiisoft\Db\Mysql\Connection as MysqlConnection;
use Yiisoft\Db\Pgsql\Connection as PgsqlConnection;
use Yiisoft\Db\Redis\Connection as RedisConnection;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Di\Container;
use Yiisoft\Factory\Definitions\Reference;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\Log\Logger;
use Yiisoft\Profiler\Profiler;

use function array_merge;
use function explode;
use function file_get_contents;
use function preg_replace;
use function str_replace;
use function trim;

class TestCase extends AbstractTestCase
{
    protected ContainerInterface $container;
    protected MssqlConnection $mssqlConnection;
    protected MysqlConnection $mysqlConnection;
    protected PgsqlConnection $pgsqlConnection;
    protected RedisConnection $redisConnection;
    protected SqliteConnection $sqliteConnection;
    protected ActiveRecordFactory $arFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configContainer();
    }

    protected function configContainer(): void
    {
        $this->container = new Container($this->config());

        $this->mssqlConnection = $this->container->get(MssqlConnection::class);
        $this->mysqlConnection = $this->container->get(MysqlConnection::class);
        $this->pgsqlConnection = $this->container->get(PgsqlConnection::class);
        $this->redisConnection = $this->container->get(RedisConnection::class);
        $this->sqliteConnection = $this->container->get(SqliteConnection::class);
        $this->arFactory = $this->container->get(ActiveRecordFactory::class);
    }

    protected function customerData(): void
    {
        $customer = new Customer($this->redisConnection);
        $customer->setAttributes(
            [
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 1,
                'profile_id', 1
            ]
        );
        $customer->save();

        $customer = new Customer($this->redisConnection);
        $customer->setAttributes(
            [
                'email' => 'user2@example.com',
                'name' => 'user2',
                'address' => 'address2',
                'status' => 1,
                'profile_id' => null
            ]
        );
        $customer->save();

        $customer = new Customer($this->redisConnection);
        $customer->setAttributes(
            [
                'email' => 'user3@example.com',
                'name' => 'user3',
                'address' => 'address3',
                'status' => 2,
                'profile_id' => 2
            ]
        );
        $customer->save();
    }

    /**
     * Invokes a inaccessible method.
     *
     * @param $object
     * @param $method
     * @param array $args
     * @param bool $revoke whether to make method inaccessible after execution
     *
     * @throws ReflectionException
     *
     * @return mixed
     */
    protected function invokeMethod($object, $method, $args = [], $revoke = true)
    {
        $reflection = new ReflectionObject($object);

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
            case 'mssql':
                $fixture = $this->params()['yiisoft/db-mssql']['fixture'];
                break;
            case 'mysql':
                $fixture = $this->params()['yiisoft/db-mysql']['fixture'];
                break;
            case 'pgsql':
                $fixture = $this->params()['yiisoft/db-pgsql']['fixture'];
                break;
            case 'sqlite':
                $fixture = $this->params()['yiisoft/db-sqlite']['fixture'];
                break;
            case 'redis':
                return;
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
                return str_replace(['\\[', '\\]'], ['[', ']'], preg_replace('/(\[\[)|((?<!(\[))]])/', '"', $sql));
            case 'mssql':
                return str_replace(['[[', ']]'], ['[', ']'], $sql);
            default:
                return $sql;
        }
    }

    private function config(): array
    {
        $params = $this->params();

        return [
            CacheInterface::class => [
                '__class' => Cache::class,
                '__construct()' => [
                    Reference::to(ArrayCache::class)
                ]
            ],

            LoggerInterface::class => Logger::class,

            Profiler::class => [
                '__class' => Profiler::class,
                '__construct()' => [
                    Reference::to(LoggerInterface::class)
                ]
            ],

            ListenerProviderInterface::class => Provider::class,

            EventDispatcherInterface::class => Dispatcher::class,

            MssqlConnection::class => [
                '__class' => MssqlConnection::class,
                '__construct()' => [
                    Reference::to(CacheInterface::class),
                    Reference::to(LoggerInterface::class),
                    Reference::to(Profiler::class),
                    $params['yiisoft/db-mssql']['dsn']
                ],
                'setUsername()' => [$params['yiisoft/db-mssql']['username']],
                'setPassword()' => [$params['yiisoft/db-mssql']['password']]
            ],

            MysqlConnection::class => [
                '__class' => MysqlConnection::class,
                '__construct()' => [
                    Reference::to(CacheInterface::class),
                    Reference::to(LoggerInterface::class),
                    Reference::to(Profiler::class),
                    $params['yiisoft/db-mysql']['dsn']
                ],
                'setUsername()' => [$params['yiisoft/db-mysql']['username']],
                'setPassword()' => [$params['yiisoft/db-mysql']['password']]
            ],

            PgsqlConnection::class => [
                '__class' => PgsqlConnection::class,
                '__construct()' => [
                    Reference::to(CacheInterface::class),
                    Reference::to(LoggerInterface::class),
                    Reference::to(Profiler::class),
                    $params['yiisoft/db-pgsql']['dsn']
                ],
                'setUsername()' => [$params['yiisoft/db-pgsql']['username']],
                'setPassword()' => [$params['yiisoft/db-pgsql']['password']]
            ],

            RedisConnection::class => [
                '__class' => RedisConnection::class,
                '__construct()' => [
                    Reference::to(EventDispatcherInterface::class),
                    Reference::to(LoggerInterface::class),
                ],
                'database()' => [$params['yiisoft/db-redis']['database']]
            ],

            SqliteConnection::class => [
                '__class' => SqliteConnection::class,
                '__construct()' => [
                    Reference::to(CacheInterface::class),
                    Reference::to(LoggerInterface::class),
                    Reference::to(Profiler::class),
                    $params['yiisoft/db-sqlite']['dsn']
                ]
            ],

            ActiveRecordFactory::class => [
                '__class' => ActiveRecordFactory::class,
                '__construct()' => [
                    null,
                    [ConnectionInterface::class => Reference::to(SqliteConnection::class)],
                ],
            ],
        ];
    }

    private function params(): array
    {
        return [
            'yiisoft/db-mssql' => [
                'dsn' => (new MssqlDsn('sqlsrv', '127.0.0.1', 'yiitest', '1433'))->getDsn(),
                'username' => 'SA',
                'password' => 'YourStrong!Passw0rd',
                'fixture' => __DIR__ . '/Data/mssql.sql',
            ],
            'yiisoft/db-mysql' => [
                'dsn' => (new Dsn('mysql', '127.0.0.1', 'yiitest', '3306'))->getDsn(),
                'username' => 'root',
                'password' => 'root',
                'fixture' => __DIR__ . '/Data/mysql.sql',
            ],
            'yiisoft/db-pgsql' => [
                'dsn' => (new Dsn('pgsql', '127.0.0.1', 'yiitest', '5432'))->getDsn(),
                'username' => 'root',
                'password' => 'root',
                'fixture' => __DIR__ . '/Data/pgsql.sql',
            ],
            'yiisoft/db-redis' => [
                'database' => 0,
            ],
            'yiisoft/db-sqlite' => [
                'dsn' => 'sqlite:' . __DIR__ . '/Data/Runtime/yiitest.sq3',
                'fixture' => __DIR__ . '/Data/sqlite.sql'
            ]
        ];
    }
}
