<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\ActiveRecord\ActiveRecordFactory;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Factory\Factory;

abstract class ConnectionHelper
{
    protected Factory $factory;

    public function createARFactory(ConnectionInterface $db): ActiveRecordFactory
    {
        return new ActiveRecordFactory($this->createFactory($db));
    }

    protected function createSchemaCache(): SchemaCache
    {
        return new SchemaCache(new ArrayCache());
    }

    private function createFactory(ConnectionInterface $db): Factory
    {
        $container = new Container(ContainerConfig::create()->withDefinitions([ConnectionInterface::class => $db]));
        return new Factory($container, [ConnectionInterface::class => $db]);
    }
}
