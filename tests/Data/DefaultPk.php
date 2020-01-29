<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * DefaultPk.
 *
 * @property int $id
 */
class DefaultPk extends ActiveRecord
{
    public static function tableName()
    {
        return 'default_pk';
    }
}
