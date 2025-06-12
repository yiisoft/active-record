<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event;

use Yiisoft\ActiveRecord\ActiveRecordInterface;

abstract class AbstractEvent implements EventInterface
{
    private bool $propagationStopped = false;

    public function __construct(private readonly ActiveRecordInterface $model)
    {
    }

    public function getModel(): ActiveRecordInterface
    {
        return $this->model;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
