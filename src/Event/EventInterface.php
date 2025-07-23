<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Psr\EventDispatcher\StoppableEventInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Represents an event related to Active Record models.
 */
interface EventInterface extends StoppableEventInterface
{
    /**
     * Returns the target model associated with this event.
     */
    public function getModel(): ActiveRecordInterface;

    /**
     * Returns the value that will be returned by the method that triggered this event
     * if the {@see isDefaultPrevented() default action is prevented}.
     */
    public function getReturnValue(): mixed;

    /**
     * Checks if the default action associated with this event has been prevented.
     */
    public function isDefaultPrevented(): bool;

    /**
     * Prevents the default action associated with this event from being executed.
     *
     * @see returnValue()
     */
    public function preventDefault(): void;

    /**
     * Sets the return value which will be returned by the method that triggered this event
     * if the {@see isDefaultPrevented() default action is prevented}.
     *
     * @see preventDefault()
     */
    public function returnValue(mixed $returnValue): void;

    /**
     * Stops the propagation of the event to further listeners.
     * No further listeners will be notified after this method is called.
     */
    public function stopPropagation(): void;
}
