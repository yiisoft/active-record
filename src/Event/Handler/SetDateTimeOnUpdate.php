<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use DateTimeImmutable;
use DateTimeInterface;
use Yiisoft\Db\Expression\Expression;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class SetDateTimeOnUpdate extends SetValueOnUpdate
{
    public function __construct(
        float|int|string|DateTimeInterface|Expression|null $value = null,
        string ...$propertyNames,
    ) {
        $value ??= static fn (): DateTimeImmutable => new DateTimeImmutable();

        if (empty($propertyNames)) {
            $propertyNames = ['updated_at'];
        }

        parent::__construct($value, ...$propertyNames);
    }
}
