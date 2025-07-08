<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Yiisoft\ActiveRecord\Event\EventInterface;

/**
 * Abstract class for event handlers based on class attributes.
 */
abstract class AttributeHandler
{
    /**
     * @var string[] List of property names the handler should be applied to.
     */
    private array $propertyNames;

    /**
     * Handles the event.
     *
     * @param EventInterface $event The event to handle.
     */
    abstract public function handle(EventInterface $event): void;

    /**
     * @param string ...$propertyNames Names of properties the handler should be applied to.
     */
    public function __construct(
        string ...$propertyNames,
    ) {
        $this->propertyNames = $propertyNames;
    }

    /**
     * Returns the list of events the handler can handle.
     *
     * @return string[] The list of events.
     *
     * @psalm-return class-string<EventInterface>[]
     */
    public function getHandledEvents(): array
    {
        return [];
    }

    /**
     * Returns the list of property names the handler should be applied to.
     *
     * @return string[]
     */
    public function getPropertyNames(): array
    {
        return $this->propertyNames;
    }

    /**
     * Sets the list of property names the handler should be applied to.
     *
     * @param string[] $propertyNames
     */
    public function setPropertyNames(array $propertyNames): void
    {
        $this->propertyNames = $propertyNames;
    }
}
