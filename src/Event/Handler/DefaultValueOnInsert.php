<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use Yiisoft\ActiveRecord\Event\BeforeInsert;
use Yiisoft\ActiveRecord\Event\BeforeUpsert;
use Yiisoft\ActiveRecord\Event\EventInterface;

use function array_keys;
use function is_callable;

/**
 * Attribute for setting default value for properties before inserting a new record into the database.
 *
 * It can be applied to classes or properties, and it can be repeated for multiple properties.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class DefaultValueOnInsert extends AttributeHandler
{
    public function __construct(
        private readonly mixed $value = null,
        string ...$propertyNames,
    ) {
        parent::__construct(...$propertyNames);
    }

    public function getHandledEvents(): array
    {
        return [BeforeInsert::class, BeforeUpsert::class];
    }

    public function handle(EventInterface $event): void
    {
        match ($event::class) {
            BeforeInsert::class => $this->beforeInsert($event),
            BeforeUpsert::class => $this->beforeUpsert($event),
            default => null,
        };
    }

    private function beforeInsert(BeforeInsert $event): void
    {
        $model = $event->getModel();
        $value = is_callable($this->value) ? ($this->value)($event) : $this->value;

        foreach ($this->getPropertyNames() as $propertyName) {
            if ($model->hasProperty($propertyName) && $model->get($propertyName) === null) {
                $model->set($propertyName, $value);
            }
        }
    }

    private function beforeUpsert(BeforeUpsert $event): void
    {
        $model = $event->getModel();
        $value = is_callable($this->value) ? ($this->value)($event) : $this->value;

        foreach ($this->getPropertyNames() as $propertyName) {
            if ($model->hasProperty($propertyName)
                && $model->get($propertyName) === null
                && !isset($event->insertProperties[$propertyName])
            ) {
                $event->insertProperties ??= array_keys($model->newValues());
                $event->insertProperties[$propertyName] = $value;
            }
        }
    }
}
