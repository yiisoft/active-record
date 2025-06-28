<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Base class for events in Active Record models.
 */
abstract class AbstractEvent implements EventInterface
{
    /** @var bool Whether the default action of the event should be prevented. */
    private bool $isDefaultPrevented = false;
    /** @var bool Whether the propagation of the event should be stopped. */
    private bool $isPropagationStopped = false;
    /** @var mixed The return value if the default action is prevented. */
    private mixed $returnValue = null;

    public function __construct(private readonly ActiveRecordInterface $model)
    {
    }

    public function getModel(): ActiveRecordInterface
    {
        return $this->model;
    }

    public function getReturnValue(): mixed
    {
        return $this->returnValue;
    }

    public function isDefaultPrevented(): bool
    {
        return $this->isDefaultPrevented;
    }

    public function isPropagationStopped(): bool
    {
        return $this->isPropagationStopped;
    }

    public function preventDefault(): void
    {
        $this->isDefaultPrevented = true;
    }

    public function returnValue(mixed $returnValue): void
    {
        $this->returnValue = $returnValue;
    }

    public function stopPropagation(): void
    {
        $this->isPropagationStopped = true;
    }
}
