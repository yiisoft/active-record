<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Yiisoft\ActiveRecord\Event\Handler\AttributeHandlerProvider;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;
use Yiisoft\EventDispatcher\Provider\Provider;

/**
 * Provider of event dispatchers for target objects.
 */
final class EventDispatcherProvider
{
    private static array $dispatchers = [];

    /**
     * Returns the dispatcher for the given target object class name. If the dispatcher is not defined,
     * it will be created using the listeners defined in the target object class's attributes.
     *
     * @param string $targetClass The target object class name.
     *
     * @return EventDispatcherInterface The dispatcher for the target.
     *
     * @psalm-param class-string $targetClass
     */
    public static function get(string $targetClass): EventDispatcherInterface
    {
        if (!isset(self::$dispatchers[$targetClass])) {
            self::$dispatchers[$targetClass] = new Dispatcher(new Provider(self::getListenersFromAttributes($targetClass)));
        }

        return self::$dispatchers[$targetClass];
    }

    /**
     * Sets the dispatcher for the target object class name.
     *
     * @param string $targetClass The target object class name.
     * @param EventDispatcherInterface $dispatcher The dispatcher to be set
     *
     * @psalm-param class-string $targetClass
     */
    public static function set(string $targetClass, EventDispatcherInterface $dispatcher): void
    {
        self::$dispatchers[$targetClass] = $dispatcher;
    }

    /**
     * Get listeners from attributes defined in the target object class.
     *
     * @psalm-param class-string $targetClass
     */
    private static function getListenersFromAttributes(string $targetClass): ListenerCollection
    {
        $reflection = new ReflectionClass($targetClass);
        $attributes = $reflection->getAttributes(AttributeHandlerProvider::class, ReflectionAttribute::IS_INSTANCEOF);

        $listener = new ListenerCollection();

        foreach ($attributes as $attribute) {
            /** @var AttributeHandlerProvider $handlerProvider */
            $handlerProvider = $attribute->newInstance();

            foreach ($handlerProvider->getEventHandlers() as $event => $handler) {
                $listener = $listener->add($handler, $event);
            }
        }

        $properties = $reflection->getProperties(
            ReflectionProperty::IS_PRIVATE
            | ReflectionProperty::IS_PROTECTED
            | ReflectionProperty::IS_PUBLIC
        );

        foreach ($properties as $property) {
            $attributes = $property->getAttributes(AttributeHandlerProvider::class, ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $attribute) {
                /** @var AttributeHandlerProvider $handlerProvider */
                $handlerProvider = $attribute->newInstance();
                $handlerProvider->setPropertyNames([$property->getName()]);

                foreach ($handlerProvider->getEventHandlers() as $event => $handler) {
                    $listener = $listener->add($handler, $event);
                }
            }
        }

        return $listener;
    }
}
