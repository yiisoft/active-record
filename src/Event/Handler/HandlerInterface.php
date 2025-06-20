<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Yiisoft\ActiveRecord\Event\EventInterface;

interface HandlerInterface
{
    /**
     * Returns the list of events the handler listens to.
     *
     * @return string[] The list of events.
     *
     * @psalm-return class-string<EventInterface>[]
     */
    public function events(): array;

    public function handle(EventInterface $event): void;

    /**
     * Returns the list of property names the handler should be applied to.
     *
     * @return string[]
     */
    public function getPropertyNames(): array;

    /**
     * Sets the list of property names the handler should be applied to.
     *
     * @param string[] $propertyNames
     */
    public function setPropertyNames(array $propertyNames): void;
}
