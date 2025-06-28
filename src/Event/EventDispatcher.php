<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use ReflectionAttribute;
use ReflectionObject;
use ReflectionProperty;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Event\Handler\HandlerInterface;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;
use Yiisoft\EventDispatcher\Provider\Provider;

/**
 * Managing and dispatching events related to Active Record models.
 * It allows adding listeners for specific events and dispatching events to those listeners.
 * It can also automatically add listeners based on attributes defined in the Active Record model.
 */
final class EventDispatcher
{
    public function __construct(
        private Dispatcher|null $dispatcher = null,
        private ListenerCollection|null $listeners = null,
    ) {
    }

    /**
     * Adds a listener for specific event class names.
     */
    public function addListener(callable $listener, string ...$eventClassNames): void
    {
        $this->listeners = $this->getListeners()->add($listener, ...$eventClassNames);
    }

    /**
     * Adds listeners from attributes defined in the Active Record model.
     */
    public function addListenersFromAttributes(ActiveRecordInterface $model): void
    {
        if (isset($this->listeners)) {
            return;
        }

        $reflection = new ReflectionObject($model);
        $attributes = $reflection->getAttributes(HandlerInterface::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            $handler = $attribute->newInstance();
            $this->listeners = $this->getListeners()->add($handler->handle(...), ...$handler->events());
        }

        $properties = $reflection->getProperties(
            ReflectionProperty::IS_PRIVATE
            | ReflectionProperty::IS_PROTECTED
            | ReflectionProperty::IS_PUBLIC
        );

        foreach ($properties as $property) {
            $attributes = $property->getAttributes(HandlerInterface::class, ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $attribute) {
                /** @var HandlerInterface $handler */
                $handler = $attribute->newInstance();
                $handler->setPropertyNames([$property->getName()]);
                $this->listeners = $this->getListeners()->add($handler->handle(...), ...$handler->events());
            }
        }
    }

    /**
     * Dispatches an event to all registered listeners.
     */
    public function dispatch(EventInterface $event): void
    {
        $this->getDispatcher()->dispatch($event);
    }

    /**
     * Returns the event dispatcher instance.
     */
    public function getDispatcher(): Dispatcher
    {
        return $this->dispatcher ??= new Dispatcher(new Provider($this->getListeners()));
    }

    /**
     * Returns the collection of listeners.
     */
    protected function getListeners(): ListenerCollection
    {
        return $this->listeners ??= new ListenerCollection();
    }
}
