<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Tests\Support\DbHelper;
use Yiisoft\Db\Connection\ConnectionInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private bool $shouldReloadFixture = false;

    abstract protected static function createConnection(): ConnectionInterface;

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

    protected function reloadFixtureAfterTest(): void
    {
        $this->shouldReloadFixture = true;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $db = static::createConnection();
        ConnectionProvider::set($db);
        DbHelper::loadFixture($db);
    }

    protected function tearDown(): void
    {
        if ($this->shouldReloadFixture) {
            $this->reloadFixture();
            $this->shouldReloadFixture = false;
        }

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        ConnectionProvider::get()->close();
        ConnectionProvider::remove();

        parent::tearDownAfterClass();
    }
}
