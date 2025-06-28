<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use Yiisoft\ActiveRecord\Event\BeforeUpdate;
use Yiisoft\ActiveRecord\Event\BeforeUpsert;
use Yiisoft\ActiveRecord\Event\EventInterface;

use function is_callable;

/**
 * This attribute is used to set a value for properties before updating an existing record in the database.
 *
 * It can be applied to classes or properties, and it can be repeated for multiple properties.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class SetValueOnUpdate extends AbstractHandler
{
    public function __construct(
        private mixed $value = null,
        string ...$propertyNames,
    ) {
        parent::__construct(...$propertyNames);
    }

    public function events(): array
    {
        return [BeforeUpdate::class, BeforeUpsert::class];
    }

    public function handle(EventInterface $event): void
    {
        match ($event::class) {
            BeforeUpdate::class => $this->beforeUpdate($event),
            BeforeUpsert::class => $this->beforeUpsert($event),
            default => null,
        };
    }

    private function beforeUpdate(BeforeUpdate $event): void
    {
        $model = $event->getModel();
        $value = is_callable($this->value) ? ($this->value)($event) : $this->value;

        foreach ($this->getPropertyNames() as $propertyName) {
            if ($model->hasProperty($propertyName)) {
                $model->set($propertyName, $value);
            }
        }
    }

    private function beforeUpsert(BeforeUpsert $event): void
    {
        $model = $event->getModel();
        $updateProperties = &$event->getUpdateProperties();
        $value = is_callable($this->value) ? ($this->value)($event) : $this->value;

        match ($updateProperties) {
            true => $updateProperties = &$event->getInsertProperties(),
            false => $updateProperties = [],
            default => null,
        };

        foreach ($this->getPropertyNames() as $propertyName) {
            if ($model->hasProperty($propertyName)
                && $model->get($propertyName) === null
                && !isset($updateProperties[$propertyName])
            ) {
                $updateProperties ??= array_keys($model->newValues());
                /** @psalm-suppress PossiblyInvalidArrayAssignment */
                $updateProperties[$propertyName] = $value;
            }
        }
    }
}
