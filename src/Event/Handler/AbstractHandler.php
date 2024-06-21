<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Yiisoft\ActiveRecord\Event\EventInterface;

abstract class AbstractHandler implements HandlerInterface
{
    private array $propertyNames;

    public function __construct(
        string ...$propertyNames,
    ) {
        $this->propertyNames = $propertyNames;
    }

    /**
     * Returns the list of events the handler listens to.
     *
     * @psalm-return array<string, class-string<EventInterface>>
     */
    public function events(): array
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
