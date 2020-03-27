<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Tests\Stubs\Connection\MysqlConnection;
use Yiisoft\ActiveRecord\Tests\Stubs\Connection\PgsqlConnection;
use Yiisoft\ActiveRecord\Tests\Stubs\Connection\SqliteConnection;
use Yiisoft\Composer\Config\Builder;
use Yiisoft\Db\Connection\Connection;
use Yiisoft\Di\Container;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected ?Connection $mysqlConnection = null;
    protected ?Connection $pgsqlConnection = null;
    protected ?Connection $sqliteConnection = null;

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
        $config = require Builder::path('tests');

        $this->container = new Container($config);

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

    protected function loadFixture(Connection $db): void
    {
        if ($db->isActive()) {
            $db->close();
        }

        $db->open();

        if ($this->driverName === 'oci') {
            [$drops, $creates] = explode('/* STATEMENTS */', file_get_contents($db->getFixture()), 2);
            [$statements, $triggers, $data] = explode('/* TRIGGERS */', $creates, 3);
            $lines = array_merge(
                explode('--', $drops),
                explode(';', $statements),
                explode('/', $triggers),
                explode(';', $data)
            );
        } else {
            $lines = explode(';', file_get_contents($db->getFixture()));
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
}
