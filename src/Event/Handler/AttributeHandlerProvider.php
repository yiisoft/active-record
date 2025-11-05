<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Closure;

/**
 * Abstract class for event handlers provider based on class attributes.
 */
abstract class AttributeHandlerProvider
{
    /**
     * @var string[] List of property names the handler should be applied to.
     */
    private array $propertyNames;

    /**
     * @param string ...$propertyNames Names of properties the handler should be applied to.
     */
    public function __construct(
        string ...$propertyNames,
    ) {
        $this->propertyNames = $propertyNames;
    }

    /**
     * Returns array with event class names as keys and their handlers as values `[event_class => handler_closure, ...]`
     *
     * @psalm-return array<class-string, Closure>
     */
    abstract public function getEventHandlers(): array;

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
