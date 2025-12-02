<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Class Profile.
 */
final class ProfileWithConstructor extends ActiveRecord
{
    protected int $id;
    protected string $description;

    public function __construct(ConnectionInterface $db) {}

    public function tableName(): string
    {
        return 'profile';
    }
}
