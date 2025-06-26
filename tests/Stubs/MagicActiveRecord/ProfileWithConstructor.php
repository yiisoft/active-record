<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Class Profile.
 *
 * @property int $id
 * @property string $description
 */
final class ProfileWithConstructor extends MagicActiveRecord
{
    public function __construct(ConnectionInterface $db, private Aliases $aliases)
    {
    }

    public function tableName(): string
    {
        return 'profile';
    }
}
