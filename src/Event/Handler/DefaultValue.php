<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use Yiisoft\ActiveRecord\Event\AfterPopulate;

use function is_callable;

/**
 * Attribute for setting default value for properties of an Active Record model after it has been populated.
 *
 * It can be applied to classes or properties, and it can be repeated for multiple properties.
 *
 * @psalm-suppress ClassMustBeFinal
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class DefaultValue extends AttributeHandlerProvider
{
    public function __construct(
        private readonly mixed $value = null,
        string ...$propertyNames,
    ) {
        parent::__construct(...$propertyNames);
    }

    public function getEventHandlers(): array
    {
        return [
            AfterPopulate::class => $this->afterPopulate(...),
        ];
    }

    private function afterPopulate(AfterPopulate $event): void
    {
        $model = $event->model;
        $value = is_callable($this->value) ? ($this->value)($event) : $this->value;

        foreach ($this->getPropertyNames() as $propertyName) {
            if ($model->hasProperty($propertyName) && $model->get($propertyName) === null) {
                $model->set($propertyName, $value);
            }
        }
    }
}
