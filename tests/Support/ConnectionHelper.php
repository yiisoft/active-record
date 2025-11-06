<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Factory\Factory;

abstract class ConnectionHelper
{
    public function createFactory(ConnectionInterface $db): Factory
    {
        $container = new Container(ContainerConfig::create()->withDefinitions([ConnectionInterface::class => $db]));
        return new Factory($container, [ConnectionInterface::class => $db]);
    }

    protected function createSchemaCache(): SchemaCache
    {
        return new SchemaCache(new ArrayCache());
    }
}
