<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use Yiisoft\ActiveRecord\Event\BeforeUpdate;
use Yiisoft\ActiveRecord\Event\EventInterface;

use function is_callable;

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
        return [BeforeUpdate::class];
    }

    public function handle(EventInterface $event): void
    {
        match ($event::class) {
            BeforeUpdate::class => $this->beforeUpdate($event),
            default => null,
        };
    }

    private function beforeUpdate(BeforeUpdate $event): void
    {
        $model = $event->getModel();

        $value = is_callable($this->value)
            ? ($this->value)($model)
            : $this->value;

        foreach ($this->getPropertyNames() as $propertyName) {
            if (!$model->hasProperty($propertyName)) {
                continue;
            }

            $model->set($propertyName, $value);
        }
    }
}
