<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use PHPUnit\Framework\TestCase as AbstractTestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use Yiisoft\ActiveRecord\ActiveRecordFactory;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mssql\ConnectionPDO as ConnectionPDOMssql;
use Yiisoft\Db\Mssql\Dsn as MssqlDsn;
use Yiisoft\Db\Mssql\PDODriver as MssqlPDODriver;
use Yiisoft\Db\Mysql\ConnectionPDO as ConnectionPDOMysql;
use Yiisoft\Db\Mysql\Dsn as MysqlDsn;
use Yiisoft\Db\Mysql\PDODriver as MysqlPDODriver;
use Yiisoft\Db\Oracle\ConnectionPDO as ConnectionPDOOracle;
use Yiisoft\Db\Oracle\Dsn as OracleDsn;
use Yiisoft\Db\Oracle\PDODriver as OraclePDODriver;
use Yiisoft\Db\Pgsql\ConnectionPDO as ConnectionPDOPgsql;
use Yiisoft\Db\Pgsql\Dsn as PgsqlDsn;
use Yiisoft\Db\Pgsql\PDODriver as PgsqlPDODriver;
use Yiisoft\Db\Sqlite\ConnectionPDO as ConnectionPDOSqlite;
use Yiisoft\Db\Sqlite\Dsn as SqliteDsn;
use Yiisoft\Db\Sqlite\PDODriver as SqlitePDODriver;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\Factory\Factory;
use Yiisoft\Log\Logger;
use Yiisoft\Profiler\Profiler;
use Yiisoft\Profiler\ProfilerInterface;

use function array_merge;
use function explode;
use function file_get_contents;
use function preg_replace;
use function str_replace;
use function trim;

class TestCase extends AbstractTestCase
{
    protected ContainerInterface $container;
    protected ConnectionPDOMssql $mssqlConnection;
    protected ConnectionPDOMysql $mysqlConnection;
    protected ConnectionPDOOracle $ociConnection;
    protected ConnectionPDOPgsql $pgsqlConnection;
    protected ConnectionPDOSqlite $sqliteConnection;
    protected ActiveRecordFactory $arFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configContainer();
    }

    protected function configContainer(): void
    {
        $config = ContainerConfig::create()
            ->withDefinitions($this->config());
        $this->container = new Container($config);

        $this->mssqlConnection = $this->container->get(ConnectionPDOMssql::class);
        $this->mysqlConnection = $this->container->get(ConnectionPDOMysql::class);
        $this->ociConnection = $this->container->get(ConnectionPDOOracle::class);
        $this->pgsqlConnection = $this->container->get(ConnectionPDOPgsql::class);
        $this->sqliteConnection = $this->container->get(ConnectionPDOSqlite::class);
        $this->arFactory = $this->container->get(ActiveRecordFactory::class);
    }

    protected function checkFixture(ConnectionInterface $db, string $tablename, bool $reset = false): void
    {
        if ($db->getSchema()->getTableSchema($tablename, true) === null || $reset) {
            $this->loadFixture($db);
            $db->getSchema()->refresh();
        }
    }

    /**
     * Gets an inaccessible object property.
     *
     * @param bool $revoke whether to make property inaccessible after getting.
     *
     * @return mixed
     */
    protected function getInaccessibleProperty(object $object, string $propertyName, bool $revoke = true)
    {
        $class = new ReflectionClass($object);

        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }

        $property = $class->getProperty($propertyName);

        $property->setAccessible(true);

        $result = $property->getValue($object);

        if ($revoke) {
            $property->setAccessible(false);
        }

        return $result;
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
        $fixture = null;
        switch ($this->driverName) {
            case 'mssql':
                $fixture = $this->params()['yiisoft/db-mssql']['fixture'];
                break;
            case 'mysql':
                $fixture = $this->params()['yiisoft/db-mysql']['fixture'];
                break;
            case 'oci':
                $fixture = $this->params()['yiisoft/db-oracle']['fixture'];
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
        return match ($this->driverName) {
            'mysql', 'sqlite' => str_replace(['[[', ']]'], '`', $sql),
            'oci' => str_replace(['[[', ']]'], '"', $sql),
            'pgsql' => str_replace(['\\[', '\\]'], ['[', ']'], preg_replace('/(\[\[)|((?<!(\[))]])/', '"', $sql)),
            'mssql' => str_replace(['[[', ']]'], ['[', ']'], $sql),
            default => $sql,
        };
    }

    private function config(): array
    {
        $params = $this->params();

        return [
            CacheInterface::class => [
                'class' => Cache::class,
                '__construct()' => [
                    Reference::to(ArrayCache::class),
                ],
            ],

            LoggerInterface::class => Logger::class,

            ProfilerInterface::class => Profiler::class,

            ListenerProviderInterface::class => Provider::class,

            EventDispatcherInterface::class => Dispatcher::class,

            ConnectionPDOMssql::class => [
                'class' => ConnectionPDOMssql::class,
                '__construct()' => [
                    'driver' => $params['yiisoft/db-mssql']['driver'],
                ],
            ],

            ConnectionPDOMysql::class => [
                'class' => ConnectionPDOMysql::class,
                '__construct()' => [
                    'driver' => $params['yiisoft/db-mysql']['driver'],
                ],
            ],

            ConnectionPDOPgsql::class => [
                'class' => ConnectionPDOPgsql::class,
                '__construct()' => [
                    'driver' => $params['yiisoft/db-pgsql']['driver'],
                ],
            ],

            ConnectionPDOOracle::class => [
                'class' => ConnectionPDOOracle::class,
                '__construct()' => [
                    'driver' => $params['yiisoft/db-oracle']['driver'],
                ],
            ],

            ConnectionPDOSqlite::class => [
                'class' => ConnectionPDOSqlite::class,
                '__construct()' => [
                    'driver' => $params['yiisoft/db-sqlite']['driver'],
                ],
            ],

            Factory::class => [
                'class' => Factory::class,
                '__construct()' => [
                    'definitions' => [ConnectionInterface::class => Reference::to(ConnectionPDOSqlite::class)],
                ],
            ],
        ];
    }

    private function params(): array
    {
        return [
            'yiisoft/db-mssql' => [
                'driver' => new MssqlPDODriver(
                    (new MssqlDsn('sqlsrv', '127.0.0.1', 'yiitest'))->asString(),
                    'SA',
                    'YourStrong!Passw0rd',
                ),
                'fixture' => __DIR__ . '/Data/mssql.sql',
            ],
            'yiisoft/db-mysql' => [
                'driver' => new MysqlPDODriver(
                    (new MysqlDsn('mysql', '127.0.0.1', 'yiitest'))->asString(),
                    'root',
                    '',
                ),
                'fixture' => __DIR__ . '/Data/mysql.sql',
            ],
            'yiisoft/db-pgsql' => [
                'driver' => new PgsqlPDODriver(
                    (new PgsqlDsn('pgsql', '127.0.0.1', 'yiitest'))->asString(),
                    'root',
                    'root'
                ),
                'fixture' => __DIR__ . '/Data/pgsql.sql',
            ],
            'yiisoft/db-oracle' => [
                'driver' => new OraclePDODriver(
                    (new OracleDsn('oci', 'localhost', 'XE', ['charset' => 'AL32UTF8']))->asString(),
                    'system',
                    'oracle',
                ),
                'fixture' => __DIR__ . '/Data/oci.sql',
            ],
            'yiisoft/db-sqlite' => [
                'driver' => new SqlitePDODriver(new SqliteDsn('sqlite', 'memory')),
                'fixture' => __DIR__ . '/Data/sqlite.sql',
            ],
        ];
    }
}
