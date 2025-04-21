<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\ActiveRecordModel;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;

/**
 * Active Record class which implements {@see ActiveRecordInterface} and provides additional features like:
 *
 * @see MagicPropertiesTrait to access column values and relations via PHP magic methods as properties;
 * @see MagicRelationsTrait to access relation queries.
 */
class MagicActiveRecord extends ActiveRecordModel
{
    use MagicPropertiesTrait;
    use MagicRelationsTrait;
}
