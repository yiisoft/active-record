<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Tests\Support\DbHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private bool $shouldReloadFixture = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $db = static::createConnection();
        ConnectionProvider::set($db);
        DbHelper::loadFixture($db);
    }

    public static function tearDownAfterClass(): void
    {
        ConnectionProvider::get()->close();
        ConnectionProvider::remove();

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        if ($this->shouldReloadFixture) {
            $this->reloadFixture();
            $this->shouldReloadFixture = false;
        }

        parent::tearDown();
    }

    abstract protected static function createConnection(): ConnectionInterface;

    /**
     * Call this method in tests which modifies the database state to reload the connection and fixture after the tests.
     */
    protected function reloadFixtureAfterTest(): void
    {
        $this->shouldReloadFixture = true;
    }

    protected static function reloadFixture(): void
    {
        ConnectionProvider::get()->close();

        $db = static::createConnection();
        ConnectionProvider::set($db);

        DbHelper::loadFixture($db);
    }

    protected static function db(): ConnectionInterface
    {
        return ConnectionProvider::get();
    }
}
