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

final class EventDispatcher
{
    public function __construct(
        private Dispatcher|null $dispatcher = null,
        private ListenerCollection|null $listeners = null,
    ) {
    }

    public function addListener(callable $listener, string ...$eventClassNames): void
    {
        $this->listeners = $this->getListeners()->add($listener, ...$eventClassNames);
    }

    public function addListenersFromAttributes(ActiveRecordInterface $model): void
    {
        if (isset($this->listeners)) {
            return;
        }

        $reflection = new ReflectionObject($model);
        $attributes = $reflection->getAttributes(HandlerInterface::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            $handler = $attribute->newInstance();
            $this->listeners = $this->getListeners()->add([$handler, 'handle'], ...$handler->events());
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

    public function dispatch(EventInterface $event): void
    {
        $this->getDispatcher()->dispatch($event);
    }

    public function getDispatcher(): Dispatcher
    {
        return $this->dispatcher ??= new Dispatcher(new Provider($this->getListeners()));
    }

    protected function getListeners(): ListenerCollection
    {
        return $this->listeners ??= new ListenerCollection();
    }
}
