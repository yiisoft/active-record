<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use Yiisoft\ActiveRecord\Event\BeforeUpdate;
use Yiisoft\ActiveRecord\Event\BeforeUpsert;

use function array_diff_key;
use function array_fill_keys;
use function is_callable;

/**
 * Attribute for setting value for properties before updating an existing record in the database.
 *
 * It can be applied to classes or properties, and it can be repeated for multiple properties.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class SetValueOnUpdate extends AttributeHandlerProvider
{
    public function __construct(
        private mixed $value = null,
        string ...$propertyNames,
    ) {
        parent::__construct(...$propertyNames);
    }

    public function getEventHandlers(): array
    {
        return [
            BeforeUpdate::class => $this->beforeUpdate(...),
            BeforeUpsert::class => $this->beforeUpsert(...),
        ];
    }

    private function beforeUpdate(BeforeUpdate $event): void
    {
        $model = $event->model;
        $value = is_callable($this->value) ? ($this->value)($event) : $this->value;

        foreach ($this->getPropertyNames() as $propertyName) {
            if ($model->hasProperty($propertyName)) {
                $model->set($propertyName, $value);
            }
        }
    }

    private function beforeUpsert(BeforeUpsert $event): void
    {
        $model = $event->model;
        $value = is_callable($this->value) ? ($this->value)($event) : $this->value;

        foreach ($this->getPropertyNames() as $propertyName) {
            if ($model->hasProperty($propertyName)) {
                $updateProperties ??= match ($event->updateProperties) {
                    true => array_diff_key(
                        $event->insertProperties ?? $model->newValues(),
                        array_fill_keys($model->primaryKey(), null)
                    ),
                    false => [],
                    default => $event->updateProperties,
                };

                $updateProperties[$propertyName] = $value;
            }
        }

        if (!empty($updateProperties)) {
            $event->updateProperties = $updateProperties;
        }
    }
}
