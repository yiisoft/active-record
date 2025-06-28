<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use DateTimeImmutable;
use Yiisoft\ActiveRecord\Event\BeforeDelete;
use Yiisoft\ActiveRecord\Event\EventInterface;

/**
 * Event handler that allows to implement soft deletion in Active Record models. Instead of deleting records from the
 * database, it sets a value of the date and time for properties to indicate that the record has been logically deleted.
 * By default, it sets the current date and time to the `deleted_at` property.
 *
 * It can be applied to classes or properties, and it can be repeated for multiple properties.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class SoftDelete extends AbstractHandler
{
    public function __construct(
        private mixed $value = null,
        string ...$propertyNames,
    ) {
        $this->value ??= static fn(): DateTimeImmutable => new DateTimeImmutable();

        if (empty($propertyNames)) {
            $propertyNames = ['deleted_at'];
        }

        parent::__construct(...$propertyNames);
    }

    public function events(): array
    {
        return [BeforeDelete::class];
    }

    public function handle(EventInterface $event): void
    {
        match ($event::class) {
            BeforeDelete::class => $this->beforeDelete($event),
            default => null,
        };
    }

    private function beforeDelete(BeforeDelete $event): void
    {
        $model = $event->getModel();
        $value = is_callable($this->value) ? ($this->value)($event) : $this->value;

        $propertyValues = [];

        foreach ($this->getPropertyNames() as $propertyName) {
            if ($model->hasProperty($propertyName) && $model->get($propertyName) === null) {
                $propertyValues[$propertyName] = $value;
            }
        }

        if (!empty($propertyValues)) {
            $event->returnValue($model->update($propertyValues));
        }

        $event->preventDefault();
    }
}
