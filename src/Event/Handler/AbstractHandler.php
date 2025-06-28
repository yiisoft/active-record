<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

/**
 * Base class for event handlers.
 */
abstract class AbstractHandler implements HandlerInterface
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

    public function events(): array
    {
        return [];
    }

    public function getPropertyNames(): array
    {
        return $this->propertyNames;
    }

    public function setPropertyNames(array $propertyNames): void
    {
        $this->propertyNames = $propertyNames;
    }
}
