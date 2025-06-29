<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Yiisoft\ActiveRecord\Event\EventInterface;

/**
 * Represents an event handler for handling events related to Active Record models.
 */
interface HandlerInterface
{
    /**
     * Returns the list of events the handler can handle.
     *
     * @return string[] The list of events.
     *
     * @psalm-return class-string<EventInterface>[]
     */
    public function events(): array;

    /**
     * Handles the event.
     *
     * @param EventInterface $event The event to handle.
     */
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
