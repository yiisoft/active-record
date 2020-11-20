<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\Aliases\Aliases;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Class Profile.
 *
 * @property int $id
 * @property string $description
 */
final class ProfileWithConstructor extends ActiveRecord
{
    private Aliases $aliases;

    public function __construct(ConnectionInterface $db, Aliases $aliases)
    {
        parent::__construct($db);

        $this->aliases = $aliases;
    }

    public function tableName(): string
    {
        return 'profile';
    }
}
