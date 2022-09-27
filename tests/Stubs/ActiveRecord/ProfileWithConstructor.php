<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Class Profile.
 *
 * @property int $id
 * @property string $description
 */
final class ProfileWithConstructor extends ActiveRecord
{
    public function __construct(ConnectionInterface $db, private Aliases $aliases)
    {
        parent::__construct($db);
    }

    public static function tableName(): string
    {
        return 'profile';
    }
}
