<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Psr\EventDispatcher\StoppableEventInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Base class for events in Active Record models.
 */
abstract class AbstractEvent implements StoppableEventInterface
{
    /** @var bool Whether the default action of the event should be prevented. */
    private bool $isDefaultPrevented = false;
    /** @var bool Whether the propagation of the event should be stopped. */
    private bool $isPropagationStopped = false;
    /** @var mixed The return value if the default action is prevented. */
    private mixed $returnValue = null;

    /**
     * @param ActiveRecordInterface $model The target model associated with this event.
     */
    public function __construct(private readonly ActiveRecordInterface $model)
    {
    }

    /**
     * @return ActiveRecordInterface The target model associated with this event.
     */
    public function getModel(): ActiveRecordInterface
    {
        return $this->model;
    }

    /**
     * Returns the value that will be returned by the method that triggered this event
     * if the {@see isDefaultPrevented() default action is prevented}.
     */
    public function getReturnValue(): mixed
    {
        return $this->returnValue;
    }

    /**
     * Checks if the default action associated with this event has been prevented.
     */
    public function isDefaultPrevented(): bool
    {
        return $this->isDefaultPrevented;
    }

    public function isPropagationStopped(): bool
    {
        return $this->isPropagationStopped;
    }

    /**
     * Prevents the default action associated with this event from being executed.
     *
     * @see returnValue()
     */
    public function preventDefault(): void
    {
        $this->isDefaultPrevented = true;
    }

    /**
     * Sets the return value which will be returned by the method that triggered this event
     * if the {@see isDefaultPrevented() default action is prevented}.
     *
     * @see preventDefault()
     */
    public function returnValue(mixed $returnValue): void
    {
        $this->returnValue = $returnValue;
    }

    /**
     * Stops the propagation of the event to further listeners.
     * No further listeners will be notified after this method is called.
     */
    public function stopPropagation(): void
    {
        $this->isPropagationStopped = true;
    }
}
