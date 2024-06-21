<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use Yiisoft\ActiveRecord\Event\BeforeInsert;
use Yiisoft\ActiveRecord\Event\EventInterface;

use function is_callable;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class DefaultValueOnInsert extends AbstractHandler
{
    public function __construct(
        private readonly mixed $value = null,
        string ...$propertyNames,
    ) {
        parent::__construct(...$propertyNames);
    }

    public function events(): array
    {
        return [BeforeInsert::class];
    }

    public function handle(EventInterface $event): void
    {
        match ($event::class) {
            BeforeInsert::class => $this->beforeInsert($event),
            default => null,
        };
    }

    private function beforeInsert(BeforeInsert $event): void
    {
        $model = $event->getModel();

        $value = is_callable($this->value)
            ? ($this->value)($model)
            : $this->value;

        foreach ($this->getPropertyNames() as $propertyName) {
            if (!$model->hasProperty($propertyName)) {
                continue;
            }

            if ($model->get($propertyName) === null) {
                $model->set($propertyName, $value);
            }
        }
    }
}
