<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordModel;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Class Profile.
 */
final class ProfileWithConstructor extends ActiveRecordModel
{
    protected int $id;
    protected string $description;

    public function __construct(ConnectionInterface $db, private Aliases $aliases)
    {
        parent::__construct($db);
    }

    public function tableName(): string
    {
        return 'profile';
    }
}
