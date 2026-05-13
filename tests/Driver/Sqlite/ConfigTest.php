<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Driver\Sqlite;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Sqlite\Connection as SQLiteConnection;
use Yiisoft\Db\Sqlite\Driver as SQLiteDriver;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;
use Yiisoft\Yii\Runner\BootstrapRunner;

use function dirname;

final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        if (ConnectionProvider::has()) {
            ConnectionProvider::get()->close();
            ConnectionProvider::remove();
        }

        parent::tearDown();
    }

    public function testBootstrap(): void
    {
        $container = $this->createConsoleContainer();
        $bootstrapList = $this->getBootstrapList();

        $this->assertFalse(ConnectionProvider::has());

        (new BootstrapRunner($container, $bootstrapList))->run();

        $this->assertTrue(ConnectionProvider::has());
        $this->assertInstanceOf(SQLiteConnection::class, ConnectionProvider::get());
    }

    private function createConsoleContainer(): Container
    {
        $config = ContainerConfig::create()
            ->withDefinitions(
                [
                    CacheInterface::class => MemorySimpleCache::class,

                    ConnectionInterface::class => [
                        'class' => SQLiteConnection::class,
                        '__construct()' => [
                            'driver' => new SQLiteDriver('sqlite::memory:'),
                        ],
                    ],
                ],
            );

        return new Container($config);
    }

    private function getBootstrapList(): array
    {
        return require dirname(__DIR__, 3) . '/config/bootstrap.php';
    }
}
