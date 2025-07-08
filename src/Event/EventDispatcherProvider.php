<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionAttribute;
use ReflectionObject;
use ReflectionProperty;
use Yiisoft\ActiveRecord\Event\Handler\AttributeHandler;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;
use Yiisoft\EventDispatcher\Provider\Provider;

final class EventDispatcherProvider
{
    private static array $dispatchers = [];

    public static function get(object $target): EventDispatcherInterface
    {
        $class = $target::class;

        if (!isset(self::$dispatchers[$class])) {
            self::$dispatchers[$class] = new Dispatcher(new Provider(self::getListenersFromAttributes($target)));
        }

        return self::$dispatchers[$class];
    }

    /**
     * Get listeners from attributes defined in the target object.
     */
    private static function getListenersFromAttributes(object $target): ListenerCollection
    {
        $reflection = new ReflectionObject($target);
        $attributes = $reflection->getAttributes(AttributeHandler::class, ReflectionAttribute::IS_INSTANCEOF);

        $listener = new ListenerCollection();

        foreach ($attributes as $attribute) {
            /** @var AttributeHandler $handler */
            $handler = $attribute->newInstance();

            $listener = $listener->add($handler->handle(...), ...$handler->getHandledEvents());
        }

        $properties = $reflection->getProperties(
            ReflectionProperty::IS_PRIVATE
            | ReflectionProperty::IS_PROTECTED
            | ReflectionProperty::IS_PUBLIC
        );

        foreach ($properties as $property) {
            $attributes = $property->getAttributes(AttributeHandler::class, ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $attribute) {
                /** @var AttributeHandler $handler */
                $handler = $attribute->newInstance();
                $handler->setPropertyNames([$property->getName()]);

                $listener = $listener->add($handler->handle(...), ...$handler->getHandledEvents());
            }
        }

        return $listener;
    }
}
