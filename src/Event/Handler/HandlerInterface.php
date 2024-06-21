<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Yiisoft\ActiveRecord\Event\EventInterface;

interface HandlerInterface
{
    public function events(): array;
    public function handle(EventInterface $event): void;
    public function getPropertyNames(): array;
    public function setPropertyNames(array $propertyNames);
}
