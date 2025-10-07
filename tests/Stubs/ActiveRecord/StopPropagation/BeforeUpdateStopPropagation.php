<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\StopPropagation;

use Attribute;
use Yiisoft\ActiveRecord\Event\BeforeUpdate;
use Yiisoft\ActiveRecord\Event\Handler\AttributeHandlerProvider;

#[Attribute(Attribute::TARGET_CLASS)]
final class BeforeUpdateStopPropagation extends AttributeHandlerProvider
{
    public function getEventHandlers(): array
    {
        return [
            BeforeUpdate::class => $this->beforeUpdate(...),
        ];
    }

    private function beforeUpdate(BeforeUpdate $event): void
    {
        $event->stopPropagation();
    }
}
