<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use ArrayAccess;
use IteratorAggregate;
use Yiisoft\ActiveRecord\Trait\ArrayableTrait;
use Yiisoft\ActiveRecord\Trait\ArrayAccessTrait;
use Yiisoft\ActiveRecord\Trait\ArrayIteratorTrait;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;
use Yiisoft\ActiveRecord\Trait\TransactionalTrait;
use Yiisoft\Arrays\ArrayableInterface;

/**
 * Active Record class which implements {@see ActiveRecordInterface} and provides additional features like:
 *
 * - {@see ArrayableInterface}: to convert the object into an array;
 * - {@see ArrayAccess}: to access attributes as array elements;
 * - {@see IteratorAggregate}: to iterate over attributes;
 * - {@see TransactionalInterface}: to handle transactions;
 * - {@see MagicPropertiesTrait}: to access attributes as properties;
 * - {@see MagicRelationsTrait}: to access relation queries.
 *
 * @see BaseActiveRecord for more information.
 *
 * @template-implements ArrayAccess<string, mixed>
 * @template-implements IteratorAggregate<string, mixed>
 */
class ActiveRecord extends BaseActiveRecord implements
    ArrayableInterface,
    ArrayAccess,
    IteratorAggregate,
    TransactionalInterface
{
    use ArrayableTrait;
    use ArrayAccessTrait;
    use ArrayIteratorTrait;
    use MagicPropertiesTrait;
    use MagicRelationsTrait;
    use TransactionalTrait;
}
