<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Event\Handler;

use Attribute;
use DateTimeImmutable;
use DateTimeInterface;
use Yiisoft\Db\Expression\Expression;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class DefaultDateTimeOnInsert extends DefaultValueOnInsert
{
    public function __construct(
        float|int|string|DateTimeInterface|Expression|null $value = null,
        string ...$propertyNames,
    ) {
        $value ??= static fn() => new DateTimeImmutable();

        if (empty($propertyNames)) {
            $propertyNames = ['created_at', 'updated_at'];
        }

        parent::__construct($value, ...$propertyNames);
    }
}
