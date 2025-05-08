<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\ConnectionProviderMiddleware;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Middleware\Dispatcher\MiddlewareFactory;

abstract class ConnectionProviderTest extends TestCase
{
    public function testConnectionProvider(): void
    {
        $this->assertTrue(ConnectionProvider::has('default'));
        $this->assertFalse(ConnectionProvider::has('db2'));

        $db = ConnectionProvider::get();

        $this->assertTrue(ConnectionProvider::has('default'));
        $this->assertSame($db, ConnectionProvider::get('default'));

        $list = ConnectionProvider::all();

        $this->assertSame($list, ['default' => $db]);

        $db2 = $this->createConnection();
        ConnectionProvider::set($db2, 'db2');

        $this->assertTrue(ConnectionProvider::has('db2'));
        $this->assertSame($db2, ConnectionProvider::get('db2'));

        $list = ConnectionProvider::all();

        $this->assertSame($list, ['default' => $db, 'db2' => $db2]);

        ConnectionProvider::remove('db2');

        $this->assertFalse(ConnectionProvider::has('db2'));

        $list = ConnectionProvider::all();

        $this->assertSame($list, ['default' => $db]);
    }

    public function testConnectionProviderMiddleware(): void
    {
        $this->reloadFixtureAfterTest();

        ConnectionProvider::remove();

        $this->assertEmpty(ConnectionProvider::all());
        $this->assertFalse(ConnectionProvider::has('default'));

        $db = $this->createConnection();
        $container = new Container(ContainerConfig::create()->withDefinitions([ConnectionInterface::class => $db]));
        $request = $this->createMock(ServerRequestInterface::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $dispatcher = (new MiddlewareDispatcher(new MiddlewareFactory($container)))
            ->withMiddlewares([ConnectionProviderMiddleware::class]);

        $dispatcher->dispatch($request, $requestHandler);

        $this->assertTrue(ConnectionProvider::has('default'));
        $this->assertSame($db, ConnectionProvider::get());
    }
}
